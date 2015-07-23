<?php
/**
 * NOC-PS WHCMS module
 *
 * Copyright (C) Maxnet 2010
 *
 * You are free to modify this module to fit your needs
 * However be aware that you need a NOC-PS license to actually provision servers
 */

$nocps_password = "TheNocPSPassword";

require_once ("include/log.php");
require_once ("include/whmcs.php");
require_once ("include/citrix.php");
require_once ("include/validate.php");

/* Configurable options */
define('NOCPS_CONFIG_ENABLE_PROVISIONING', 'configoption1');
define('NOCPS_CONFIG_ENABLE_POWERMANAGEMENT', 'configoption2');
define('NOCPS_CONFIG_ENABLE_BANDWIDTHGRAPHS', 'configoption3');
define('NOCPS_CONFIG_BANDWIDTHPERIOD', 'configoption4');
define('NOCPS_CONFIG_BANDWIDTHUNIT', 'configoption5');
define('NOCPS_CONFIG_WHITELIST', 'configoption7');
define('NOCPS_CONFIG_BLACKLIST', 'configoption8');
define('NOCPS_CONFIG_REQUIRE_IPMIPASSWORD', 'configoption9');

/**
 * Singleton function for creating a NOC-PS API object
 *
 * @param struct $params The WHMCS parameters passed to the module
 * @return nocps_api API object
 */
function nocprovisioning_api($params = false)
{
	static $api = false;
	
	if (!$api)
	{
		require_once( dirname(__FILE__).'/include/nocps_api.php' );
		
		if ( is_array($params) && !empty($params['serverip']))
		{ 
			$api = new nocps_api($params['serverip'], $params['serverusername'], $params['serverpassword']);
		}
		else
		{
			/*
			 * seems that if we are associated with a "dedicated server" product instead of a "shared hosting" product,
			 * WHMCS does not link us to the NOC-PS server.
			 * as an ugly workaround: fetch the login information straight from the database
			 */
			
			/* Check if our package prefers a certain server group */
			$servergroup = 0;
			if ( is_array($params) && !empty($params['serviceid']) )
			{ 
				$q = mysql_query("SELECT servergroup FROM tblproducts p, tblhosting h WHERE p.id=h.packageid AND h.id=".intval($params['serviceid']));
				$servergroup = mysql_result($q, 0); 
			}
			
			if ( $servergroup )
			{
				$q = mysql_query("SELECT ipaddress,username,password FROM tblservers s, tblservergroupsrel g WHERE type='nocprovisioning' AND active=1 AND s.id=g.serverid AND g.groupid=".intval($servergroup)." ORDER BY id LIMIT 1");
			}
			else
			{
				/* If all else fails just grab the first (and hopefully only) NOC-PS server */
				$q = mysql_query("SELECT ipaddress,username,password FROM tblservers WHERE type='nocprovisioning' AND active=1 ORDER BY id LIMIT 1");
			}
			$d = mysql_fetch_assoc($q);
			if (!$d)
				throw new Exception("No NOC-PS server configured. Go to 'Setup' -&gt; 'Servers");
			
			if ( !empty($_SESSION['adminid']))
				$loguser = 'WHMCS admin '.$_SESSION['adminid'];
			else if ( !empty($_SESSION['uid']))
				$loguser = 'WHMCS client '.$_SESSION['uid'];
			else
				$loguser = '';
			
			$api = new nocps_api($d['ipaddress'], $d['username'], decrypt($d['password']), $loguser);
		}
	}
	
	return $api;
}

/**
 * Get dedicated server IP-address from database
 *
 * @param  int $serviceid ID of order
 * @return string IP-address of order
 */
function nocprovisioning_getServerIP($serviceid)
{
	$q = mysql_query("SELECT dedicatedip,domainstatus FROM tblhosting WHERE id=".intval($serviceid)." AND userid=".intval($_SESSION['uid']));
	$d = mysql_fetch_assoc($q);
	if (!$d)
		throw new Exception("Cannot find order ".intval($serviceid)." of user ".intval($_SESSION['uid']));
	if ($d['domainstatus'] != 'Active')
		throw new Exception("Server order does not have status 'active'");
	if (!$d['dedicatedip'])
		throw new Exception("No dedicated IP-address assigned");
	
	return $d['dedicatedip'];
}

