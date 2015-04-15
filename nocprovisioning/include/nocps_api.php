<?php
/**
 * NOC-PS API
 *
 * Author: Floris Bos (Maxnet)
 * bos@je-eigen-domein.nl
 *
 * Wrapper class that uses the Zend Framework's XML-RPC classes to do the dirty work! :-)
 * XML-RPC automatically detects which methods are supported by the server, so you will not find them listed here
 * Read the documentation instead
 */

/* IP-address of NOC-PS server, e.g. '1.2.3.4' */
define('NOCPS_DEFAULT_SERVER', '');
/* Username */
define('NOCPS_DEFAULT_USERNAME', '');
/* Password */
define('NOCPS_DEFAULT_PASSWORD', '');


/* Check for necessary extensions */
if ( !extension_loaded('openssl') )
	die("Error! OpenSSL extension is missing! Cowardly refusing insecure communication!");
if ( !extension_loaded('xml') || !extension_loaded('dom') )
	die("Error! XML/DOM extension is missing! These extensions are supposed to be enabled by default in PHP 5, check your installation!");
	

/* Add include directory to path */
set_include_path( dirname(__FILE__). PATH_SEPARATOR.get_include_path() );
require_once('Zend/XmlRpc/Client.php');


class nocps_api
{
	protected $_client, $_proxy;

	/**
	 *	Constructor
	 *
	 *	Connect to the NOC-PS server
	 *	@param string $server     IP-address of server (will use default if empty)
	 *	@param string $user       Username
	 *	@param string $password   Password
	 *	@param string $logusername OPTIONAL: client username/id for logging purposes
	 */
	function __construct($server = NOCPS_DEFAULT_SERVER, $user = NOCPS_DEFAULT_USERNAME, $password = NOCPS_DEFAULT_PASSWORD, $logusername = '')
	{
		if (!$server)
			die("Please set the server, username and password in ".__FILE__);

		$http = new Zend_Http_Client();
		$http->setAuth($user, $password);

		/* If the API is used in a website, include IP-address of client as X-forwarded-for header for logging purposes */
		if ( isset($_SERVER["REMOTE_ADDR"] ))
			$http->setHeaders("X-Forwarded-For: ".(isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"].", " : "").$_SERVER['REMOTE_ADDR']);
		if ($logusername)
			$http->setHeaders("X-Forwarded-For-User: $logusername");

		$this->_client = new Zend_XmlRpc_Client("https://$server/xmlrpc.php", $http);
		$this->_proxy = $this->_client->getProxy('PXE_API');
	}
	
	/**
	 * All API calls are routed through the XML-RPC proxy
	 */
	function __call($name, $arguments)
	{
		return call_user_func_array(array($this->_proxy, $name), $arguments);
	}
}