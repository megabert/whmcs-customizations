<?PHP 

function validate_dns_hostname($untested_string) {

	if ( strlen($untested_string) <= 253 and strlen($untested_string) >= 1) {
	
		if (preg_match("/^[0-9a-zA-Z]+\.[0-9a-zA-Z]+\.[0-9a-zA-Z]+(((\.[0-9a-zA-Z]+)?\.[0-9a-zA-Z]+)?\.[0-9a-zA-Z])?$/",$untested_string)) {
			return $untested_string;
		} else {
			lg_debug2("String is no valid dns_hostname(Pattern match failed)");
			return false;
		}

	} else {
		lg_debug2("String is no valid dns_hostname(too long)");
		return false;
	}
}

function validate_dns_domainname($untested_string) {

	if ( strlen($untested_string) <= 253 and strlen($untested_string) >= 1) {
	
		if (preg_match("/^[0-9a-zA-Z]+\.[0-9a-zA-Z]+(((\.[0-9a-zA-Z]+)?\.[0-9a-zA-Z]+)?\.[0-9a-zA-Z]+)?$/",$untested_string)) {
			return $untested_string;
		} else {
			lg_debug2("String is no valid dns domainname(Pattern match failed)");
			return false;
		}

	} else {
		lg_debug2("String is no valid dns_hostname(too long)");
		return false;
	}
}

?>