function nocprovisioning_getServerIPblub($serviceid)
{
	$q = mysql_query("SELECT dedicatedip, domainstatus FROM tblhosting WHERE id=".$serviceid);
	$d = mysql_fetch_assoc($q);
	
	return $d['dedicatedip'];
}


/**
 * Get dedicated server MAC-address from NOC-PS database
 *
 * @param  int $serviceid ID of order
 * @param struct $params The WHMCS parameters passed to the module
 * @return string MAC-address
 */
function nocprovisioning_getServerMAC($serviceid, $params = false)
{
	$ip  = nocprovisioning_getServerIP($serviceid);
	$api = nocprovisioning_api($params);
	$mac = $api->getServerByIP($ip);
	
	if (!$mac)
		throw new Exception("Server IP $ip is not registered in the NOC-PS system");
		
	return $mac;
}

/**
 * Returns product setup options
 *
 * @return array
 */
function nocprovisioning_ConfigOptions() {
	
	global $CONFIG;

	/* Test if we have a recent WHMCS version */
	$versionparts = explode('.', $CONFIG['Version']);
	if ($versionparts[0] < 4 || ($versionparts[0] == 4 && $versionparts[1] < 2) )
		return array( "<b>Error: </b>Your version of WHMCS will not work with this module. Upgrade to at least 4.2" => array("Type" => "html") );

	/* Test if we have a working connection, so we can alert the admin here if not */
	try
	{
		$api = nocprovisioning_api();
		$profiles = $api->getProfileNames(0, 1000);
	}
	catch (Exception $e)
	{
		return array( "<b>Error communicating with NOC-PS server (on port 443): </b> ".$e->getMessage() => array("Type" => "html") );
	}
	
	$options = array();
	$options["Enable provisioning of servers"] = array( "Type" => "yesno" );
	$options["Enable power management"] = array( "Type" => "yesno" );
	$options["Enable bandwidth graphs"] = array( "Type" => "yesno" );
	$options["Bandwidth graph period"]  = array( "Type" => "dropdown", "Options" => "Calendar month");
	$options["Bandwidth billing unit"]  = array( "Type" => "dropdown", "Options" => "MB,GB,Mbit/s" );
	

	$h  = '<p align="center"><b>Profiles</b></p>';
	$h .= '<table border>';
	$h .= '<tr><th>ID</th><th>Profile name</th><th>Tags</th>';
	foreach ($profiles['data'] as $profile)
	{
		$h .= '<tr><td>'.$profile['id'].'</td><td>'.htmlentities($profile['name']).'</td><td>'.htmlentities($profile['tags']).'</td></tr>';
	}
	$h .= '</table>';
	$h .= '<p>You can restrict the profiles your customer may install by entering the numeric profile ID number or one of the profile TAGS.<p>E.g. to only allow installation of Linux profiles, enter tagname &quot;linux&quot; in the whitelist field.<br>To enter multiple profiles/tags separate them by spaces, e.g. &quot;1 2 4 windows&quot;';

	$options["</td><tr><td colspan=4>$h</td></tr>"] = array("Type" => "html");
	$options["Profile WHITElist (empty = allow all)"]  = array( "Type" => "text");
	$options["Profile BLACKlist"]  = array( "Type" => "text");
	$options["Ask customer for server's IPMI password (more secure)"] = array("Type" => "yesno");
	
	return $options;
}

/**
 * Dummy account creation function
 */
