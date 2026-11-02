<?php
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

function noPermitido(): void
{
    respuesta(405, ['success' => false, 'mensaje' => 'Método no permitido']);
}

function noEncontrado(): void
{
    respuesta(404, ['success' => false, 'mensaje' => 'Ruta no encontrada']);
}

//=====================================================================

function validarCampoEntero($campo): int
{
    $valor = filter_var($campo, FILTER_VALIDATE_INT);

    if ($valor === false || $valor <= 0) {
        respuesta(400, [
            'success' => false,
            'mensaje' => 'Campo inválido'
        ]);
    }

    return $valor;
}
function validarCamposVacios($campos = []){
    $vacio = true;
    foreach($campos as $campo){
        if($campo !== '' && $campo !== NULL){
            $vacio = false; break;
        }
    }
    if($vacio){
        respuesta(400, ['success' => false, 'Mensaje:' => 'Campo vacio']);
    }
}

function validarFecha($fecha){
    $fechaValidada = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$fechaValidada || $fechaValidada->format('Y-m-d') !== $fecha || $fechaValidada > new DateTime('today')){
        respuesta(400, ['success' => false, 'Mensaje:' => 'Tipo de fecha ingresada no es valida']);
    }
}
