<?php

require_once __DIR__ . "/../../app/config/database.php";

// Catálogos iniciales
require_once __DIR__ . "/RolesSeeder.php";
require_once __DIR__ . "/TipoSangreSeeder.php";

// División territorial
require_once __DIR__ . "/DepartamentosSeeder.php";
require_once __DIR__ . "/MunicipiosSeeder.php";
require_once __DIR__ . "/DistritosSeeder.php";

// Información del sistema
require_once __DIR__ . "/HospitalesSeeder.php";
require_once __DIR__ . "/UsuariosSeeder.php";

// Registros de prueba
require_once __DIR__ . "/DonantesSeeder.php";
require_once __DIR__ . "/PacientesSeeder.php";
require_once __DIR__ . "/EvaluacionesDonanteSeeder.php";
require_once __DIR__ . "/SolicitudesSeeder.php";
require_once __DIR__ . "/DonacionesSeeder.php";

try {

    $pdo->beginTransaction();

    // Catálogos
    seedRoles($pdo);
    seedTiposSangre($pdo);

    // División territorial
    seedDepartamentos($pdo);
    seedMunicipios($pdo);
    seedDistritos($pdo);

    // Información del sistema
    seedHospitales($pdo);
    seedUsuarios($pdo);

    // Registros de prueba
    seedDonantes($pdo);
    seedPacientes($pdo);
    seedEvaluacionesDonante($pdo);
    seedSolicitudes($pdo);
    seedDonaciones($pdo);

    $pdo->commit();

    echo "\nTodos los seeders se ejecutaron correctamente.";

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\nError: " . $e->getMessage();
}