function nocprovisioning_CreateAccount($params)
{
	$domain_validated = validate_dns_domainname($params["domain"]);
	if ( !$domain_validated ) {
		return("Error, Invalid Hostname");	
	}

	global $loglevel, $LOG;
	$loglevel=$LOG["DEBUG"];
        $q =  mysql_query("SELECT packageid FROM tblhosting WHERE id=".intval($params['serviceid']));
        $packageid = mysql_result($q, 0);
        $q =  mysql_query("SELECT id FROM tblproducts WHERE id=".$packageid);
        $productid = mysql_result($q, 0);
        $myFile = "/tmp/testFile.txt";
        $fh = fopen($myFile, 'a');

	$product    = get_whmcs_nocps_servertype($productid);
	$is_virtual = whmcs_host_is_virtual     ($productid);
	lg_info("Setting up $product. is_virtual: $is_virtual");

	if       ( !$is_virtual ) { 
        	 $n_serverrequest = "php /var/www/myscript.php getserver ".$product;
        	 $newserverip_req = exec($n_serverrequest);

	} elseif (  $is_virtual ) {

		$ram_amount = get_ram_amount_for_product($productid);
		lg_info("Reqd RAM $ram_amount");
		$disk_space = get_disk_space_for_product($productid);
		lg_info("Reqd Disk space $disk_space");
		$vcore_count = get_vcore_count_for_product($productid);

		$description = 	whmcs_get_customer_name($params["userid"]). " ClientID ".$params["userid"]
				. " ". get_whmcs_nocps_servertype($productid);
		lg_info("Description $description");

		$newserverip_req = nocps_define_citrix_vm(nocprovisioning_api(),$params["domain"],$description,$ram_amount,$disk_space,$vcore_count);

		// 1) pruefen ob Rersourcen auf einem NOC-PS Citrix-XenServer frei sind
		// 2) VM auf dem Citrix XenServer anlegen
	}
	lg_debug("Got Back from VM-Create: $newserverip_req");

        if ($newserverip_req == "NoServers")
        {
                return "Error, no servers available";
        } elseif 
           (strtolower(substr($newserverip_req,0,5)) == "error") {
                return $newserverip_req;
	}
        else
        {
                $newserverip_req = str_replace("NEWIP ", "", $newserverip_req);
                $myquer = "UPDATE tblhosting set dedicatedip=\"".$newserverip_req."\", domainstatus=\"Active\"  WHERE id=".intval($params['serviceid']);
                $q = mysql_query($myquer);
                $stringData = $myquer."\n";
                fwrite($fh, $stringData);
                $q  = mysql_query("SELECT orderid FROM tblhosting WHERE id=".intval($params['serviceid']));
                $orderid = mysql_result($q, 0);
                $upd_query = "UPDATE tblorders SET status=\"Active\" WHERE id=\"".$orderid."\";";
                $stringData = $upd_query."\n";
                fwrite($fh, $stringData);
                $q  = mysql_query($upd_query);

        }

        $q  = mysql_query("SELECT dedicatedip FROM tblhosting WHERE id=".intval($params['serviceid']));
        $ip = mysql_result($q, 0);
        fclose($fh);

        if ($ip)
                return "success";
        else
                return "Keine freien Server verfuegbar!";


}

function nocprovisioning_TerminateAccount($params)
{
	global $nocps_password;
        $ip  = nocprovisioning_getServerIPblub($params['serviceid']);
        $api = nocprovisioning_api($params);
        $mac = $api->getServerByIP($ip);

        $result = "";
        $password = "$nocps_password";
        $method = "auto";
	try {
        	$result = $api->powercontrol($mac, 'off', $password, $method);
        	nocprovisioning_log("Power management action 'off' - $result - MAC $mac", $params);
	} 
	catch (exception $e) {
	lg_err("PHP Crashed while powering off server: $ip / $mac");
	}
	$is_virtual = whmcs_host_is_virtual($params["packageid"]);

	if($is_virtual) {
		sleep(10);
		lg_info("Deleting VM $ip / $mac");
		try {
			$api->deleteVM($mac);
		} 
		catch (exception $e) {
			lg_err("PHP Crashed while deleting vm: $ip / $mac");
		}
	}

	$q =  mysql_query("UPDATE tblhosting set dedicatedip=\"\", assignedips=\"\", domainstatus=\"Terminated\"  WHERE id=".intval($params['serviceid']));
        $query = mysql_result($q);

	return "success";
}



function nocprovisioning_SuspendAccount($params)
{
	global $nocps_password;
        $q =  mysql_query("UPDATE tblhosting set domainstatus=\"Suspended\" WHERE id=\"".$params['serviceid']."\"");
        $query = mysql_result($q);
        $ip  = nocprovisioning_getServerIPblub($params['serviceid']);
        $api = nocprovisioning_api($params);
        $mac = $api->getServerByIP($ip);

        $result = "";
        $password = "$nocps_password";
        $method = "auto";
	try {
		$result = $api->powercontrol($mac, 'off', $password, $method);
		lg_info("Server $ip suspended(powered off)");
		nocprovisioning_log("Power management action 'off' - $result - MAC $mac", $params);
	} catch (Exception $e) {
		return "Error while powering off: ".$e->getMessage();
	}

	return "success";
}

