<?php
require 'nh/vendor/autoload.php';
date_default_timezone_set("Europe/Stockholm");
$lang = "en";
if (isset($_GET['lang'])) $lang = $_GET['lang'];

echo "*";

include_once("../templates/emmalang_en.php");
include_once("../templates/emmalang_$lang.php");
include_once("../templates/classEmma.class.php");

$RunnerStatus = Array("1" =>  $_STATUSDNS, "2" => $_STATUSDNF, "11" =>  $_STATUSWO, "12" => $_STATUSMOVEDUP, "9" => $_STATUSNOTSTARTED,"0" => $_STATUSOK, "3" => $_STATUSMP, "4" => $_STATUSDSQ, "5" => $_STATUSOT, "9" => "", "10" => "");

header('content-type: text/html'); # ; charset='.$CHARSET);
header('Cache-Control: max-age=10');

$m = new Mustache_Engine(array(
    'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views'),
    'escape' => function($value) {
        return htmlspecialchars($value, ENT_COMPAT, 'ISO-8859-1');},
));

$base = "/emmanh/nh";
$app = new \Slim\Slim(array('debug'=>true));


$app->get('/:raceId/:class/legresult/:leg', function ($raceId,$class,$leg) {
    global $m, $base, $config;
    $comp = new Emma($raceId);
    $results = result($class, $comp);
    echo $m->render("display_class", array("res"=>$results, "class"=>$class, 
    "config"=>$config,
    "base"=>$base, "raceId"=>$raceId));	
});  

$app->get('/', function () {
    global $m	      , $config;
    echo $m->render("display_index", "config"=>$config);
});

$app->run();



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