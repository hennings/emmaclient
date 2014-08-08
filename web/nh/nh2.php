<?php
require 'vendor/autoload.php';
date_default_timezone_set("Europe/Stockholm");
$lang = "en";
if (isset($_GET['lang'])) $lang = $_GET['lang'];


include_once("../templates/emmalang_en.php");
include_once("../templates/emmalang_$lang.php");
include_once("../templates/classEmma.class.php");

$RunnerStatus = Array("1" =>  $_STATUSDNS, "2" => $_STATUSDNF, "11" =>  $_STATUSWO, "12" => $_STATUSMOVEDUP, "9" => $_STATUSNOTSTARTED,"0" => $_STATUSOK, "3" => $_STATUSMP, "4" => $_STATUSDSQ, "5" => $_STATUSOT, "9" => "", "10" => "");

header('content-type: text/html; charset='.$CHARSET);
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
        1=>array(
	     array("code"=>"1111","name"=>"2.3 km")),
        2=>array(
	     array("code"=>"1111","name"=>"2.5 km")),
        3=>array(
	     array("code"=>"1111","name"=>"2.5 km")),
        4=>array(
	     array("code"=>"1111","name"=>"3.4 km")),

        5=>array(
		 array("code"=>"1121","name"=>"1.2 km", "hidden"=>true),
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

$app->get('/:raceId/:class/passing/:code', function ($raceId,$class, $code) {
    global $m, $base, $config;
    $comp = new Emma($raceId);
    $results = list_passing($class, $comp, $code);
    echo $m->render("passing", array("class"=>$class,"res"=>$results, "base"=>$base, "code"=>$code,
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

$app->get('/:raceId/:class/speakermenu', function ($raceId,$class) {
    global $m, $base, $config, $splits, $menu;
    $comp = new Emma($raceId);

    $menu = make_menu($class);

	echo $m->render("speaker_menu", 
    array("class"=>$class, "raceId"=>$raceId, "base"=>$base, 
        "config"=>$config, "menu"=>$menu));

});

$app->get('/:raceId/:class/superspeaker', function ($raceId,$class) {
    global $m, $base, $config, $splits, $menu, $app;
    $comp = new Emma($raceId);

    list($results, $list_of_points) = calc_superspeaker($class, $comp);

    echo $m->render("speaker_res", 
    array("class"=>$class, "raceId"=>$raceId, "base"=>$base, 
    "config"=>$config, "res"=>$results, "points"=>$list_of_points));


});


$app->run();



function calc_superspeaker ($class, $comp) 
{
    global $m, $base, $config, $splits, $menu, $app;

    $tavid = $comp->m_CompId;
    $q = "";
    $legs = $config[$class]["legs"];
    $list_of_points = array();
    for ($i = 1; $i<=$legs; $i++) {
        $from_control = $to_control = 0;
        $from = $app->request->params("from-".$i);
        $to = $app->request->params("to-".$i);
        if ($from!='skip' && $to!='skip') {
	    $from_control = find_station_code($from);
	    $to_control = find_station_code($to);
        }

        if ($from_control>0 && $to_control>0) {

            array_push ($list_of_points, array(
                "leg"=>$i,
                "from"=>$from_control,
                "to"=>$to_control,
                "from_name" => find_station_name($class, $i, $from_control),
                "to_name" => find_station_name($class, $i, $to_control)
            ));
            

            if (strlen($q)>0) {
                $q = $q." union all ";
            }
            $q=$q." select ru.Name, ru.Club, fromres.relay_leg, fromres.relay_teamid, ifnull(tores.relay_leg_time,0) - ifnull(fromres.relay_leg_time,0) as legtime,  tores.relay_leg_time as totime, fromres.relay_leg_time as fromtime from (select dbid, control, relay_teamid,relay_leg_time from Results where control=".$to_control." and tavid=".$tavid." and  relay_leg=".$i.") as tores, (select dbid, control, relay_teamid,relay_leg_time, relay_leg from Results where control=".$from_control." and tavid=".$tavid." and relay_leg=".$i.") as fromres, Runners ru where tores.dbid=fromres.dbid and fromres.dbid=ru.dbid and ru.tavid=".$tavid." and ru.class like '".$class."-%'";
        }

#        echo "$i / $legs ($from_control - $to_control)<br/>\n";
    }
        $q2 = "SELECT r.relay_teamid as TeamId, Club, sum(legtime) as Legtime FROM ( ".$q." ) as r GROUP by relay_teamid, club ORDER by count(legtime) DESC, sum(legtime)  ";

        $allRes = array();
        if ($result0 = mysql_query($q." order by relay_teamid, relay_leg",$comp->m_Conn)) {
            while ($row = mysql_fetch_array($result0)) {
                $allRes[$row["relay_teamid"]][$row["relay_leg"]]=array(
                    "Name"=>$row["Name"], "Legtime"=>$row["legtime"]);
            }
            mysql_free_result($result0);
        }


    if ($result = mysql_query($q2,$comp->m_Conn)) {

        $data = array();
        while ($row = mysql_fetch_array($result)) {
            $teamId =$row["TeamId"];
            for ($i=1; $i<=8;$i++) {
                if (array_key_exists($i, $allRes[$teamId])) {
                    if (!isset($row["LegRes"])) {
                        $row["LegRes"] = array();
                    }
                    array_push($row["LegRes"], array(
                        "Leg"=>$i, "Name"=>$allRes[$teamId][$i]["Name"],
                        "Legtime"=>
                        formatTimeSimple($allRes[$teamId][$i]["Legtime"],0)));
                }
            }
            array_push ($data, $row);
        }
        mysql_free_result($result);

        $results = decorate_resultlist($data, "LegTime");
        
    }
    return array($results, $list_of_points);

}


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
  if ($leg>1) {
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
} else {
    $q = "
select relay_teamid, Runners.Club, Status , Status as LegStatus, Name,
  relay_restarts as Restarts, relay_leg_time as Time, 0 as NumFinish, relay_leg_time as SplitTime
  FROM Runners,Results where Results.DbID = Runners.DbId 
    and Results.TavId = ". $comp->m_CompId ."
    and Runners.TavId = ".$comp->m_CompId ." AND Runners.Class like '".$class."-_' 
    and Control=".$split." AND relay_leg=1 
  ORDER BY status,NumFinish desc,restarts, time
";

}
  //	echo $q;

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
		"Name" => $row["Name"], 
		"LegTime"=>formatTime($row["SplitTime"],0).
		" ".status($row["LegStatus"])
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


function list_passing ($class, $comp, $code) {
  $q = "SELECT  Runners.Name, Runners.Club, Results.Time, Results.Status, Results.Changed, Results.DbID, Results.Control, relay_restarts, relay_teamid, relay_leg, relay_leg_time, relay_timestamp From Runners,Results where Results.DbID = Runners.DbId AND Results.TavId = ". $comp->m_CompId ." AND Runners.TavId = ".$comp->m_CompId ." AND Runners.Class like '".$class."-_' AND Control=".(0+$code)." ORDER BY relay_timestamp desc, relay_teamid, relay_leg";
    
    if ($result = mysql_query($q,$comp->m_Conn)) {
        $results = array();
        
        while ($row = mysql_fetch_array($result)) {
            array_push($results, array("Name"=>$row["Name"],
	        "Club"=>$row["Club"], "Status"=>$row["Status"],
            "Timestr"=>formatTime($row["relay_leg_time"], $row["Status"]),
				       "Timestamp"=>formatExcel($row["relay_timestamp"]),
				       "Ts"=>($row["relay_timestamp"]),
            "Restarts"=>$row["relay_restarts"],
            "TeamId"=>$row["relay_teamid"],
				       "Leg"=>$row["relay_leg"],
				       "Stnr"=>$row["relay_leg"]*100+$row["relay_teamid"]
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
		  $hidden = false;
		  if (array_key_exists("hidden", $splitCode)) { $hidden=true; }
		  array_push($legsplits,array("nr"=>$splitNr, "hidden"=>$hidden,
		    "code"=>$splitCode["code"],
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
function formatExcel($time) {
  $ts = mktime(0,0, (($time-floor($time))*60*60*24),1,$time-1.0,1900); 
  return gmdate("d/m/Y H:i:s",$ts);
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

function find_station_code($from) {
	  $from_control=-1;
            if ($from=='start') $from_control = 100;
            if ($from=='finish') $from_control = 1000;
            if ($from!='start' && $from!='finish' && $from>0) { 
	       $from_control = $from;
            }
	    return $from_control;
}

function find_station_name($class, $leg, $control) {
    global $splits;
    if ($control == 1000) return "Change-over";
    if ($control == 100) return "Start";
    foreach ($splits[$class][$leg] as $p) {
        if ($p["code"] == $control) {
            return $p["name"];
        }
    }
    return "n/a";
}


function decorate_resultlist($data, $name) {
    $n=0; $rank = 0;
    $prevtime = $besttime = $delta = 0 ;
    $results = array();
    foreach ($data as $row) {
        $n++;
        if ($n==1) { 
            $besttime = $row["Legtime"]; 
        } else {
            $delta = $row["Legtime"] - $besttime; 
        }

        if ($row["Legtime"]!=$prevtime) {
            $rank = $n;
            $prevtime = $row["Legtime"];
        }
        array_push ($results, array(
            "Club"=>$row["Club"],
            "Timestr"=>formatTime($row["Legtime"],0), "Rank"=>$rank,
            "Behind" =>($delta>0) ? "+".formatTimeSimple($delta,0):"",
            "TeamId"=>$row["TeamId"],
            "LegRes"=>$row["LegRes"]
        ));
    }
    return $results;
}


?>
