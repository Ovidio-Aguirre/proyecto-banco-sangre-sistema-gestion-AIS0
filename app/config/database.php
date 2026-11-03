<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Cargar .env (formato CLAVE=valor)
$envFile = __DIR__ . '/../../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $_ENV[trim($clave)] = trim($valor, " \t\"'");
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = (int)($_ENV['DB_PORT'] ?? 3306);
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$db   = $_ENV['DB_NAME'] ?? 'banco_sangre';

$conn = new mysqli($host, $user, $pass, $db, $port);
$conn->set_charset("utf8mb4");
