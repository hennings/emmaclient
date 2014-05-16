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
header('Cache-Control: max-age=10');

# nh2.php/{race}/{class}/{listtype}/{leg}

$m = new Mustache_Engine(array(
    'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views'),
    'escape' => function($value) {
        return htmlspecialchars($value, ENT_COMPAT, 'ISO-8859-1');},
));

$config = array(
    "NightHawk Men"=>array("legs"=>8),
    "NightHawk Women"=>array("legs"=>6),
    "classes"=>array("NightHawk Men", "NightHawk Women")
);

$splits = array(
    "NightHawk Men"=>array(
        5=>array(
            array("code"=>"1121","name"=>"1.2 km"),
            array("code"=>"1111","name"=>"2.3 km"),
            array("code"=>"1141","name"=>"3.4 km"),
            array("code"=>"1136","name"=>"5.2 km"))),
    "NightHawk Women"=>array(
        4=>array(array("code"=>"1136", "name"=>"4.2 km")))
);


$base = "/emmanh/nh";


$app = new \Slim\Slim(array('debug'=>true));


$menus = array();
foreach (array_keys($splits) as $class) {
    array_push($menus, array("menu"=>make_menu($class),"class"=>$class));
}
$config["menus"]=$menus;

$app->get('/:raceId/:class/result/:leg/:split', function ($raceId,$class,$leg,$split) {
    global $m, $splits, $base, $config;
    $comp = new Emma($raceId);
    if ($split>0) {
        $results = result_by_leg_at_split($class, $comp, $leg, $splits[$class][$leg][$split-1]["code"]);
        $where=$splits[$class][$leg][$split-1]["name"];
    } else {
        $results = result_by_leg($class, $comp, $leg);
        if ($leg==$config[$class]["legs"]) {
            $where="Finish";
        } else {
            $where = "Change-over";
        }
    }
    echo $m->render("total", array("res"=>$results, "class"=>$class, "base"=>$base,
    "config"=>$config,
    "raceId"=>$raceId, "Where"=>$where, "Leg"=>$leg));	
});

$app->get('/:raceId/:class/legresult/:leg', function ($raceId,$class,$leg) {
    global $m, $base, $config;
    $comp = new Emma($raceId);
    $results = result_at_leg($class, $comp, $leg);
    echo $m->render("legresult", array("res"=>$results, "class"=>$class, 
    "config"=>$config,
    "leg"=>$leg, "base"=>$base, "raceId"=>$raceId));	
});  



$app->get('/:raceId/:class/team/:bibNr', function ($raceId,$class,$bib) {
    global $m, $base, $config;
    $start = microtime(true);
    $comp = new Emma($raceId);
    $results = result_by_team($class, $comp, $bib);
#    echo "\n\n".print_r($results[0]["finish"])."\n\n";

    echo $m->render("team", array("res"=>$results, "class"=>$class, "teamId"=>$bib,
    "config"=>$config,
    "base"=>$base, "raceId"=>$raceId, "team"=>$results[0]["finish"]["Club"]));	

    $total = microtime(true)-$start;
    echo "<!-- Time spent: ".$total. "-->\n\n";

});


$app->get('/:raceId/:class/listall', function ($raceId,$class) {
    global $m, $base, $config;
    $comp = new Emma($raceId);
    $results = list_all_result($class, $comp);
	echo $m->render("listall", array("class"=>$class,"res"=>$results, "base"=>$base,
        "config"=>$config));
});


$app->get('/:raceId/:class/teams', function ($raceId,$class) {
    global $m, $base,  $config;
    $comp = new Emma($raceId);
    $results = list_all_teams($class, $comp);
	echo $m->render("teams", array("class"=>$class,"res"=>$results, "base"=>$base, 
        "config"=>$config,
    "raceId"=>$raceId));
});

$app->get('/:raceId/:class/', function ($raceId,$class) {
    global $m, $base, $config, $splits, $menu;
    $comp = new Emma($raceId);

    $menu = make_menu($class);

	echo $m->render("class_overview", 
    array("class"=>$class, "raceId"=>$raceId, "base"=>$base, 
        "config"=>$config, "menu"=>$menu));

});


$app->run();


