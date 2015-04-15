<?PHP

function nocps_init() {
	$nopcs_server = "NOCPS.SERVER.IP.ADDRESS";
	$nocps_user   = "admin_username";
	$nocps_pass   = "admin_password";
	return new nocps_api("$nocps_server","$nocps_user","$nocps_pass");
}


function nocps_get_hosts($nocps_api) {

	static $all_hosts = Array();

	if (!( $all_hosts[0] and is_array($all_hosts[0])))  {
		foreach (nocps_get_subnets($nocps_api) as $index => $data ) {

			$result    = $nocps_api->getHosts($data["subnet"],0,1000);
			$all_hosts = array_merge ( $all_hosts, $result["data"]);
		}
	}
	lg_debug(print_r($all_hosts,true));
	return $all_hosts;

}

function nocps_get_host_ip_by_name($nocps_api,$hostname) {

	foreach ( nocps_get_hosts($nocps_api) as $index => $data ) {

		if ( $data["hostname"] == $hostname ) {
			return $data["ip"];
		}

	}

}

function nocps_get_host_mac_by_name($nocps_api,$hostname) {

	foreach ( nocps_get_hosts($nocps_api) as $index => $data ) {

		if ( $data["hostname"] == $hostname ) {
			return $data["mac"];
		}

	}
}

function nocps_get_citrix_servers($nocps_api) {

	static $servers = Array();
	if  ( ! (is_array($servers) and $servers[0]["id"] ) )  {
		$result = $nocps_api->getDevices(0,1000);
		if (is_array($result) and $result["success"]=="1" and $result["total"] >= 1) {
			foreach ( $result["data"] as $index => $data) {
				if ($data["type"] == "xenserver") { 
					lg_debug("Got new XenServer from NOC-PS: ".$data["name"]);
		
					array_push($servers,
						Array( 
							"servername" 	=> $data["name"],
							"address" 	=> $data["ip"],
							"id"		=> $data["id"] )
					); 
				}
			}
		}
	}
	$server_count = count($servers);
	lg_debug("Got $server_count XenServers from NOC-PS");
	return $servers;
			
}

function nocps_get_free_ram($nocps_api,$server_id,$server_name,$server_address) {

	$info = get_citrix_info($nocps_api,$server_id,$server_address);
	lg_debug("XenServer $server_name has ".$info["free_ram_mb"]." MB free RAM");
	return $info["free_ram_mb"];
	
}

function nocps_get_free_disk_space($nocps_api,$server_id,$server_name,$server_address) {

	$info = get_citrix_info($nocps_api,$server_id,$server_address);
	lg_debug("XenServer $server_name has ".$info["free_diskspace_mb"]." MB free Diskspace");
	return $info["free_diskspace_mb"];

}

function nocps_get_core_count($nocps_api,$server_id,$server_name,$server_address) {

	$info = get_citrix_info($nocps_api,$server_id,$server_address);
	lg_debug("XenServer $server_name has ".$info["total_cores"]." CPU Cores");
	return $info["total_cores"];

}
	
function get_citrix_info ($nocps_api,$server_id,$server_address) {

	$ssh_key_file = "/var/www/.ssh/id_citrix_info";
	static $citrix_info = Array ();

	if ( ! ( is_array($citrix_info) and count($citrix_info) > 0 and $citrix_info["free_ram_mb"])) {
		lg_debug2("SSH-Connection to $server_address to get free ram / disksize");
		$ssh_options = "-o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no";
		$ssh_command = "/usr/bin/ssh 2>&1 $ssh_options -i $ssh_key_file -l citrix_info $server_address";
		lg_debug("$ssh_command");
		$ssh_output = 
			popen("$ssh_command","r");
			foreach (error_get_last() as $index => $line) { lg_err("$line"); }
		do {
			$line = fgets($ssh_output);
			lg_debug2($line);
			$values = explode ( " ", $line);
			if ( $values[0] == "free_memory"     ) $citrix_info["free_ram_mb"]       = chop($values[1]);
			if ( $values[0] == "free_disk_space" ) $citrix_info["free_diskspace_mb"] = chop($values[1]);
			if ( $values[0] == "total_cores"     ) $citrix_info["total_cores"]       = chop($values[1]);

		} while ( $line );
		pclose($ssh_output);
	}
	return $citrix_info;
}