function nocprovisioning_UnsuspendAccount($params)
{
	global $nocps_password;
        $q =  mysql_query("UPDATE tblhosting set domainstatus=\"Active\" WHERE id=\"".$params['serviceid']."\"");
        $query = mysql_result($q);
        $ip  = nocprovisioning_getServerIPblub($params['serviceid']);
        $api = nocprovisioning_api($params);
        $mac = $api->getServerByIP($ip);

        $result = "";
        $password = "$nocps_password";
        $method = "auto";
        $result = $api->powercontrol($mac, 'on', $password, $method);
		lg_info("Server $ip unsuspended(powered on)");
        nocprovisioning_log("Power management action 'on' - $result - MAC $mac", $params);

	return "success";
}




/**
 * Client area addition
 *
 * @return string HTML data
 */
function nocprovisioning_ClientArea($params)
{
	/* anti-XSS */
	if ( empty($_SESSION['nps_nonce'] ))
		$_SESSION['nps_nonce'] = uniqid().mt_rand();

	$q = mysql_query("SELECT dedicatedip FROM tblhosting WHERE id=".intval($params["serviceid"])." AND userid=".intval($_SESSION['uid']));
	$ip = mysql_result($q, 0);
	
	$code = '
	<table cellpadding=10><tr><td>';
	if ($ip && $params[NOCPS_CONFIG_ENABLE_BANDWIDTHGRAPHS] == 'on')
	{
		$code .= '
		<form action="clientarea.php?action=productdetails" method="post">
			<input type="hidden" name="id" value="'.$params["serviceid"].'" />
			<input type="hidden" name="nps_nonce" value="'.$_SESSION['nps_nonce'].'" />
			<input type="hidden" name="modop" value="custom" />
			<input type="hidden" name="a" value="datatraffic" />
			<input type="submit" value="Data traffic" />	
		</form>
		</td>';
	}
	
	if ($ip && $params[NOCPS_CONFIG_ENABLE_POWERMANAGEMENT] == 'on')
	{
		$code .= '
			<td><form action="clientarea.php?action=productdetails" method="post">
				<input type="hidden" name="id" value="'.$params["serviceid"].'" />
				<input type="hidden" name="nps_nonce" value="'.$_SESSION['nps_nonce'].'" />
				<input type="hidden" name="modop" value="custom" />
				<input type="hidden" name="a" value="power" />
				<input type="submit" value="Power management" />	
			</form>
		</td>';
	}
	
	if ($ip && $params[NOCPS_CONFIG_ENABLE_PROVISIONING] == 'on')
	{
		$code .= '<td>
			<form action="clientarea.php?action=productdetails" method="post">
				<input type="hidden" name="id" value="'.$params["serviceid"].'" />
				<input type="hidden" name="nps_nonce" value="'.$_SESSION['nps_nonce'].'" />
				<input type="hidden" name="modop" value="custom" />
				<input type="hidden" name="a" value="provision" />
				<input type="submit" value="(Re)install server" />
			</form>
		</td>';
	}

	if ($ip && whmcs_host_is_virtual($params["packageid"]))
	// if ($ip && $_SERVER["REMOTE_ADDR"]=="1.2.3.4" && whmcs_host_is_virtual($params["packageid"]))
	{
		$code .= '<td> 
			<form action="/startconsole.php" taget="_blank"  method="post">
				<input type="hidden" name="serviceid" value="'.$params["serviceid"].'" />
				<input type="hidden" name="domain" value="'.$params["domain"].'" />
				<input type="hidden" name="ip" value="'.$ip.'" />
				<input type="hidden" name="a" value="console" />
				<input type="submit" value="Server Console" />
			</form>
		</td>';
	}
	
	$code .= '</tr></table>'; 

	return $code;
}

/**
 * Button mapping
 *
 * @return array
 */
function nocprovisioning_ClientAreaCustomButtonArray() {
	return array(
		"Power" => "power",
		"Provision" => "provision",
		"Datatraffic" => "datatraffic"
//		,"Graph" => "graph"
	);
}

/**
 * Show power management page
 */
