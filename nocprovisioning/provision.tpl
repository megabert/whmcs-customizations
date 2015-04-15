{*
 * NOC-PS WHCMS module
 * Provisioning form template
 *
 * Copyright (C) Maxnet 2010
 *
 * You are free to modify this module to fit your needs
 * However be aware that you need a NOC-PS license to actually provision servers
 *}

<script type="text/javascript">

{* Function to fill in the 'disklayout', 'package selection' and 'extra' combos depending on profile *}
function onProfileChange()
{ldelim}
  var a = {$addons_json};
  var p = {$profiles_json};
{literal}
  var f = document.forms.prov;
  var pid = f.profile.value;
  var pr = false;
  
  for (var k=0; k<p.length; k++)
  {
	pr = p[k];
	if (pr.id == pid)
	{
	  break;
	}
  }
    
  var t = pr.tags;
  var tags;
  if (t)
  {
	  tags = t.split(' ');
  }
  else
  {
	  tags = [];
  }
  
  var packages    = [['','Standard']];
  var disklayouts = [['','Standard']];
  var extras	  = [['','None']];
  var totalAddons = a.length;
  
  for (var i=0; i <totalAddons; i++)
  {
	  pr = a[i];
	  t  = pr.tag;
	  
	  for (var j=0; j < tags.length; j++)
	  {
		  if (t == tags[j])
		  {
			  var typ   = pr.type;
			  var item  = [t+':'+pr.name, pr.descr];
			  
			  if (typ == 'packages')
			  {
				  packages.push(item);
			  }
			  else if (typ == 'disklayout')
			  {
				  disklayouts.push(item);
			  }
			  else
			  {
				  extras.push(item);
			  }
				  
			  break;
		  }
	  }				
  }
  
  array2options(f.disklayout, disklayouts);
  array2options(f.packageselection, packages);
  array2options(f.extra1, extras);
  array2options(f.extra2, extras);

  f.disklayout.disabled = (disklayouts.length == 1);
  f.packageselection.disabled = (packages.length == 1);
  f.extra1.disabled = (extras.length == 1);
  f.extra2.disabled = (extras.length < 3);
}

function array2options(sel, arr)
{
  var opt = sel.options;
  opt.length = 0;

  for (var i=0; i<arr.length; i++)
  {
	opt[opt.length] = new Option(arr[i][1], arr[i][0]);
  }
}

{/literal}
</script>

<h2>Provision server</h2>

{if $error}
<p>
  <b>Error:</b> {$error}
</p>
{/if}

<form name="prov" method="post" action="{$smarty.server.PHP_SELF}?action=productdetails" onsubmit="this.provbutton.disabled=true;">
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
		  
{if $ask_ipmi_password}
          <tr>
            <td width="150" class="fieldarea">Your server's IPMI password</td>
            <td><input type="password" name="ipmipassword" style="width: 350px;"></td>
          </tr>
{/if}

          <tr>
            <td width="150" class="fieldarea">Hostname</td>
            <td><input type="text" name="hostname" style="width: 350px;"></td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Installation profile</td>
            <td><select name="profile" style="width: 350px;" onchange="onProfileChange();">
				{foreach item=profile from=$profiles}
			      <option value="{$profile.id}"{if $defaultProfile == $profile.id} selected{/if}>{$profile.name|escape}</option>
				{/foreach}
			</select></td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Disk layout</td>
            <td><select name="disklayout" style="width: 350px;">
			</select></td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Package selection</td>
            <td><select name="packageselection" style="width: 350px;">
			</select></td>
          </tr>
		  
          <tr>
            <td width="150" class="fieldarea">Extras</td>
            <td>
			  <select name="extra1" style="width: 350px;"></select><br><br>
			  <select name="extra2" style="width: 350px;"></select>
			</td>
          </tr>
		  
          <tr>
            <td width="150" class="fieldarea">Root user password</td>
            <td><input type="password" name="rootpassword" style="width: 350px;"></td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Repeat root user password</td>
            <td><input type="password" name="rootpassword2" style="width: 350px;"></td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Regular user name (optional)</td>
            <td><input type="text" name="adminuser" value="charlie" style="width: 350px;"></td>
          </tr>
		  
          <tr>
            <td width="150" class="fieldarea">User password (optional)</td>
            <td><input type="password" name="userpassword" style="width: 350px;"></td>
          </tr>

          <tr>
            <td width="150" class="fieldarea">Repeat user password (optional)</td>
            <td><input type="password" name="userpassword2" style="width: 350px;"></td>
          </tr>
		  
		  <tr>
			<td>&nbsp;
			<td><input type="submit" name="provbutton" value="Provision server (WARNING: overwrites data on disk)" onclick="return confirm('This will delete all existing data on disk. Are you sure?');">
		  </tr>

		</table></td>
	</tr>
  </table>
</form>

<script type="text/javascript">
{* Load comboboxes with information of default profile *}
onProfileChange();
</script>
