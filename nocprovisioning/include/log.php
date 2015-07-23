<?PHP

$LOG = ARRAY ( 
	"CRIT"   => 1,
	"ERR"    => 2,
	"WARN"   => 3,
	"INFO"   => 4,
	"DEBUG"  => 5,
	"DEBUG2" => 6,
	);

$loglevel = $LOG["INFO"];
$my_logfile  = "/tmp/nocps.log";

function _log($msg,$msglevel) {
	$loglevel=6;
	// global $loglevel;
	$my_logfile = "/tmp/nocps.log";
	if($msglevel <= $loglevel) {
		$_log = fopen($my_logfile,"a");
		if ($_log === false) {
			error_log("Cannot write to log file $my_logfile: ".print_r(error_get_last(),true)."\n");
			error_log("Log Message: $msg\n");
			file_put_contents('php://stderr', "Cannot write to log file $my_logfile:".error_get_last()."\n");
			file_put_contents('php://stderr', "Log Message: $msg\n");
		} else {
			fputs($_log, date("Y-m-d H:i:s ").$msg."\n");
			fclose($_log);
		}
	}
}	

function lg_crit   ($msg ) { global $LOG; _log ($msg,$LOG["CRIT"]   );}
function lg_err    ($msg ) { global $LOG; _log ($msg,$LOG["ERR"]    );}
function lg_warn   ($msg ) { global $LOG; _log ($msg,$LOG["WARN"]   );}
function lg_info   ($msg ) { global $LOG; _log ($msg,$LOG["INFO"]   );}
function lg_debug  ($msg ) { global $LOG; _log ($msg,$LOG["DEBUG"]  );}
function lg_debug2 ($msg ) { global $LOG; _log ($msg,$LOG["DEBUG2"] );}

?>