function result_by_team ($class, $comp, $team) {
    global $splits, $config;
    $legs = $config[$class]["legs"];

#    $res_after_leg = array();
#    $res_at_leg = array();
    $teamres = array();
    for ($i = 1; $i<=$legs; $i++) {
        $res_after_leg = result_by_leg($class, $comp, $i) ;
        $res_at_leg = result_at_leg($class, $comp, $i) ;
        foreach ($res_after_leg as $ra) {
            $ra["Leg"] = $i;
           $teamres[$ra["TeamId"]][$i-1]["finish"]=$ra;
        }
        $numberOfRunners = count($res_at_leg);
        foreach ($res_at_leg as $ra) {
            $ra["NumberOfRunners"] = $numberOfRunners;
            $ra["Leg"] = $i;
            $teamres[$ra["TeamId"]][$i-1]["at_leg"]=$ra;
        }

        if (array_key_exists($i, $splits[$class])) {
            $splitNr = 1;
            foreach ($splits[$class][$i] as $splitCode) {
                $res_at_split = result_by_leg_at_split($class, $comp, $i, $splitCode["code"]) ;
                foreach ($res_at_split as $ra) {
                    $ra["SplitName"] = $splitCode["name"];
                    $ra["Leg"] = $i;
                    $ra["SplitNr"] = $splitNr;
                    $teamres[$ra["TeamId"]][$i-1]["splits"][$splitNr-1]=$ra;
                }
                $splitNr++;
            }
        }

    }
//    echo "<br> ".print_r($legres);
    return $teamres[$team];
}

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
                if ($n>1) $restart_flag= $row["Restarts"];
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

function result_at_leg ($class, $comp, $leg) {

    $q = "select relay_teamid, Name, relay_leg_time as Time, ru.Club, re.Status from Results re, Runners ru WHERE re.dbid=ru.dbid and re.tavid=".$comp->m_CompId." and ru.tavid=".$comp->m_CompId." and relay_leg=".$leg." and class like '".$class."-_' AND control=1000 ORDER by status, relay_leg_time";

    if ($result = mysql_query($q,$comp->m_Conn)) {
        $results = array();
        $rank = 1;
        $besttime = 0;
        $prevtime = 0;
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
            
	    if ($row["Status"]>0) { $rank=""; }

            array_push($results, array(
                "Club"=>$row["Club"], "Status"=>$row["Status"],
                "Timestr"=>formatTime($row["Time"], $row["Status"]),
                "TeamId"=>$row["relay_teamid"], "Rank"=>$rank,
                "Behind" => ($delta>0) ? "+".formatTimeSimple($delta,0):"",
                "Name" => $row["Name"] )
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

function list_all_teams ($class, $comp) {
    $q = "SELECT distinct Runners.Club, relay_teamid From Runners,Results where Results.DbID = Runners.DbId AND Results.TavId = ". $comp->m_CompId ." AND Runners.TavId = ".$comp->m_CompId ." AND Runners.Class like '".$class."-_'  ORDER BY relay_teamid";
    
    if ($result = mysql_query($q,$comp->m_Conn)) {
        $results = array();
        
        while ($row = mysql_fetch_array($result)) {
            array_push($results, array(
                "Club"=>$row["Club"],
            "TeamId"=>$row["relay_teamid"]
            )
            );
        }
        
        mysql_free_result($result);
        return $results;
        
    }		else {
        
        die(mysql_error());
    }
 }

    function make_menu ($class) {
        global $config, $splits;
        $legs = $config[$class]["legs"];
        
        $menu = array();
        for ($i = 1; $i<=$legs; $i++) {
            if ($i==$legs) {
                $where="Finish";
            } else { 
                $where="Change over"; 
            }
            $cur = array("finish"=>0, "Leg"=>$i, "Where"=>$where);
            if (array_key_exists($i, $splits[$class])) {
                $splitNr = 1;
                $legsplits = array();
                foreach ($splits[$class][$i] as $splitCode) {
                    array_push($legsplits,array("nr"=>$splitNr, 
                    "SplitWhere"=>$splitCode["name"]));
                    $splitNr++;
                }
            $cur["splits"]=$legsplits;
            }
            $menu[$i-1] = $cur;
        }
        return $menu;
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
