<?PHP


function websocket_proxy_start($listen_port,$vnc_host,$vnc_port,$duration = 60,$client_ip) {
	lg_debug("Starting Console Proxy");
	lg_debug("Proxy-Start-Command: /var/www/novnc/start_proxy $listen_port $vnc_host $vnc_port $duration");
	system("/var/www/novnc/init/start_proxy $listen_port $vnc_host $vnc_port $duration $client_ip");
}

?>
