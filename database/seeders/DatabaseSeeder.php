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

    $conn->begin_transaction();

    // Catálogos
    seedRoles($conn);
    seedTiposSangre($conn);

    // División territorial
    seedDepartamentos($conn);
    seedMunicipios($conn);
    seedDistritos($conn);

    // Información del sistema
    seedHospitales($conn);
    seedUsuarios($conn);

    // Registros de prueba
    seedDonantes($conn);
    seedPacientes($conn);
    seedEvaluacionesDonante($conn);
    seedSolicitudes($conn);
    seedDonaciones($conn);

    $conn->commit();

    echo "Todos los seeders se ejecutaron correctamente.\n";

} catch (Throwable $e) {

    $conn->rollback();

    echo "Error: " . $e->getMessage() . "\n";
}