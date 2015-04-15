<?PHP

function get_whmcs_nocps_servertype($productid) {

        $q =  mysql_query("SELECT fieldoptions FROM tblcustomfields WHERE fieldname='NOCPSTYPE' and relid='$productid';");
	lg_debug("NOCPS Servertype for product ID $productid is ".(mysql_result($q,0)));
        return mysql_result($q, 0);
}

function whmcs_host_is_virtual($productid) {

        $q =  mysql_query("SELECT fieldoptions FROM tblcustomfields WHERE fieldname='ISVIRTUAL' and relid='$productid';");
        $isvirtual = mysql_result($q, 0);
	lg_debug("Server for product ID $productid is " . (($isvirtual=="1")?"virtual":"physical"));
	return ( $isvirtual and $isvirtual == "1") ? true : false;
}

function get_disk_space_for_product($productid) {

        $q =  mysql_query("SELECT fieldoptions FROM tblcustomfields WHERE fieldname='DISKSIZE' and relid='$productid';");
	lg_debug("Diskspace for product ID $productid is ".mysql_result($q,0)." MB");
        return mysql_result($q, 0);
}

function get_ram_amount_for_product($productid) {

        $q =  mysql_query("SELECT fieldoptions FROM tblcustomfields WHERE fieldname='RAMSIZE' and relid='$productid';");
	lg_debug("RAM size for product ID $productid is ".mysql_result($q,0)." MB");
        return mysql_result($q, 0);
}

function get_vcore_count_for_product($productid) {

        $q =  mysql_query("SELECT fieldoptions FROM tblcustomfields WHERE fieldname='CPUCOUNT' and relid='$productid';");
	lg_debug("RAM size for product ID $productid is ".mysql_result($q,0)." MB");
        return mysql_result($q, 0);
}

function whmcs_get_customer_name($userid) {
	lg_debug2("search customer name for userid $userid");
        $sql =  "SELECT firstname,lastname FROM tblclients WHERE id='$userid';";
	lg_debug2("$sql");
	$q = mysql_query($sql);
	$firstname = mysql_result($q,0,0);
	$lastname = mysql_result($q,0,1);
	lg_debug2("firstname: $firstname lastname: $lastname");
        return mysql_result($q, 0,0)." ".mysql_result($q,0,1);
}

function get_host_name_from_service_id ($serviceid) {

        $q =  mysql_query("SELECT domain FROM tblhosting WHERE id='$serviceid';");
	lg_debug("Servername for service ID $serviceid is ".mysql_result($q,0));
        return mysql_result($q, 0);
}

?>
