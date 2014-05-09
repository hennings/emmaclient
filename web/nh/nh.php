<?php
require 'vendor/autoload.php';

date_default_timezone_set("Europe/Stockholm");
$lang = "en";
if (isset($_GET['lang']))
 $lang = $_GET['lang'];

include_once("../templates/emmalang_en.php");
include_once("../templates/emmalang_$lang.php");
include_once("../templates/classEmma.class.php");


$RunnerStatus = Array("1" =>  $_STATUSDNS, "2" => $_STATUSDNF, "11" =>  $_STATUSWO, "12" => $_STATUSMOVEDUP, "9" => $_STATUSNOTSTARTED,"0" => $_STATUSOK, "3" => $_STATUSMP, "4" => $_STATUSDSQ, "5" => $_STATUSOT, "9" => "", "10" => "");

header('content-type: text/html; charset='.$CHARSET);
header('cache-control: max-age=15');
header('pragma: public');
header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + 15));


$method = $_GET["method"];
$class = $_GET['class'];
$comp = new Emma($_GET['comp']);

$m = new Mustache_Engine(array(
   'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views'),
   'escape' => function($value) {
        return htmlspecialchars($value, ENT_COMPAT, 'ISO-8859-1');},
    ));



if ($method == "all") {


$restpl = $m->loadTemplate('results.mustache');
			$q = "SELECT Runners.Name, Runners.Club, Results.Time ,Results.Status, Results.Changed, Results.DbID, Results.Control, relay_restarts, relay_teamid, relay_leg, relay_leg_time From Runners,Results where Results.DbID = Runners.DbId AND Results.TavId = ". $comp->m_CompId ." AND Runners.TavId = ".$comp->m_CompId ." AND Runners.Class like '".$class."-_' AND Control=1000 ORDER BY relay_teamid, relay_leg";

if ($result = mysql_query($q,$comp->m_Conn)) {
	$results = array();

	while ($row = mysql_fetch_array($result)) {
	      array_push($results, array("Name"=>$row["Name"],
	        "Club"=>$row["Club"], "Status"=>$row["Status"],
		"Timestr"=>formatTime($row["relay_leg_time"], $row["Status"]),
		"Restarts"=>$row["relay_restarts"],
		"TeamId"=>$row["relay_teamid"],
		"Leg"=>$row["relay_leg"]
		)
	      );
	}

	echo $restpl->render(array("class"=>$class,"res"=>$results));

				mysql_free_result($result);

}		else {

				die(mysql_error());
}



} else if ($method == "total") {


			$q = "SELECT relay_teamid,Runners.Club, max(Results.Status) as Status, sum(relay_restarts) as Restarts, sum(relay_leg_time) as Time From Runners,Results where Results.DbID = Runners.DbId AND Results.TavId = ". $comp->m_CompId ." AND Runners.TavId = ".$comp->m_CompId ." AND Runners.Class like '".$class."-_' AND Control=1000 GROUP BY relay_teamid,Club ORDER BY status,restarts, time";

if ($result = mysql_query($q,$comp->m_Conn)) {

   $alltpl = $m->loadTemplate('total.mustache');

        $results = array();
	$rank = 1;
	$prevtime = 0;
	$prevrestart = 0;
	$n = 0;	

	while ($row = mysql_fetch_array($result)) {
	      $n++;
	      if ($row["Time"]!=$prevtime) {
	        $rank = $n;
		$prevtime = $row["Time"];
	      }
        	$restart_flag=0;
	      if ($prevrestart<$row["Restarts"]) {
	        $prevrestart = $row["Restarts"];
		if ($n>1)$restart_flag= $row["Restarts"]-5;
	      }
	      array_push($results, array(
	        "Club"=>$row["Club"], "Status"=>$row["Status"],
		"Timestr"=>formatTime($row["Time"], $row["Status"]),
		"Restarts"=>$row["Restarts"],
		"TeamId"=>$row["relay_teamid"], "Rank"=>$rank,
		"RestartFlag"=>$restart_flag
		)
	      );
	}
	echo	$alltpl->render(array("res"=>$results, "class"=>$class));	
				mysql_free_result($result);

}		else {

				die(mysql_error());
}



}



function formatTime($time,$status){
  global $lang;
  global $RunnerStatus	;

  if ($status != "0")  {
    return $RunnerStatus[$status]; //$status;
  }

  return gmdate("H:i:s",$time/100);

}
?>