function nocprovisioning_power($params)
{
	try
	{
		/* against XSS attacks */
		if ( $_SERVER['REQUEST_METHOD'] == "POST" && $_POST['nps_nonce'] != $_SESSION['nps_nonce'] )
			throw new Exception('Nonce value does not match');
			
		if ($params[NOCPS_CONFIG_ENABLE_POWERMANAGEMENT] != 'on')
			throw new Exception('Not allowed');
		
		$api = nocprovisioning_api($params);
		$ip  = nocprovisioning_getServerIP($params["serviceid"]);
		$mac = nocprovisioning_getServerMAC($params["serviceid"]);
		$result = "";
		$password = "";
		$method = "auto";
		
		if ( !empty($_POST['ipmipassword'] ))
		{
			$password = $_POST['ipmipassword'];
			$method   = 'ipmi';
		}
		
		if ( !empty($_POST['poweraction'] ))
		{
			if ( !empty($params[NOCPS_CONFIG_REQUIRE_IPMIPASSWORD]) && !$password)
				throw new Exception("Enter your server's IPMI password");
			
			$result = $api->powercontrol($mac, $_POST['poweraction'], $password, $method);
			nocprovisioning_log("Power management action '".$_POST['poweraction']."' - $result - MAC $mac", $params);
		}
		
		if ( !empty($params[NOCPS_CONFIG_REQUIRE_IPMIPASSWORD]) && !$password )
		{
			$status        = 'unknown';
			$actionsString = 'on off reset cycle';
		}
		else
		{
			$status = $api->powercontrol($mac, 'status', $password, $method);
			$actionsString    = $api->powercontrol($mac, 'supportedactions', '', $method);
		}
		
		$supportedActions = explode(' ', $actionsString);
		if (empty($actionsString))
			$status = 'No power management device associated with this server';
		
		return array(
			'templatefile' => 'powermanagement',
			'vars' => array(
				'ip'		=> $ip,
				'serviceid' => $params["serviceid"],
				'nonce'     => $_SESSION['nps_nonce'],
				'result'	=> $result,
				'status'	=> $status,
				'supportsOn'    => in_array('on', $supportedActions),
				'supportsOff'   => in_array('off', $supportedActions),
				'supportsReset' => in_array('reset', $supportedActions),
				'supportsCycle' => in_array('cycle', $supportedActions),
				'supportsCtrlAltDel' => in_array('ctrlaltdel', $supportedActions),
				'ask_ipmi_password' => !empty($params[NOCPS_CONFIG_REQUIRE_IPMIPASSWORD])
			)
		);
	}
	catch (Exception $e)
	{
		nocprovisioning_log("Power management error - ".$e->getMessage(), $params);
		die('<b>Error: </b>'.$e->getMessage() );
	}
}

/**
 * Show server provisioning page
 */
