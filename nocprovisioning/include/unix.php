<?PHP 

function unix_get_free_tcp_port($start_port = 60000, $end_port = 65000) {

	lg_debug("Searching free tcp port");
	for($i = 1; $i <=500; $i++) {
		$port = rand($start_port,$end_port);
		$busy = `netstat -nlt | grep :$port | wc -l`;
		if($busy == 0) {
			lg_debug("got free port $port");
			return $port;
		}
	}
}

function unix_ssh_local_redirect ( $local_port, $ssh_target, $remote_host, $remote_port, $duration = 1800) {

        $ssh_key_file = "/var/www/.ssh/id_citrix_vminfo";
        lg_debug("SSH-Connection to $ssh_target for port forwarding from port $local_port to $remote_host:$remote_port");
        $ssh_options = "-p 59172 -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no";
	$forward     = "-L $local_port:$remote_host:$remote_port";
        $ssh_command = "/usr/bin/ssh >/tmp/ssh_forward.log 2>&1 $ssh_options $forward -i $ssh_key_file -l citrix_info $ssh_target sleep $duration &";
	lg_debug("SSH-Command: $ssh_command");
	$ssh = `$ssh_command`;
}

?>
