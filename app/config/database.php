<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$user = "root";
$pass = "";
$db = "bancoSangre";
/*$port = 3306;*/

$conn = new mysqli($host, $user, $pass, $db /*, $port*/);

$conn->set_charset("utf8mb4");

?>