<?php
$host="localhost";
$dbname="test";
$dbusername="root";
$dbpassword="";
try{
  $conn = new mysqli($host, $dbusername, $dbpassword , $dbname);
}catch(Exception $e){
      echo"Connection Failed" . $e->getMessage();
}