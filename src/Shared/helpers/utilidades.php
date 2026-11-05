<?php
/* --- FUNCIONES JSON --- */
/** 
 * Devuelve una respuesta HTTP en formato JSON 
 * @return void Finaliza la ejecucion del script
 */ 
function respuesta(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lee el contenido de un JSON y lo convierte en array
 * @return array Datos decodificados
 */
function leerJson(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        respuesta(400, ['success' => false, 'mensaje' => 'JSON inválido']);
    }
    return $data;
}

/* --- VALIDACIONES --- */
/**
 * Valida que el ID sea un numero entero positivo, con mensaje personalizado de error
 * @return int ID validado
 */
function validarId(mixed $valor, string $mensaje = "ID invalido") : int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
            respuesta(400, ['success' => false, 'mensaje' => $mensaje]);
    }
    return $id;
}

/**
 * Validar campos definidos con formato entero
 * @return int Valor validado
 */
function validarCampoEntero(mixed $campo): int
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

/**
 * Validar al menos un campo con valor para realizar busqueda 
 * @return void Devuelve respuesta HTTP si todos estan vacios
 */
function validarCamposVacios(array $campos = []) : void{
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

/** 
 * Validar todos los campos obligatorios
 * @return void Devuelve respuesta HTTP si hay campos invalidos
 */
function validarCamposRequeridos(array $data, array $requeridos) : void
{
    $invalidos = [];

    foreach ($requeridos as $campo) {
        if (
            !isset($data[$campo]) ||
            !is_scalar($data[$campo]) ||
            trim((string)$data[$campo]) === ''
        ) {
            $invalidos[] = $campo;
        }
    }

    if (!empty($invalidos)) {
        respuesta(400, [
            'success' => false,
            'mensaje' => 'Campos inválidos o vacíos',
            'campos' => $invalidos
        ]);
    }
}

/**
 * Validar formato de fecha de filtro
 * @param bool @fechaFutura     false: rechaza fechas posteriores a hoy
 * @return string Fecha validada
 */
function validarFecha(mixed $valor, string $campo = 'fecha', bool $fechaFutura = false): string
{
    $fecha = is_string($valor) ? DateTime::createFromFormat('!Y-m-d', $valor) : false;

    if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
        respuesta(400, ['success' => false, 'mensaje' => "$campo inválida, use el formato YYYY-MM-DD"]);
    }
    if (!$fechaFutura && $fecha > new DateTime('today')) {
        respuesta(400, ['success' => false, 'mensaje' => "$campo no puede ser una fecha futura"]);
    }
    return $valor;
}

/* --- CONTROL DE METODOS --- */
function noPermitido(): void
{
    respuesta(405, ['success' => false, 'mensaje' => 'Método no permitido']);
}

function noEncontrado(): void
{
    respuesta(404, ['success' => false, 'mensaje' => 'Ruta no encontrada']);
}

/* --- AUTENTICACION --- */
function usuarioActual(): int
{
    return 1; // LEER SESION
}
