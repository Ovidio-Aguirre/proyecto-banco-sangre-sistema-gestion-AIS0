<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../src/Shared/helpers/utilidades.php';
require_once __DIR__ . '/../src/Shared/contracts/Controlador.php';

spl_autoload_register(function (string $clase) {
    // Módulos: src/<Modulo>/Controllers y src/<Modulo>/Models
    foreach (['Controllers', 'Models'] as $carpeta) {
        $archivos = glob(__DIR__ . "/../src/*/$carpeta/$clase.php");
        if ($archivos) {
            require_once $archivos[0];
            return;
        }
    }
});

// Sesión compartida entre la API y las vistas
session_set_cookie_params([
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

try {
    require_once __DIR__ . '/../app/config/database.php'; // define $conn

    // /proyecto/api/donantes/5 -> ['donantes','5']
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $ruta   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $partes = array_values(array_filter(explode('/', substr($ruta, strlen($base)))));
    $metodo = $_SERVER['REQUEST_METHOD'];

    // Una línea por recurso; el controlador se crea solo si se pide
    $rutas = [
        'donantes' => fn() => new DonanteController(
            new Donante($conn),
            new EvaluacionController(new EvaluacionDonante($conn)),
            new DonacionController(new Donacion($conn))
        ),
        'donaciones'    => fn() => new DonacionController(new Donacion($conn)),
        'inventario'    => fn() => new InventarioController(new Inventario($conn)),
        'hospitales'    => fn() => new HospitalController(new Hospital($conn)),
        'solicitudes'   => fn() => new SolicitudController(new Solicitud($conn)),
        'pacientes'     => fn() => new PacienteController(new Paciente($conn)),
        'auth'          => fn() => new AuthController(new Usuario($conn)),
        'usuarios'      => fn() => new UsuarioController(new Usuario($conn)),
        'roles'         => fn() => new RolController(new Rol($conn)),
        'reportes'      => fn() => new ReporteController(new Reporte($conn)),
    ];

    $recurso = $partes[0] ?? '';
    if (!isset($rutas[$recurso])) {
        noEncontrado();
    }

    // Exigir sesión en todo excepto POST /auth/login
    $esLogin = $recurso === 'auth' && ($partes[1] ?? null) === 'login';
    if (!$esLogin) {
        usuarioActual(); // 401 si no hay sesión

        // Con contraseña temporal solo se permite cambiarla, cerrar sesión o ver el perfil
        $permitida = $recurso === 'auth'
            && in_array($partes[1] ?? '', ['cambiar-contrasena', 'logout', 'perfil'], true);
        if (!empty($_SESSION['debe_cambiar']) && !$permitida) {
            respuesta(403, [
                'success'                 => false,
                'mensaje'                 => 'Debe cambiar su contraseña temporal',
                'debe_cambiar_contrasena' => true
            ]);
        }
    }

    $rutas[$recurso]()->control($metodo, $partes);

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    if ($e->getCode() === 1062) {
        respuesta(409, ['success' => false, 'mensaje' => 'Registro duplicado']);
    }
    respuesta(500, ['success' => false, 'mensaje' => 'Error interno del servidor']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    respuesta(500, ['success' => false, 'mensaje' => 'Error interno del servidor']);
}