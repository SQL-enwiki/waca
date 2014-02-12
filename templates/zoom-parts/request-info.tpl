﻿<!-- tpl:zoom-parts/request-info.tpl -->
<div class="row-fluid visible-phone">
    <div class="span12">
        <h3>Request data:</h3>
        <table class="table table-condensed table-striped">
        <tbody>
            {if $showinfo}
            <tr>
                <th>Email address:</th>
                <td><a href="mailto:{$request->getEmail()}">{$request->getEmail()}</a></td>
                <td><span class="badge{if count($request->getRelatedEmailRequests()) > 0} badge-important{/if}">{count($request->getRelatedEmailRequests())}</span></td>
            </tr>
            <tr>
                <th>IP address:</th>
                <td>
                  {$request->getTrustedIp()}
                  <br />
                  <span class="muted">
                    {if $iplocation != null}
                      Location: {$iplocation.cityName}, {$iplocation.regionName}, {$iplocation.countryName}
                    {else}
                      <em>Location unavailable</em>
                    {/if}
                  </span>
                </td>
                <td>
                    {if $proxyip != NULL}<span class="label label-info">XFF</span>{/if}
                    <span class="badge{if count($request->getRelatedIpRequests()) > 0} badge-important{/if}">{count($request->getRelatedIpRequests())}</span>
                </td>
            </tr>
            {/if}
            <tr><th>Requested name:</th><td>{$request->getName()}</td><td></td></tr>
            <tr><th>Date:</th><td>{$request->getDate()}</td><td></td></tr>
            {if $currentUser->isCheckUser()}<tr><th>User Agent:</th><td>{$request->getUserAgent()}</td><td></td></tr>{/if}
            {if $currentUser->getId() == $request->getReserved()}
            <tr>
                <th>Reveal link:</th>
                <td>
                <a href="{$tsurl}/acc.php?action=zoom&amp;id={$request->getId()}&amp;hash={$hash}">
                  Reveal link
                </a></td>
                <td></td>
            </tr>
            {/if}
            <tr><th>Reserved by:</th><td>{if $request->getReserved() != 0}{$request->getReservedObject()->getUsername()}{else}None{/if}</td>
            <td></td></tr>
        </tbody>
    </table>
  </div>
</div>
{if $showinfo}
  <div class="row-fluid hidden-phone">
    <div class="span4"><strong>Email address:</strong></div>
    <div class="span7"><a href="mailto:{$request->getEmail()}">{$request->getEmail()}</a></div>
    <div class="span1"><span class="badge{if count($request->getRelatedEmailRequests()) > 0} badge-important{/if}">{count($request->getRelatedEmailRequests())}</span></div>
  </div>
  <div class="row-fluid hidden-phone">
      <div class="span4"><strong>IP address:</strong></div>
      <div class="span7">
        {$request->getTrustedIp()}
        <br />
        <span class="muted">
          {if $iplocation != null}
            Location: {$iplocation.cityName}, {$iplocation.regionName}, {$iplocation.countryName}
          {else}
            <em>Location unavailable</em>
          {/if}
        </span>
      </div>
      <div class="span1"><span class="label label-info">XFF</span><span class="badge{if count($request->getRelatedIpRequests()) > 0} badge-important{/if}">{count($request->getRelatedIpRequests())}</span></div>
  </div>
{/if}
<div class="row-fluid hidden-phone">
    <div class="span4"><strong>Requested name:</strong></div>
    <div class="span8">{$request->getName()}</div>
</div>
<div class="row-fluid hidden-phone">
    <div class="span4"><strong>Date:</strong></div>
    <div class="span8">{$request->getDate()}</div>
</div>
{if $currentUser->isCheckUser()}
  <div class="row-fluid hidden-phone">
    <div class="span4"><strong>User Agent:</strong></div>
    <div class="span8">{$request->getUserAgent()}</div>
  </div>
{/if}
<div class="row-fluid hidden-phone">
  <div class="span4"><strong>Reserved by:</strong></div>
  <div class="span8">
    {if $request->getReserved() != 0}
      {$request->getReservedObject()->getUsername()}
      {if $request->getReserved() == $currentUser->getId()}
        <a href="{$tsurl}/acc.php?action=zoom&amp;id={$request->getId()}&amp;hash={$hash}">(reveal to others)</a>
      {/if}
    {else}
      None
    {/if}
   </div>
</div>
<!-- /tpl:zoom-parts/request-info.tpl -->