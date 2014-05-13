<?php
require 'vendor/autoload.php';
date_default_timezone_set("Europe/Stockholm");
$lang = "en";
if (isset($_GET['lang'])) $lang = $_GET['lang'];


include_once("../templates/emmalang_en.php");
include_once("../templates/emmalang_$lang.php");
include_once("../templates/classEmma.class.php");

$RunnerStatus = Array("1" =>  $_STATUSDNS, "2" => $_STATUSDNF, "11" =>  $_STATUSWO, "12" => $_STATUSMOVEDUP, "9" => $_STATUSNOTSTARTED,"0" => $_STATUSOK, "3" => $_STATUSMP, "4" => $_STATUSDSQ, "5" => $_STATUSOT, "9" => "", "10" => "");

header('content-type: text/html'); # ; charset='.$CHARSET);


# nh2.php/{race}/{class}/{listtype}/{leg}

$m = new Mustache_Engine(array(
    'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views'),
    'escape' => function($value) {
        return htmlspecialchars($value, ENT_COMPAT, 'ISO-8859-1');},
));

$splits = array(
    "NightHawk Men"=>array(5=>array("1121","1111","1141","1136")),
    "NightHawk Women"=>array(4=>array("1136"))
);


$app = new \Slim\Slim(array('debug'=>true));

$app->get('/:raceId/:class/result/:leg/:split', function ($raceId,$class,$leg,$split) {
    global $m, $splits;
    $comp = new Emma($raceId);
    if ($split>0) {
        $results = result_by_leg_at_split($class, $comp, $leg, $splits[$class][$leg][$split-1]);
    } else {
        $results = result_by_leg($class, $comp, $leg);
    }
    echo $m->render("total", array("res"=>$results, "class"=>$class));	
});

$app->get('/:raceId/:class/team/:bibNr', function ($raceId,$class,$leg) {
    global $m;
    $comp = new Emma($raceId);
    $results = result_by_team($class, $comp);
    echo $m->render("team", array("res"=>$results, "class"=>$class));	
});


$app->get('/:raceId/:class/listall', function ($raceId,$class) {
    global $m;
    $comp = new Emma($raceId);
    $results = list_all_result($class, $comp);
	echo $m->render("listall", array("class"=>$class,"res"=>$results));
});


$app->run();



function result_by_leg ($class, $comp, $leg) {

    $q = "SELECT * FROM (select relay_teamid, Runners.Club, max(Results.Status) as Status, sum(relay_restarts) as Restarts, sum(relay_leg_time) as Time, count(Runners.DbId) as NumFinish From Runners,Results where Results.DbID = Runners.DbId AND Results.TavId = ". $comp->m_CompId ." AND Runners.TavId = ".$comp->m_CompId ." AND Runners.Class like '".$class."-_' AND Control=1000 AND relay_leg<=".$leg." GROUP BY relay_teamid,Club ORDER BY status,NumFinish desc,restarts, time) as total, (select relay_teamid, Name, relay_leg_time as LegTime, re.Status as LegStatus from Results re, Runners ru WHERE re.dbid=ru.dbid and re.tavid=".$comp->m_CompId." and ru.tavid=".$comp->m_CompId." and relay_leg=".$leg." and class like '".$class."-_' AND control=1000)  as leg_runner WHERE total.relay_teamid=leg_runner.relay_teamid ORDER by status, NumFinish desc, restarts, time";

    if ($result = mysql_query($q,$comp->m_Conn)) {
        $results = array();
        $rank = 1;
        $besttime = 0;
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
            $delta = 0;
            if ($n==1) { $besttime = $row["Time"]; }
            else { 
                $delta = $row["Time"] - $besttime; 
            }
            
            if ($prevrestart<$row["Restarts"]) {
                $prevrestart = $row["Restarts"];
                if ($n>1)$restart_flag= $row["Restarts"]-5;
            }

	    if ($row["Status"]>0) { $rank=""; }

            array_push($results, array(
                "Club"=>$row["Club"], "Status"=>$row["Status"],
                "Timestr"=>formatTime($row["Time"], $row["Status"]),
                "Restarts"=>$row["Restarts"],
                "TeamId"=>$row["relay_teamid"], "Rank"=>$rank,
                "RestartFlag"=>$restart_flag, "NumFinish"=>$row["NumFinish"],
                "Behind" => ($delta>0) ? "+".formatTimeSimple($delta,0):"",
		"LegStatus"=>$row["Status"],
		"Name" => $row["Name"], "LegTime"=>formatTime($row["LegTime"],0)." ".status($row["LegStatus"])
            )
            );
        }
        mysql_free_result($result);
        return $results;
        
    }		else {
        
        die(mysql_error());
    }
    



}