function nocps_define_citrix_vm($nocps_api,$vm_name,$vm_description,$ram_amount,$disk_size,$vcore_count) {

	# ram_mount = needed ram in MB
	# disk_size = needed disk_size in MB

	$servers 	= nocps_get_citrix_servers($nocps_api);
	$target_server 	= "";

	foreach ( $servers as $index => $data ) {

		$free_ram 	 = nocps_get_free_ram       ($nocps_api,$data["id"],$data["servername"],$data["address"]);
		$free_disk_space = nocps_get_free_disk_space($nocps_api,$data["id"],$data["servername"],$data["address"]);
		$total_cores     = nocps_get_core_count     ($nocps_api,$data["id"],$data["servername"],$data["address"]);

		lg_debug("free_ram        of server: $free_ram        reqd ram:        $ram_amount");
		lg_debug("free_disk space of server: $free_disk_space reqd disk space: $disk_size");
		if($free_ram >= $ram_amount and $free_disk_space >= $disk_size and $total_cores >= $vcore_count) {
			lg_info("Got Server ".$data["servername"]." for new VM $vm_name with $ram_amount MB RAM, $disk_size MB Disk size and $vcore_count VCores");
			$target_server=$data;
			break;
		}
			
	}	

	if ($target_server) {
		$ip = nocps_get_free_ip_address($nocps_api);
		

		if ($ip[0] == "No IPs avalable") {
			lg_err("No IPs available in NOC-PS");
			return "No IPs available";
		} else {
			$subnet	= $ip[1]; 
			$ip 	= $ip[0];
			lg_info("Got new IP address $ip");
		
			lg_debug("Creating new VM");
			lg_debug2("Subnet: $subnet");
			lg_debug2("ip: $ip");
			lg_debug2("vm_name: $vm_name");
			lg_debug2("vm_description: $vm_description");
			lg_debug2("module: ".$target_server["id"]);
			lg_debug2("memory: $ram_amount");
			lg_debug2("disk_size: $disk_size");
			lg_debug2("network: ".nocps_get_citrix_first_nic($nocps_api,$target_server["id"]));

			try {
			$result = $nocps_api->addVM(Array(
					"subnet"	=> $subnet,
					"ip"		=> $ip,
					"numips"	=> 1,
					"hostname"	=> $vm_name,
					"description"   => $vm_description,
					"module"	=> $target_server["id"],
					"memory"	=> $ram_amount,
					"disk"		=> $disk_size,
					"diskstore"	=> "Local storage",
					"network"	=> nocps_get_citrix_first_nic($nocps_api,$target_server["id"]),
					));
			}
			catch (exception $e) {
				lg_err("Error from XenServer while creating new VM, Please check xensource.log");
				return "Error: from XenServer while Creating VM";
			}
		
		}
		if( is_array($result) and $result["success"] == "1") {
			$mac = $result["mac"];
			lg_info("VM-Setup Phase 1 - successful");
			if ($vcore_count > 1 ) { citrix_set_vcore_count($target_server["servername"],$vm_name, $vcore_count); }
			return "NEWIP $ip";
		} else { 
			lg_err("VM-Setup failed, unknown error");
			lg_err("success: ".$result["success"]);
			$lines = print_r($result,true);
			if(is_array($lines)) { foreach($lines as $ind => $text) { lg_info($ind." ".$text); } }
			return "Error: VM Setup Failed, unknown Error";	
		}
	} else {
		lg_err("VM-Setup failed, No Server with enough resources for this VM available");
		return "Error: No Server has enough resources for this VM";
	}
}


