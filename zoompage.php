<?php
/**************************************************************************
 **********      English Wikipedia Account Request Interface      **********
 ***************************************************************************
 ** Wikipedia Account Request Graphic Design by Charles Melbye,           **
 ** which is licensed under a Creative Commons                            **
 ** Attribution-Noncommercial-Share Alike 3.0 United States License.      **
 **                                                                       **
 ** All other code are released under the Public Domain                   **
 ** by the ACC Development Team.                                          **
 **                                                                       **
 ** See CREDITS for the list of developers.                               **
 ***************************************************************************/

if (!defined("ACC")) {
	die();
} // Invalid entry point

function zoomPage($id,$urlhash)
{
	global $tsSQLlink, $session, $skin, $tsurl, $messages, $availableRequestStates, $dontUseWikiDb, $internalInterface, $createdid;
	global $smarty, $locationProvider, $rdnsProvider;
    
    $database = gGetDb();
    $request = Request::getById($id, $database);
    if($request == false)
    {
        // Notifies the user and stops the script.
        BootstrapSkin::displayAlertBox("Could not load the requested request!", "alert-error","Error",true,false);
        BootstrapSkin::displayInternalFooter();
        die();
    }
    
	$gid = $request->getId();
    
    $smarty->assign('request', $request);
    
	$urlhash = sanitize($urlhash);
    
    // TODO: move to template
	if ($request->getEmailConfirm() != 'Confirmed' && $request->getEmailConfirm() != "" && !isset($_GET['ecoverride'])) {
		$out .= $skin->displayRequestMsg("Email has not yet been confirmed for this request, so it can not yet be closed or viewed.");
		return $out;
	}
    
	$thisip = $request->getTrustedIp();
    $smarty->assign("iplocation", $locationProvider->getIpLocation($thisip));
	$thisid = $request->getId();
	$thisemail = $request->getEmail();
    
	$sUser = $request->getName();
	$smarty->assign("usernamerawunicode", html_entity_decode($sUser));
	$createreason = "Requested account at [[WP:ACC]], request #" . $request->getId();
	$smarty->assign("createreason", $createreason);
	$smarty->assign("createdid", $createdid);
	$createdreason = EmailTemplate::getById($createdid, gGetDb());
	$smarty->assign("createdname", $createdreason->getName());
	$smarty->assign("createdquestion", $createdreason->getJsquestion());

	//#region setup whether data is viewable or not
	
	// build the sql fragment of possible open states
	$statesSqlFragment = " ";
	foreach($availableRequestStates as $k => $v){
		$statesSqlFragment .= "pend_status = '".sanitize($k)."' OR ";
	}
	$statesSqlFragment = rtrim($statesSqlFragment, " OR");
	
	$sessionuser = $_SESSION['userID'];
	$query = "SELECT * FROM acc_pend WHERE pend_email = '" . 
				mysql_real_escape_string($thisemail, $tsSQLlink) . 
				"' AND pend_reserved = '" . 
				mysql_real_escape_string($sessionuser, $tsSQLlink) . 
				"' AND pend_mailconfirm = 'Confirmed' AND ( ".$statesSqlFragment." );";

	$result = mysql_query($query, $tsSQLlink);
	if (!$result) {
		Die("Query failed: $query ERROR: " . mysql_error());
	}
	$hideemail = TRUE;
	if (mysql_num_rows($result) > 0) {
		$hideemail = FALSE;
	}

	$sessionuser = $_SESSION['userID'];
	$query2 = "SELECT * FROM acc_pend WHERE (pend_ip = '" . 
			mysql_real_escape_string($thisip, $tsSQLlink) . 
			"' OR pend_proxyip LIKE '%" .
			mysql_real_escape_string($thisip, $tsSQLlink) . 
			"%') AND pend_reserved = '" .
			mysql_real_escape_string($sessionuser, $tsSQLlink) . 
			"' AND pend_mailconfirm = 'Confirmed' AND ( ".$statesSqlFragment." );";

	$result2 = mysql_query($query2, $tsSQLlink);
	
	if (!$result2) {
		Die("Query failed: $query2 ERROR: " . mysql_error());
	}
	
	$hideip = TRUE;
	
	if (mysql_num_rows($result2) > 0) {
		$hideip = FALSE;
	}
	
	if( $hideip == FALSE || $hideemail == FALSE ) {
		$hideinfo = FALSE;
	} else {
		$hideinfo = TRUE;
	}
	
	//#endregion
	
	if ($request->getStatus() == "Closed") {
		$hash = md5($thisid. $thisemail . $thisip . microtime()); //If the request is closed, change the hash based on microseconds similar to the checksums.
		$smarty->assign("isclosed", true);
	} else {
		$hash = md5($thisid . $thisemail . $thisip);
		$smarty->assign("isclosed", false);
	}
	$smarty->assign("hash", $hash);
	if ($hash == $urlhash) {
		$correcthash = TRUE;
	}
	else {
		$correcthash = FALSE;
	}
	
	$smarty->assign("showinfo", false);
	if ($hideinfo == FALSE || $correcthash == TRUE || $session->hasright($_SESSION['user'], 'Admin') || $session->isCheckuser($_SESSION['user']))
    {
		$smarty->assign("showinfo", true);
    }
    
	if ($hideinfo == FALSE || $correcthash == TRUE || $session->hasright($_SESSION['user'], 'Admin') || $session->isCheckuser($_SESSION['user']) ) {
		$smarty->assign("proxyip", $request->getForwardedIp());
		if ($request->getForwardedIp()) {
			$smartyproxies = array(); // Initialize array to store data to be output in Smarty template.
			$smartyproxiesindex = 0;
			
			$proxies = explode(",", $request->getForwardedIp());
			$proxies[] = $request->getIp();
			
			$origin = $proxies[0];
			$smarty->assign("origin", $origin);
			
			$proxies = array_reverse($proxies);
			$trust = true;
            global $rfc1918ips;

            foreach($proxies as $proxynum => $p) {
                $p2 = trim($p);
				$smartyproxies[$smartyproxiesindex]['ip'] = $p2;

                // get data on this IP.
				$trusted = isXffTrusted($p2);
				$ipisprivate = ipInRange($rfc1918ips, $p2);
                
                if( !$ipisprivate) 
                {
				    $iprdns = $rdnsProvider->getRdns($p2);
                    $iplocation = $locationProvider->getIpLocation($p2);
                }
                else
                {
                    // this is going to fail, so why bother trying?
                    $iprdns = false;
                    $iplocation = false;
                }
                
                // current trust chain status BEFORE this link
				$pretrust = $trust;
				
                // is *this* link trusted?
				$smartyproxies[$smartyproxiesindex]['trustedlink'] = $trusted;
                
                // current trust chain status AFTER this link
                $trust = $trust & $trusted;
                if($pretrust && $p2 == $origin)
                {
                    $trust = true;   
                }
				$smartyproxies[$smartyproxiesindex]['trust'] = $trust;
				
				$smartyproxies[$smartyproxiesindex]['rdnsfailed'] = $iprdns === false;
				$smartyproxies[$smartyproxiesindex]['rdns'] = $iprdns;
				$smartyproxies[$smartyproxiesindex]['routable'] = ! $ipisprivate;
				
				$smartyproxies[$smartyproxiesindex]['location'] = $iplocation;
				
                if( $iprdns == $p2 && $ipisprivate == false) {
					$smartyproxies[$smartyproxiesindex]['rdns'] = NULL;
				}
                
				$smartyproxies[$smartyproxiesindex]['showlinks'] = (!$trust || $p2 == $origin) && !$ipisprivate;
                
				$smartyproxiesindex++;
			}
			
			$smarty->assign("proxies", $smartyproxies);
		}
	}

	global $protectReservedRequests, $defaultRequestStateKey;
	
	$smarty->assign("isprotected", isProtected($request->getId()));
    
	$type = $request->getStatus();
	$checksum = $request->getChecksum();
	$pendid = $request->getId();
	$smarty->assign("type", $type);
	$smarty->assign("defaultstate", $defaultRequestStateKey);
	$smarty->assign("requeststates", $availableRequestStates);
	
	$cmtlen = strlen(trim($request->getComment()));
	$request_comment = "";
	if ($cmtlen != 0) {
		$request_comment = $request->getComment();
	}

	global $tsurl;
	
	$request_date = $request->getDate();
	

	
	$legacyRequest = new accRequest();
	$smarty->assign("isblacklisted", false);
    $blacklistresult = $legacyRequest->isblacklisted($sUser);
	if($blacklistresult)
    {
		$smarty->assign("isblacklisted", true);
		$smarty->assign("blacklistregex", $blacklistresult);
        
    }
	$out2 = "<h2>Possibly conflicting usernames</h2>\n";
	$spoofs = getSpoofs( $sUser );
	
	$smarty->assign("spoofs", $spoofs);
	
	// START LOG DISPLAY
	$loggerclass = new LogPage();
	$loggerclass->filterRequest=$gid;
	$logs = $loggerclass->getRequestLogs();
	
	if ($session->hasright($_SESSION['user'], 'Admin')) {
		$query = "SELECT * FROM acc_cmt JOIN acc_user ON (user_name = cmt_user) WHERE pend_id = '$gid' ORDER BY cmt_id ASC;";
	} else {
		$user = sanitise($_SESSION['user']);
		$query = "SELECT * FROM acc_cmt JOIN acc_user ON (user_name = cmt_user) WHERE pend_id = '$gid' AND (cmt_visability = 'user' OR cmt_user = '$user') ORDER BY cmt_id ASC;";
	}
	$result = mysql_query($query, $tsSQLlink);
	
	if (!$result) {
		Die("Query failed: $query ERROR: " . mysql_error());
	}
	
	while ($row = mysql_fetch_assoc($result)) {
		$logs[] = array('time'=> $row['cmt_time'], 'user'=>$row['cmt_user'], 'description' => '', 'target' => 0, 'comment' => $row['cmt_comment'], 'action' => "comment", 'security' => $row['cmt_visability'], 'id' => $row['cmt_id']);
	}
	
	if($request_comment !== ""){
		$logs[] = array(
			'time'=> $request->getDate(), 
			'user'=>$sUser, 
			'description' => '',
			'target' => 0, 
			'comment' => $request_comment, 
			'action' => "comment", 
			'security' => ''
			);
	}
	
	
	$namecache = array();
	
	if ($logs) {
		$logs = doSort($logs);
		foreach ($logs as &$row) {
			$row['canedit'] = false;
			if(!isset($row['security'])) {
				$row['security'] = '';
			}
			if(!isset($namecache[$row['user']]))
				$row['userid'] = getUserIdFromName($row['user']);
			else
				$row['userid'] = $namecache[($row['user'])];
			
			if($row['action'] == "comment"){
				$row['entry'] = xss($row['comment']);
                
				global $enableCommentEditing;
				if($enableCommentEditing && ($session->hasright($_SESSION['user'], 'Admin') || $_SESSION['user'] == $row['user']) && isset($row['id']))
					$row['canedit'] = true;
			} elseif($row['action'] == "Closed custom-n" ||$row['action'] == "Closed custom-y"  ) {
				$row['entry'] = "<em>" .$row['description'] . "</em><br />" . str_replace("\n", '<br />', xss($row['comment']));
			} else {
				foreach($availableRequestStates as $deferState)
					$row['entry'] = "<em>" . str_replace("deferred to ".$deferState['defertolog'],"deferred to ".$deferState['deferto'],$row['description']) . "</em>"; //#35: The log text(defertolog) should not be displayed to the user, deferto is what should be displayed
			}
		}
		unset($row);
	}
	$smarty->assign("zoomlogs", $logs);


	// START OTHER REQUESTS BY IP AND EMAIL STUFF
	
	// Displays other requests from this ip.
	$smarty->assign("otherip", false);
	$smarty->assign("numip", 0);
    
    // assign to user
	$userListQuery = "SELECT username FROM user WHERE status = 'User' or status = 'Admin';";
	$userListResult = gGetDb()->query($userListQuery);
    $userListData = $userListResult->fetchAll(PDO::FETCH_COLUMN);
    $userListProcessedData = array();
    foreach ($userListData as $userListItem)
    {
        $userListProcessedData[] = "\"" . htmlentities($userListItem) . "\"";
    }
    
	$userList = '[' . implode(",", $userListProcessedData) . ']';	
    $smarty->assign("jsuserlist", $userList);
    // end: assign to user
    
	$ipmsg = 'this ip';
	if ($hideinfo == FALSE || $session->hasright($_SESSION['user'], 'Admin') || $session->isCheckuser($_SESSION['user']))
        $ipmsg = $thisip;


	if ($thisip != '127.0.0.1') {
		$query = "SELECT pend_date, pend_id, pend_name FROM acc_pend WHERE (pend_proxyip LIKE '%{$thisip}%' OR pend_ip = '$thisip') AND pend_id != '$thisid' AND (pend_mailconfirm = 'Confirmed' OR pend_mailconfirm = '');";
		$result = mysql_query($query, $tsSQLlink);
		if (!$result)
            Die("Query failed: $query ERROR: " . mysql_error());

		if (mysql_num_rows($result) != 0) {
			mysql_data_seek($result, 0);
		}
		$smarty->assign("numip", mysql_num_rows($result));
		$otherip = array();
		$i = 0;
		while ($row = mysql_fetch_assoc($result)) {
			$otherip[$i]['date'] = $row['pend_date'];
			$otherip[$i]['id'] = $row['pend_id'];
			$otherip[$i]['name'] = $row['pend_name'];
			$i++;
		}
		$smarty->assign("otherip", $otherip);
	}

	// Displays other requests from this email.
	$smarty->assign("otheremail", false);
	$smarty->assign("numemail", 0);
	
	if ($thisemail != 'acc@toolserver.org') {
		$query = "SELECT pend_date, pend_id, pend_name FROM acc_pend WHERE pend_email = '" . mysql_real_escape_string($thisemail, $tsSQLlink) . "' AND pend_id != '$thisid' AND pend_id != '$thisid' AND (pend_mailconfirm = 'Confirmed' OR pend_mailconfirm = '');";
		$result = mysql_query($query, $tsSQLlink);
		if (!$result)
            Die("Query failed: $query ERROR: " . mysql_error());

		if (mysql_num_rows($result) != 0) {
			mysql_data_seek($result, 0);
		}
		$smarty->assign("numemail", mysql_num_rows($result));
		$otheremail = array();
		$i = 0;
		while ($row = mysql_fetch_assoc($result)) {
			$otheremail[$i]['date'] = $row['pend_date'];
			$otheremail[$i]['id'] = $row['pend_id'];
			$otheremail[$i]['name'] = $row['pend_name'];
			$i++;
		}
		$smarty->assign("otheremail", $otheremail);
	}
	
	// Exclude the "Created" reason from this since it should be outside the dropdown.
    $query = "SELECT id, name, jsquestion FROM emailtemplate ";
	$query .= "WHERE oncreated = '1' AND active = '1' AND id != $createdid";
	$result = mysql_query($query, $tsSQLlink);
	if (!$result)
		sqlerror("Query failed: $query ERROR: " . mysql_error());
	$createreasons = array();
	while ($row = mysql_fetch_assoc($result)) {
		$createreasons[$row['id']]['name'] = $row['name'];
		$createreasons[$row['id']]['question'] = $row['jsquestion'];
	}
	$smarty->assign("createreasons", $createreasons);
	
	$query = "SELECT id, name, jsquestion FROM emailtemplate ";
	$query .= "WHERE oncreated = '0' AND active = '1'";
	$result = mysql_query($query, $tsSQLlink);
	if (!$result)
		sqlerror("Query failed: $query ERROR: " . mysql_error());
	$declinereasons = array();
	while ($row = mysql_fetch_assoc($result)) {
		$declinereasons[$row['id']]['name'] = $row['name'];
		$declinereasons[$row['id']]['question'] = $row['jsquestion'];
	}
	$smarty->assign("declinereasons", $declinereasons);
	
	return $smarty->fetch("request-zoom.tpl");
}
