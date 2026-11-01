<?php
header('Content-Type: application/json');
require_once __DIR__ . "/../Models/Donante.php"; 

/* --- UTILIDADES --- */
// Devuelve la respuesta JSON
function respuesta(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Lee el body y responde 400 si es invalido
function leerJson(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        respuesta(400, ['success' => false, 'mensaje' => 'JSON inválido']);
    }
    return $data;
}

// Leer y validar IDs
function validarId(mixed $valor, string $mensaje = "ID invalido") : int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
            respuesta(400, ['success' => false, 'mensaje' => $mensaje]);
    }
    return $id;
}

// Valida los parametros del donante
function validarDonante(array $data): array
{
    $requeridos = ['id_tipo_sangre', 'dui', 'nombre', 'apellido', 'contacto', 'fecha_nacimiento', 'sexo'];
        
    /* --- Validaciones --- */

    // Campos obligatorios vacios
    $vacios = [];
    foreach ($requeridos as $campo){
        if (!isset($data[$campo]) || !is_scalar($data[$campo]) || trim((string)$data[$campo]) === ''){
            $vacios[] = $campo;
        }
    }

    if ($vacios){
        respuesta(400, ['success' => false, 'mensaje' => 'Campos vacios', 'campos' => $vacios]);
    }
    
    // Formato
    $idTipoSangre = filter_var($data['id_tipo_sangre'], FILTER_VALIDATE_INT);
    if ($idTipoSangre === false || $idTipoSangre <=0){
            respuesta(400, ['success' => false, 'mensaje' => 'Tipo de sangre inválido']);
    }

    $dui = trim((string)$data['dui']);
    if (!preg_match('/^\d{8}-\d$/', $dui)) {
        respuesta(400, ['success' => false, 'mensaje' => 'DUI inválido, use ########-#']);
    }

    $contacto = trim((string)$data['contacto']);
    if (!preg_match('/^\d{4}-\d{4}$/', $contacto)) {
        respuesta(400, ['success' => false, 'mensaje' => 'Numero inválido, use ####-####']);
    }

    if (!in_array($data['sexo'], ['M', 'F'], true)) {
        respuesta(400, ['success' => false, 'mensaje' => 'Sexo debe ser M o F']);
    }

    $fechaNacimiento = (string)$data['fecha_nacimiento'];
    $fecha = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
    if (!$fecha || $fecha->format('Y-m-d') !== $fechaNacimiento || $fecha > new DateTime('today')) {
        respuesta(400, ['success' => false, 'mensaje' => 'Fecha inválida, utilice formato YYYY-MM-DD']);
    }

    return [
        'id_tipo_sangre'    => $idTipoSangre,
        'dui'               => $dui,
        'nombre'            => trim((string)$data['nombre']),
        'apellido'          => trim((string)$data['apellido']),
        'contacto'          => $contacto,
        'fecha_nacimiento'  => $fechaNacimiento,
        'sexo'              => $data['sexo'],
    ]; 
}

/* --- PETICIONES --- */

// GET
function consultarRegistros(Donante $modelo): void 
{
    //Caso 1. Obtener por ID
    if (isset($_GET['id_donante'])) {
        $idDonante = validarId($_GET['id_donante'], 'ID de donante inválido');

        $donante = $modelo->obtenerDonantePorId($idDonante);
        if ($donante === null) {
            respuesta(404, ['success' => false, 'mensaje' => 'Donante no encontrado']);
        }
        respuesta(200, ['success' => true, 'donante' => $donante]);
    }

    //Caso 2. Filtrar registros o Listar todos
    $busqueda = null;   
    if (isset($_GET['busqueda'])) {
        $busqueda =  is_string($_GET['busqueda']) ? trim($_GET['busqueda']) : ''; 
        
        // Validacion: Busqueda vacia
        if ($busqueda === '') {
            respuesta(400, ['success' => false, 'mensaje' => 'La búsqueda no puede estar vacía']);
        }
    }

    $idTipoSangre = null;
    if (isset($_GET['id_tipo_sangre'])) {
       $idTipoSangre = validarId($_GET['id_tipo_sangre'], 'Tipo de sangre inválido');
    }

    $ordenDesc = false;
    if (isset($_GET['orden'])) {
        $orden = is_string($_GET['orden']) ? strtolower($_GET['orden']) : '';
        if (!in_array($orden, ['asc', 'desc'], true)) {
            respuesta(400, ['success' => false, 'mensaje' => 'Orden debe ser asc o desc']);
        }
        $ordenDesc = ($orden === 'desc');
    }

    // Listar donantes que coincidan
    $donantes = $modelo->buscarDonantes($busqueda, $idTipoSangre, $ordenDesc); 
    respuesta(200, ['success' => true, 'donantes' => $donantes]);
}