function nocprovisioning_provision($params)
{
	try
	{
		/* against XSS attacks */
		if ( $_SERVER['REQUEST_METHOD'] == "POST" && $_POST['nps_nonce'] != $_SESSION['nps_nonce'] )
			throw new Exception('Nonce value does not match');
			
		if ($params[NOCPS_CONFIG_ENABLE_PROVISIONING] != 'on')
			throw new Exception('Not allowed');		
		
		$api = nocprovisioning_api($params);
		$ip  = nocprovisioning_getServerIP($params["serviceid"]);
		$mac = nocprovisioning_getServerMAC($params["serviceid"]);
		$error = "";
		
		if ( !empty($_POST['profile'] ))
		{
			/* Never trust user input. Double check if profile is not blacklisted */
			$whitelist = array_filter(explode(' ', $params[NOCPS_CONFIG_WHITELIST]));
			$blacklist = array_filter(explode(' ', $params[NOCPS_CONFIG_BLACKLIST]));
			$profile   = $api->getProfile( intval($_POST['profile']) );
			$profileid = intval($_POST['profile']);
			$tags      = explode(' ', $profile['data']['tags']);
			
			if ( count($whitelist) && !in_array($profileid, $whitelist) && count(array_intersect($tags, $whitelist)) == 0 )
			{
				throw new Exception("Profile is not on whitelist");
			}
			else if ( count($blacklist) && ( in_array($profileid, $blacklist) || count(array_intersect($tags, $blacklist)) ))
			{
				throw new Exception("Profile is on blacklist");
			}			
			/* --- */
			
			if ( empty($params[NOCPS_CONFIG_REQUIRE_IPMIPASSWORD]) )
			{
				$rebootmethod = 'auto';
				$ipmipassword = ''; /* use password stored in db */
			}
			else
			{
				if ( empty($_POST['ipmipassword']) )
					throw new Exception("Enter your server's IPMI password");
				
				$rebootmethod = 'ipmi';
				$ipmipassword = $_POST['ipmipassword'];
			}

			/* Provision server */
			lg_debug("password : ".$_POST["rootpassword"]);
			lg_debug("password2: ".$_POST["rootpassword2"]);
			$result = $api->provisionHost(array(
				"mac"           => $mac,
				"hostname"		=> $_POST["hostname"],
				"profile"       => $profileid,
				"rootpassword"  => $_POST["rootpassword"],
				"rootpassword2" => $_POST["rootpassword2"],
				"adminuser"	    => $_POST["adminuser"],
				"userpassword"  => $_POST["userpassword"],
				"userpassword2" => $_POST["userpassword2"],
				"disk_addon"	=> $_POST["disklayout"],
				"packages_addon"=> $_POST["packageselection"],
				"extra_addon1"	=> $_POST["extra1"],
				"extra_addon2"  => $_POST["extra2"],
				"rebootmethod"  => $rebootmethod,
				"ipmipassword"  => $ipmipassword
			));
			
			if ($result['success'])
			{
				$n = $profile['data']['name'];
				if ($_POST['disklayout'])
					$n .= '+'.$_POST['disklayout'];
				if ($_POST['packages_addon'])	
					$n .= '+'.$_POST['packages_addon'];
				if ($_POST['extra1'])
					$n .= '+'.$_POST['extra1'];
				if ($_POST['extra2'])
					$n .= '+'.$_POST['extra2'];
				
				nocprovisioning_log("Provisioning server - Profile '$n' - MAC $mac", $params);
			}
			else
			{
				/* input validation error */
				
				foreach ($result['errors'] as $field => $msg)
				{
					$error .= $field.': '.htmlentities($msg).'<br>';
				}
				
				lg_err("Error trying to provision - ".str_replace("<br>", " - ", $error));
				// nocprovisioning_log("Error trying to provision - ".str_replace("<br>", " - ", $error), $params);
			}
		}
		else if ( !empty($_POST['cancelprovisioning']) )
		{
			/* Cancel provisioning */
			$api->cancelProvisioning($mac);
			nocprovisioning_log("Cancelled provisioning - MAC $mac", $params);
		}

		$status = $api->getProvisioningStatusByServer($mac);
		if ($status)
		{
			/* Host is already being provisioned */

			return array(
				'templatefile' => 'provision-status',
				'vars' => array(
					'ip'		=> $ip,
					'mac'		=> $mac,
					'serviceid' => $params["serviceid"],
					'nonce'     => $_SESSION['nps_nonce'],
					'status'    => $status,
				)
			);
		}
		else
		{
			$profiles = $api->getProfileNames(0, 1000);
			$addons   = $api->getProfileAddonNames(0, 1000);
			
			/* Check profile against white- and blacklist */
			$whitelist = array_filter(explode(' ', $params[NOCPS_CONFIG_WHITELIST]));
			$blacklist = array_filter(explode(' ', $params[NOCPS_CONFIG_BLACKLIST]));
			
			foreach ($profiles['data'] as $k => $profile)
			{
				$tags = explode(' ', $profile['tags']);
				
				/* Check wheter the profile ID or any of its tags are on the whitelist */
				if ( count($whitelist) && !in_array($profile['id'], $whitelist) && count(array_intersect($tags, $whitelist)) == 0 )
				{
					/* not on whitelist, remove */
					unset($profiles['data'][$k]);
				}
				else if ( count($blacklist) && ( in_array($profile['id'], $blacklist) || count(array_intersect($tags, $blacklist)) ))
				{
					/* on blacklist, remove */
					unset($profiles['data'][$k]);
				}
			} 
			/* --- */
			
			require_once('Zend/Json.php');
		
			return array(
				'templatefile' => 'provision',
				'vars' => array(
					'ip'		=> $ip,
					'mac'		=> $mac,
					'serviceid' => $params["serviceid"],
					'nonce'     => $_SESSION['nps_nonce'],
					'profiles'  => $profiles['data'],
					'addons_json'   => Zend_Json::encode($addons['data']),
					'profiles_json' => Zend_Json::encode( array_values($profiles['data']) ),
					'error'		=> $error,
					'ask_ipmi_password' => !empty($params[NOCPS_CONFIG_REQUIRE_IPMIPASSWORD])
				)
			);
		}
	}
	catch (Exception $e)
	{
		nocprovisioning_log("Provisioning error - ".$e->getMessage(), $params);
		die('<b>Error: </b>'.$e->getMessage() );
	}
}

/**
 * Show datatraffic page
 */
