<?php

if (!defined("WHMCS")) die("This file cannot be accessed directly");


/*

	PURPOSE:	Send an unsuspention email to the client
			when the service is unsuspended(e. g. client paid)

*/

require_once "/var/www/goweb.de/modules/servers/nocprovisioning/include/log.php";

function send_unsuspension_email($var) {

        $server=$var["params"]["domain"];
        lg_info("Sent unsuspension mail to ".$var["params"]["clientsdetails"]);

 $command = "sendemail";
 $adminuser = "tunsleber";
 $values["customtype"] = "product";
 $values["customsubject"] = "Service reactivated successfully";
 $values["custommessage"] = 
"Dear Customer,

your server $server has just been reactivated successfully.

Regards,
Your Support Team";
 $values["id"] = $var["params"]["serviceid"];
 $results = localAPI($command, $values, $adminuser);
}

add_hook("AfterModuleUnsuspend",1,"send_unsuspension_email");

?>