// POST
function crearDonante(Donante $modelo): void  
{
    $data = validarDonante(leerJson());

    //Crear registro
    $res = $modelo->crearDonante(
        $data['id_tipo_sangre'],
        $data['dui'],
        $data['nombre'],
        $data['apellido'],
        $data['contacto'],
        $data['fecha_nacimiento'],
        $data['sexo']
    );

    switch ($res['estado']) {
    case 'duplicado':
        respuesta(409, ['success' => false, 'mensaje' => 'El DUI ya está registrado']);
    case 'conflicto_medico':
        respuesta(409, ['success' => false, 'mensaje' => 'El DUI pertenece a donante inactivo y sus datos no coinciden. Verifique la informacion.']);
    case 'reactivado':
        respuesta(200, ['success' => true, 'id_donante' => $res['id_donante'], 'mensaje' => 'Donante reactivado']);
    default:
        respuesta(201, ['success' => true, 'id_donante' => $res['id_donante']]);
    }
}

// PUT ?id_donante
function actualizarDonante(Donante $modelo): void {
    $idDonante = validarId($_GET['id_donante'] ?? null, 'ID de donante inválido');
    $data = validarDonante(leerJson());

    //Actualizar registro
    $res = $modelo->actualizarDonante(
        $idDonante,
        $data['id_tipo_sangre'],
        $data['dui'],
        $data['nombre'],
        $data['apellido'],
        $data['contacto'],
        $data['fecha_nacimiento'],
        $data['sexo']
    );
    
    if ($res === false){
        respuesta(404, ['success' => false, 'mensaje' => 'Donante no encontrado o inactivo.']);
    }
    if ($res === null){
        respuesta(409, ['success' => false, 'mensaje' => 'El DUI pertenece a otro donante.']);
    }
    respuesta(200, ['success' => true, 'donante' => $modelo->obtenerDonantePorId($idDonante), 'mensaje' => 'Registro actualizado.']);
}

// PATCH ?id_donante {"activo": true|false}
function cambiarEstado(Donante $modelo) : void
{
    $idDonante = validarId($_GET['id_donante'] ?? null, 'ID de donante inválido');
    $data = leerJson();
    
    //Validar el campo activo
    if (!array_key_exists('activo', $data)) {
        respuesta(400, ['success' => false, 'mensaje' => 'Falta el campo activo']);
    }

    $activo = filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($activo === null) {
        respuesta(400, ['success' => false, 'mensaje' => 'Estado activo debe ser true o false']);
    }

    if (!$modelo->cambiarEstadoDonante($idDonante, $activo)) {
        respuesta(404, ['success' => false, 'mensaje' => 'Donante no encontrado']);
    }

    respuesta(200, ['success' => true, 'mensaje' => $activo ? 'Donante activo' : 'Donante inactivo']);
}

try {
    require_once __DIR__ . "/../../../app/config/database.php"; 
    $modelo = new Donante($conn);
    $metodo = $_SERVER['REQUEST_METHOD'];

    switch ($metodo) {
        case 'GET':
            consultarRegistros($modelo);
            break;
        case 'POST':
            crearDonante($modelo);
            break;
        case 'PUT':
            actualizarDonante($modelo);
            break;
        case 'PATCH':
            cambiarEstado($modelo);
            break;
        default:
            respuesta(405, ['success' => false, 'mensaje' => 'Método no permitido']);
    }
} catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        if ($e->getCode() === 1062) {
            respuesta(409, ['success' => false, 'mensaje' => 'El DUI ya está registrado']);
        }
        respuesta(500, ['success' => false, 'mensaje' => 'Error interno del servidor']);
} catch (Throwable $e) {
        error_log($e->getMessage());
        respuesta(500, ['success' => false, 'mensaje' => 'Error interno del servidor']);
}