function nocprovisioning_datatraffic($params)
{
	try
	{
		/* against XSS attacks */
		if ( $_SERVER['REQUEST_METHOD'] == "POST" && $_POST['nps_nonce'] != $_SESSION['nps_nonce'] )
			throw new Exception('Nonce value does not match '.print_r($_POST, true));

		if ($params[NOCPS_CONFIG_ENABLE_BANDWIDTHGRAPHS] != 'on')
			throw new Exception('Not allowed');		

		$api = nocprovisioning_api($params);
		$mac = nocprovisioning_getServerMAC($params["serviceid"]);
		/* get the number of network connections associated with the server,
		   and the time the data was first and last updated */
		$info = $api->getAvailableBandwidthData($mac);
		
		/* check when the customer purchased the server, to hide traffic from previous customers */
		$q       = mysql_query("SELECT regdate FROM tblhosting WHERE id=".intval($params["serviceid"]) );
		$regdate = strtotime(mysql_result($q, 0));
		
		/* show graphs by calendar month */
		$day     = 0;

		/* this month's graph */
		$startgraph1 = mktime(0,0,0,date('n'), $day, date('Y'));
		$endgraph1   = mktime(0,0,0,date('n')+1, $day, date('Y'));
		$startgraph1 = max($startgraph1, $info['start'], $regdate);
		$endgraph1	 = $info['last'];

		$graphs1 = '';
		for ($i = 0; $i < $info['ports']; $i++)
		{
			if ($i == 0)
				$graphs1 = '<p><img width="497" height="249" alt="Previous month" onerror="alert('."'".'Upgrade your browser to see the graphs. Does not seem to support data URI'."'".');" src="data:image/png;base64,'.$api->generateBandwidthGraph(array('host' => $mac, 'port' => $i, 'start' => $startgraph1, 'end' => $endgraph1) ).'"></p>';
			else
				$graphs1 .= '<p><img width="497" height="249" alt="Previous month" src="data:image/png;base64,'.$api->generateBandwidthGraph(array('host' => $mac, 'port' => $i, 'start' => $startgraph1, 'end' => $endgraph1) ).'"></p>';
		}

		/* last month's graph */
		$graphs2 = '';		
		$startgraph2 = mktime(0,0,0,date('n')-1, $day, date('Y'));
		$endgraph2   = mktime(0,0,0,date('n'), $day, date('Y'));
		
		if ($endgraph2 < $info['start'] || $endgraph2 < $regdate)
		{
			/* we don't have data from last month */
			$startgraph2 = $endgraph2 = 0;
		}
		else
		{
			$startgraph2 = max($startgraph2, $info['start'], $regdate);
			for ($i = 0; $i < $info['ports']; $i++)
			{
				$graphs2 .= '<p><img width="497" height="249" alt="Previous month" src="data:image/png;base64,'.$api->generateBandwidthGraph(array('host' => $mac, 'port' => $i, 'start' => $startgraph2, 'end' => $endgraph2) ).'"></p>';
			}
		}
		
		$data = $api->getBandwidthData(array('host' => $mac, 'port' => $port, 'start' => $startgraph1, 'end' => $endgraph1));

		return array(
			'templatefile' => 'datatraffic',
			'vars' => array(
				'ip'		=> $ip,
				'nonce'		=> $_SESSION['nps_nonce'],
				'serviceid' => $params["serviceid"],
				'ports'		=> $info['ports'],
				'graphs1'   => $graphs1,
				'graphs2'   => $graphs2,
				'timezone'  => 'UTC'
/*				'startgraph1' => $startgraph1,
				'startgraph2' => $startgraph2,
				'endgraph1'  => $endgraph1,
				'endgraph2'  => $endgraph2 */
			)
		);
	}
	catch (Exception $e)
	{
		die('<b>Error: </b>'.$e->getMessage() );
	}
}

