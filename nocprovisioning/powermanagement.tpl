{*
 * NOC-PS WHCMS module
 * Power management template
 *
 * Copyright (C) Maxnet 2010
 *
 * You are free to modify this module to fit your needs
 * However be aware that you need a NOC-PS license to actually provision servers
 *}

<h2>Power management</h2>

{if $result}
<p>
<b>Result of last action:</b> {$result|escape}
</p>
{/if}

<form name="power" method="post" action="{$smarty.server.PHP_SELF}?action=productdetails" onsubmit="this.performbutton.disabled=true;">
  <input type="hidden" name="id" value="{$serviceid|escape}" />
  <input type="hidden" name="nps_nonce" value="{$nonce}" />
  <input type="hidden" name="modop" value="custom" />
  <input type="hidden" name="a" value="power" />

  <table width="100%" cellspacing="0" cellpadding="0" class="frame">
    <tr>
      <td><table width="100%" cellpadding="10" cellspacing="0">

          <tr>
            <td width="150" class="fieldarea">Main server IP-address</td>
            <td>{$ip}</td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Power status</td>
            <td>{$status|escape}</td>
          </tr>
		  
{if $ask_ipmi_password}
          <tr>
            <td width="150" class="fieldarea">Your server's IPMI password</td>
            <td><input type="password" name="ipmipassword" style="width: 350px;"></td>
          </tr>
{/if}		  

{if $supportsOn || $supportsOff || $supportsReset || $supportsCycle || $supportsCtrlAltDel}
          <tr>
            <td width="150" class="fieldarea">Power action</td>
            <td>
{if $supportsOn}			  
			  <input type="radio" name="poweraction" value="on"> Power on<br>
{/if}{if $supportsOff}			  
			  <input type="radio" name="poweraction" value="off"> Power off<br>
{/if}{if $supportsReset}			  
			  <input type="radio" name="poweraction" value="reset" checked="true"> Reset<br>
{/if}{if $supportsCycle}			  
			  <input type="radio" name="poweraction" value="cycle" {if !$supportsReset}checked="true"{/if}> Cycle power<br>
{/if}{if $supportsCtrlAltDel}
			  <input type="radio" name="poweraction" value="ctrlaltdel"> Send CTRL-ALT-DEL<br>
{/if}
			</td>
          </tr>

		  <tr>
			<td>&nbsp;
			<td><input type="submit" name="performbutton" value="Perform action">
		  </tr>
{/if}

		</table></td>
	</tr>
  </table>
</form>
