<?php
require 'nh/vendor/autoload.php';
date_default_timezone_set("Europe/Stockholm");
$lang = "en";
if (isset($_GET['lang'])) $lang = $_GET['lang'];


include_once("templates/emmalang_en.php");
include_once("templates/emmalang_$lang.php");
include_once("templates/classEmma.class.php");

$RunnerStatus = Array("1" =>  $_STATUSDNS, "2" => $_STATUSDNF, "11" =>  $_STATUSWO, "12" => $_STATUSMOVEDUP, "9" => $_STATUSNOTSTARTED,"0" => $_STATUSOK, "3" => $_STATUSMP, "4" => $_STATUSDSQ, "5" => $_STATUSOT, "9" => "", "10" => "");

header('content-type: text/html'); # ; charset='.$CHARSET);
header('Cache-Control: max-age=10');

$m = new Mustache_Engine(array(
    'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/nh/views'),
    'escape' => function($value) {
        return htmlspecialchars($value, ENT_COMPAT, 'ISO-8859-1');},
));

$app = new \Slim\Slim(array('debug'=>true));


$app->get('/:raceId/results/:class', function ($raceId,$class) {
    global $m, $base, $config;
    $comp = new Emma($raceId);
    $results = result($class, $comp);
    $date = gmdate("H:i:s");


    echo $m->render("display_header", array("raceId"=>$raceId, "now"=>$date));
    echo $m->render("display_result", array("results"=>$results, "class"=>$class, 
    "config"=>$config,
    "base"=>$base, "raceId"=>$raceId));	
    echo $m->render("display_footer");
});  

$app->get('/:raceId/multiresult', function ($raceId) {
    global $m, $base, $config, $app;
    $comp = new Emma($raceId);
    $classes = $app->request->params("class");

    $allclasses = $comp->Classes();

    if (count($classes)==0) {
      $classes = array();
      foreach ($allclasses as $cl) {
	array_push($classes, $cl[0]);
      }
    }

    $date = date("H:i:s");
    echo $m->render("display_header", array("raceId"=>$raceId, "now"=>$date));
    if (count($classes)>0) {
        foreach ($classes as $class) {
            $results = result($class, $comp);
            echo $m->render("display_result", array("results"=>$results, "class"=>$class, 
            "config"=>$config,"now"=>$date,
            "base"=>$base, "raceId"=>$raceId));	
        }
    }else { echo "No classes selected."; }
    echo $m->render("display_result_scroll", array(
        "speed"=>$app->request->params("speed"),         "wait"=>$app->request->params("wait")
    ));
    echo $m->render("display_footer");

});  


$app->get('/:raceId/list', function ($raceId) {
    global $m, $base, $config;
    $comp = new Emma($raceId);
    $classes = $comp->Classes();

    echo $m->render("display_classes", array("classes"=>$classes,
    "config"=>$config, "race"=>$comp->CompName(),
    "base"=>$base, "raceId"=>$raceId));	
});  

$app->get('/', function () {
    global $m, $config;
    $races = Emma::GetCompetitions();
    echo $m->render("display_index", array(
        "config"=>$config, "races"=>$races));
});

$app->run();





function result ($class, $comp) {

    $q = "select Name, Time, ru.Club, re.Status from Results re, Runners ru WHERE re.dbid=ru.dbid and re.tavid=".$comp->m_CompId." and ru.tavid=".$comp->m_CompId."  and class like '".$class."' AND control=1000 ORDER by status, Time";

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
                "Timestr"=>formatTimeSimple($row["Time"], $row["Status"]),
                "Rank"=>$rank,
                "Behind" => ($row["Status"]==0 && $delta>0) ? "+".formatTimeSimple($delta,0):"",
                "Name" => $row["Name"] )
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
//    if ($sec<1) { return ""; }
    if ($sec<60) {
        return gmdate("s",$sec);
    } else if ($sec<60*60) {
        return gmdate("i:s",$sec);
    } 
    return gmdate("H:i:s",$sec);
}



?>