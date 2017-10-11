<?php
  require_once 'includes/Mlib.php';
  header('Content-type: text/html');
  header('Access-Control-Allow-Origin: *');
  $db = new DB();

  /* Removes everything past a space, fixes problem with iOS requests */
//  print_r($_REQUEST);
  end($_REQUEST); 
  $arend =  $_REQUEST[key($_REQUEST)];
  $arend = explode(" ",$arend) ;
  $arend = $arend[0] ;
  $_REQUEST[key($_REQUEST)] = $arend ;
  reset($_REQUEST) ; 
//  echo "<br>Last element is " . $arend . "<br>" ;
//  print_r($_REQUEST);
  
  // get the command
  $command = $_REQUEST['command'];


  // determine which command will be run
  if ($command == "users") {
	echo $db->getUsers();
  }
  elseif ($command == "checkUname") {
	$name = $_REQUEST['namein'];
	$email= $_REQUEST['emailin'];
	echo $db->getUname(urldecode($name),urldecode($email));
  }
  elseif ($command == "getX") {
	$uid = $_REQUEST['uid'];
//	echo "uid is $uid";
	echo $db->getXactions($uid);
  }
  elseif($command == "getReqs") {
	echo $db->getRequests();
  }
  elseif($command == "putReq") {
	$name = $_REQUEST['username'];
	$amt  = $_REQUEST['amount'];
	$cat  = $_REQUEST['category'];
	$cur  = $_REQUEST['currency'];
	$date = $_REQUEST['date'];
	echo $db->putRequest(urldecode($name),urldecode($amt),urldecode($cat),urldecode($cur));
  }
  elseif($command == "status") {
	echo $db->getStatus();
  }
  elseif($command == "getCon") {
	$uid = $_REQUEST['uid'];
	echo $db->getCon($uid);
  }
  elseif($command == "putX") {
	$name = $_REQUEST['username'];
	$amt  = $_REQUEST['amount'];
	$cat  = $_REQUEST['category'];
	$cur  = $_REQUEST['currency'];
	echo $db->putX(urldecode($name),urldecode($amt),urldecode($cat),urldecode($cur));
  }
  else
    echo "command was not recognized";
?>
 