function nocps_get_server_id ( $nocps_api, $server_name ) {

	$servers = nocps_get_citrix_servers ( $nocps_api );
	foreach ( $servers as $key => $data ) {

		if ( $data["servername"] == "$server_name" ) {
			lg_debug2("Server ID for server $server_name is ".$data["id"]);
			return $data["id"];
		}
	}
}
 
function nocps_get_datastores ( $nocps_api, $server_name ) {
		
	$result = $nocps_api->getDatastores(nocps_get_server_id($nocps_api,$server_name));
}

function nocps_get_subnets ( $nocps_api ) {

	$result       = $nocps_api->getSubnets(0, 1000);
	return $result['data'];

}

function nocps_get_free_ip_address ( $nocps_api) {

	foreach (nocps_get_subnets($nocps_api) as $index => $data ) {

		$result = $nocps_api->getFirstAvailableIP($data["subnet"]);
		if (preg_match("/^([0-9]{1,3}.){3}[0-9]{1,3}$/",$result)) return Array($result,$data["subnet"]);
	}
	
	return Array("No IPs available");
}


function nocps_get_citrix_first_nic($nocps_api,$xenserver_id) {

	$result = $nocps_api->getNetworks($xenserver_id);
	if (is_array($result) and $result["success"] == 1) {
		return $result["data"][0]["id"];
	}
}

function nocps_get_xenserver_for_vm ( $nocps_api, $vmname, $vmip ) {
	
	lg_debug("wanted vm-name: $vmname vm-ip: $vmip");
	$citrix_servers = nocps_get_citrix_servers ( $nocps_api);
	lg_debug2(print_r($citrix_servers,true));
	foreach ($citrix_servers as $index => $data) {
		$vms = $nocps_api->searchHosts(Array("device" => $data["id"]));
		lg_debug2(print_r($vms,true));
		foreach ($vms["data"] as $vm_index => $vm_data ) {
			lg_debug("checking ip ".$vm_data["ip"]);
			if ( $vm_data["ip"] == "$vmip" ) {	
				lg_debug("Found VM $vmip on Xen-Server ".$data["servername"]);
				return $data["servername"];
			}
		}
	}	
}

function citrix_get_vm_vnc_port($xenserver_name, $vm_name) {
	$ssh_key_file = "/var/www/.ssh/id_citrix_vminfo";
	lg_debug("SSH-Connection to $xenserver_name to get vnc port of vm $vm_name");
	$ssh_options = "-o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no";
	$ssh_command = "/usr/bin/ssh 2>&1 $ssh_options -i $ssh_key_file -l citrix_info $xenserver_name  /usr/bin/sudo /usr/local/bin/citrix_get_vnc_port $vm_name"; 
	lg_debug("$ssh_command");
	$ssh_output = popen("$ssh_command","r");
	do {
		$line = fgets($ssh_output);
		lg_debug2($line);
		$values = explode ( " ", $line);
		if ( $values[0] == "vnc_port" ) {
			$vnc_port = chop($values[1]);
			lg_debug("got vnc port $vnc_port");
			}

	} while ( $line );
	pclose($ssh_output);
	return $vnc_port;

}

function citrix_set_vcore_count ($xen_server, $vm_name, $vcore_count) {
	$ssh_key_file = "/var/www/.ssh/id_citrix_vminfo";
	lg_debug("SSH-Connection to $xen_server to set vcores of $vm_name to $vcore_count");
	$ssh_options = "-o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no";
	$ssh_command = "/usr/bin/ssh 2>&1 $ssh_options -i $ssh_key_file -l citrix_info $xen_server  /usr/bin/sudo /usr/local/bin/citrix_set_vcores $vm_name $vcore_count"; 
	lg_debug("$ssh_command");
	$ssh_output = popen("$ssh_command","r");
	do {
		$line = fgets($ssh_output);
		lg_debug2($line);

	} while ( $line );
	pclose($ssh_output);
	return $vnc_port;

}

?>