// ROUTINE NO LONGER USED. NOW USING INLINE BASE64 DATA URI IMAGES
/*
// Generate the actual PNG graph image
function nocprovisioning_graph($params)
{
	try
	{
		if ( $_GET['nps_nonce'] != $_SESSION['nps_nonce'] )
			throw new Exception('Nonce value does not match');
			
		if ($params[NOCPS_CONFIG_ENABLE_BANDWIDTHGRAPHS] != 'on')
			throw new Exception('Not allowed');		

		
		$api = nocprovisioning_api($params);
		
		// server 
		$mac = nocprovisioning_getServerMAC($params["serviceid"]);
		// network port number 
		$port  = intval($_GET['subid']);
		// start time of graph 
		$start = intval($_GET['start']);
		// end time of graph 
		$end   = intval($_GET['end']);
		
		$graph = base64_decode( $api->generateBandwidthGraph(array('host' => $mac, 'port' => $port, 'start' => $start, 'end' => $end) ) );
		header("Content-Type: image/png");
		header('Content-Disposition: inline; filename="graph-'.str_replace(':','', $mac).'-'.$port.'-'.$start.'-'.$end.'.png"');
		header("Content-Length: ".strlen($graph));
		
		echo $graph;
		exit(0);
	}
	catch (Exception $e)
	{
		die('<b>Error: </b>'.$e->getMessage() );
	}	
}
*/

/**
 * Bandwidth reporting
 */
function nocprovisioning_UsageUpdate($params)
{
	$api = nocprovisioning_api($params);
	$q = mysql_query("SELECT id,dedicatedip,regdate,packageid FROM tblhosting WHERE server=".intval($params['serverid'])." AND dedicatedip<>''");
	
	while ($d = mysql_fetch_assoc($q) )
	{
		try
		{
			$ip     = $d['dedicatedip'];
			$mac    = $api->getServerByIP($ip);
	
			/* get the number of network connections associated with the server,
			   and the time the data was first and last updated */
			$info = $api->getAvailableBandwidthData($mac);
			
			/* fetch the package information from the database to know which billing method we are using */
			$q2 = mysql_query("SELECT ".NOCPS_CONFIG_BANDWIDTHUNIT.",overagesbwlimit FROM tblproducts WHERE id=".$d['packageid']);
			list($bwunit, $bwlimit) = mysql_fetch_array($q2);
			
			/* accounting by calendar month */
			$day    = 0;
	
			/* we want this month's data */
			$start = mktime(0,0,0,date('n'), $day, date('Y'));
			$end   = mktime(0,0,0,date('n')+1, $day, date('Y'));
	
			$start = max($start, $info['start'], strtotime($d['regdate']));
			$end   = $info['last'];
	
			/* Get the total sum of all ports */
			$bw = 0;
			for ($i=0; $i<$info['ports']; $i++)
			{
				$bwinfo = $api->getBandwidthData(array('host' => $mac, 'port' => $port, 'start' => $start, 'end' => $end));
				
				if ($bwunit == 'Mbit/s')
				{
					$bw += $bwinfo['95pct'] / 1048576;
				}
				else if ($bwunit == 'GB')
				{
					$bw += ($bwinfo['inbytes'] + $bwinfo['outbytes']) / 1073741824;
				}
				else
				{
					$bw += ($bwinfo['inbytes'] + $bwinfo['outbytes']) / 1048576;
				}
			}
	
			$bw = round($bw);
			update_query("tblhosting",array("bwusage" => $bw, "bwlimit" => $bwlimit, "lastupdate"=>"now()"),array("id"=> $d['id']));
		}
		catch (Exception $e)
		{
			/* If there's an error processing a host, just continue to the next one */
		}
	}
}

function nocprovisioning_log($msg, $params = false)
{
	$username = 'Client';
	$userid   = 0;
	
	if ($params && !empty($params['serviceid']))
		$msg .= " - Service ID: ".$params['serviceid'];
	if (!empty($_SESSION['uid']))
		$userid = $_SESSION['uid'];
	if (!empty($_SESSION['adminid']))
	{
		try {
		$q = select_query("tbladmins","username",array("id" => array("sqltype" => "EQ", array("value" => $_SESSION['adminid']))) );
		} catch (Exception $e) {
			return "SELECT: ".$e->getMessage();
		}
		$username = mysql_result($q, 0);
		if (!$username)
			$username = 'ghost admin '.$_SESSION['adminid'];
	}
	if ( !empty($_SERVER["HTTP_X_FORWARDED_FOR"]) )
		$msg .= " - X-FORWARDED-FOR: ".$_SERVER["HTTP_X_FORWARDED_FOR"];
	
	try {
	insert_query("tblactivitylog", array("date" => "now()", "description" => $msg, "user" => $username, "userid" => $userid, "ipaddr" => $_SERVER['REMOTE_ADDR']));
		} catch (Exception $e) {
			return "INSERT: ".$e->getMessage();
		}
}
