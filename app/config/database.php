<?php

$host = "localhost";
$user = "root";
$pass = "";
$db = "bancoSangre";
/*$port = 3306;*/

$conn = new mysqli($host, $user, $pass, $db /*, $port*/);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'mensaje' => 'Error de conexión']));
}

?>