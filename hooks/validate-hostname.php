<?php

if (!defined("WHMCS"))
    die("This file cannot be accessed directly");

/*
	PURPOSE:	Check if the hostname is a valid dns-hostname/dns-domainname
*/

require_once "/var/www/goweb.de/modules/servers/nocprovisioning/include/log.php";

require_once "/var/www/goweb.de/modules/servers/nocprovisioning/include/log.php";
require_once "/var/www/goweb.de/modules/servers/nocprovisioning/include/validate.php";

function validate_server_hostname($vars) {

        lg_debug("Validating this: ".$vars["hostname"]);

        if ( !validate_dns_domainname($vars["hostname"]) ) {
                return "Error: Invalid hostname - Hostname must be like: <b>(yourserver).yourdomain.tld ( example: supserserver.com </b>or<b> server666.superserver.com )</b>";
        }

        lg_debug("validated: ".validate_dns_domainname($vars["hostname"]));

}

add_hook("ShoppingCartValidateProductUpdate",1,"validate_server_hostname");

?>