function result_by_leg_at_split ($class, $comp, $leg, $split) {

# sum(relay_leg_time) leg<$leg + Time at current to control

    $q = "
SELECT total.relay_teamid, Club, Status, Restarts, total.Time+leg_runner.SplitTime as Time, 
       NumFinish, 
       LegStatus, Name, SplitTime
FROM
 (select relay_teamid, Runners.Club, max(Results.Status) as Status, 
  sum(relay_restarts) as Restarts, sum(relay_leg_time) as Time, count(Runners.DbId) as NumFinish
  FROM Runners,Results where Results.DbID = Runners.DbId 
    and Results.TavId = ". $comp->m_CompId ."
    and Runners.TavId = ".$comp->m_CompId ." AND Runners.Class like '".$class."-_' 
    and Control=1000 AND relay_leg<".$leg." 
  GROUP BY relay_teamid,Club 
  ORDER BY status,NumFinish desc,restarts, time) as total, 
  (SELECT relay_teamid, Name, relay_leg_time as SplitTime, re.Status as LegStatus 
   FROM Results re, Runners ru 
   WHERE re.dbid=ru.dbid and re.tavid=".$comp->m_CompId." and ru.tavid=".$comp->m_CompId." 
    and relay_leg=".$leg." and class like '".$class."-_' AND control=".$split.") as leg_runner
WHERE total.relay_teamid=leg_runner.relay_teamid 
ORDER by status, NumFinish desc, restarts, time
";
    if ($result = mysql_query($q,$comp->m_Conn)) {
        $results = array();
        $rank = 1;
        $besttime = 0;
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
            $delta = 0;
            if ($n==1) { $besttime = $row["Time"]; }
            else { 
                $delta = $row["Time"] - $besttime; 
            }
            
            if ($prevrestart<$row["Restarts"]) {
                $prevrestart = $row["Restarts"];
                if ($n>1)$restart_flag= $row["Restarts"]-5;
            }

	    if ($row["Status"]>0) { $rank=""; }

            array_push($results, array(
                "Club"=>$row["Club"], "Status"=>$row["Status"],
                "Timestr"=>formatTime($row["Time"], $row["Status"]),
                "Restarts"=>$row["Restarts"],
                "TeamId"=>$row["relay_teamid"], "Rank"=>$rank,
                "RestartFlag"=>$restart_flag, "NumFinish"=>$row["NumFinish"],
                "Behind" => ($delta>0) ? "+".formatTimeSimple($delta,0):"",
		"LegStatus"=>$row["Status"],
		"Name" => $row["Name"], "LegTime"=>formatTime($row["SplitTime"],0)." ".status($row["LegStatus"])
            )
            );
        }
        mysql_free_result($result);
        return $results;
        
    }		else {
        
        die(mysql_error());
    }
    



}


function list_all_result ($class, $comp) {
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
        
        mysql_free_result($result);
        return $results;
        
    }		else {
        
        die(mysql_error());
    }
 }

function status($status){
	 global $RunnerStatus;
    if ($status != "0")  {
      return $RunnerStatus[$status]; //$status;
    }
    return "";
}

function formatTime($time,$status){
    global $lang;
    global $RunnerStatus	;
    
    if ($status != "0")  {
      return $RunnerStatus[$status]; //$status;
    }

    return gmdate("H:i:s",$time/100);
  
}

function formatTimeSimple($time,$status){
    global $lang;
    global $RunnerStatus	;
    
    if ($status != "0")  {
      return $RunnerStatus[$status]; //$status;
    }
    
    $sec = $time / 100;
    if ($sec<60) {
        return gmdate("s",$sec);
    } else if ($sec<60*60) {
        return gmdate("i:s",$sec);
    } 
    return gmdate("H:i:s",$sec);
}


?>
