{*
 * NOC-PS WHCMS module
 * Provisioning status template
 *
 * Copyright (C) Maxnet 2010
 *
 * You are free to modify this module to fit your needs
 * However be aware that you need a NOC-PS license to actually provision servers
 *}

<h2>Provisioning status</h2>

<p>
Your server is currently being provisioned... Be aware that this could take 10+ minutes to complete...
</p>

<form name="prov" method="post" action="{$smarty.server.PHP_SELF}?action=productdetails">
  <input type="hidden" name="id" value="{$serviceid|escape}" />
  <input type="hidden" name="nps_nonce" value="{$nonce}" />
  <input type="hidden" name="modop" value="custom" />
  <input type="hidden" name="a" value="provision" />

  <table width="100%" cellspacing="0" cellpadding="0" class="frame">
    <tr>
      <td><table width="100%" cellpadding="10" cellspacing="0">

          <tr>
            <td width="150" class="fieldarea">MAC-address</td>
            <td>{$mac}</td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">IP-address</td>
            <td>{$ip}</td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Hostname</td>
            <td>{$status.hostname|escape}</td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Installation profile</td>
            <td>{$status.profilename|escape}</td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Last status message</td>
            <td>{$status.statusmsg|escape}</td>
          </tr>

		  <tr>
			<td>&nbsp;
			<td><input type="submit" value="Update status">&nbsp;&nbsp;<input type="submit" name="cancelprovisioning" value="Cancel provisioning">
		  </tr>

		</table></td>
	</tr>
  </table>
</form>
