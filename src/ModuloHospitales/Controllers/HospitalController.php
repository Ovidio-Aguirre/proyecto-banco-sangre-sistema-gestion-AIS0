<?php
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . "/../models/Hospital.php";

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== METODO INGRESADO ==========
    try {
        require_once __DIR__ . "/../../../app/config/database.php"; 
        $modelo = new Hospital($conn);
        $metodo = $_SERVER['REQUEST_METHOD'];

        //Obtenemos el metodo por el cual fue llamado
        switch ($metodo) {
            case 'GET':
                buscarHospital($modelo);
                break;
            case 'POST':
                ingresarNuevoHospital($modelo);
                break;
            case 'PUT':
                modificarHospital($modelo);
                break;
            case 'DELETE':
                eliminarHospital($modelo);
                break;
            default:
                respuesta(405, ['success' => false, 'mensaje' => 'Método no permitido']);
        }
    } 
    catch (Throwable $e) {
        error_log($e->getMessage());
        respuesta(500, ['success' => false, 'mensaje' => 'Error interno del servidor']);
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== GET ==========
    //----------Funcion que devuelve el tipo de busqueda realizada----------
    function buscarHospital(Hospital $modelo): void {
        //Busqueda por ID
        if(isset($_GET["idHospital"])){
            $id = trim($_GET["idHospital"] ?? NULL);
            validacionID($id);

            $resultado = $modelo->buscarPorID(
                $id
            );
        }

        //Busqueda por Nombre, departamento, municipio, distrito o orden
        else if(isset($_GET["nombreHospital"])||
        isset($_GET["departamentoHospital"])||
        isset($_GET["municipioHospital"])||
        isset($_GET["distritoHospital"])|| 
        isset($_GET["ordenHospital"])){

            $nombre = trim($_GET["nombreHospital"] ?? '');
            $departamento = trim($_GET["departamentoHospital"] ?? '');
            $municipio = trim($_GET["municipioHospital"] ?? '');
            $distrito = trim($_GET["distritoHospital"] ?? '');
            $orden = trim($_GET["ordenHospital"] ?? '');
            $orden = strtoupper($orden);

            validacionCamposVacios([$nombre, $departamento, $municipio, $distrito, $orden]);

            //Si existe orden
            if($orden){
                if($orden !== 'ASC' && $orden !== 'DESC'){
                    respuesta(400, ['success' => false, 'error' => 'Tipo de orden invalido']);
                    exit;
                }
            } 

            if($departamento){validacionCampoEntero($departamento);}
            if($municipio){validacionCampoEntero($municipio);}
            if($distrito){validacionCampoEntero($distrito);}

            //Si se encuentra el campo
            $resultado = $modelo->buscarPorFiltros(
                $nombre,
                $departamento,
                $municipio,
                $distrito,
                $orden
            );
        }

        //Busqueda de todos los hospitales
        else{
            $resultado = $modelo->listarHospitales();
        }
        
        if($resultado){
            respuesta(200, ['success' => true, 'Hospitales' => $resultado]);
        }
        else{
            respuesta(400, ['success' => false, 'error' => 'No se encontro ningun hopital o se encuentra eliminado']);
        }
        
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== POST ==========
    function ingresarNuevoHospital(Hospital $modelo): void {

        $datosHospital = leerJson();

        $nombre = $datosHospital['nombreHospital'] ?? null;
        $direccion = $datosHospital['direccionHospital'] ?? null;
        $distrito = $datosHospital['distritoHospital'] ?? null;

        //Si alguno de los campos ingresados esta vacio
        if ( $nombre === '' || $nombre === NULL || $direccion === '' || $direccion === NULL || $distrito === ''|| $direccion === NULL ) {
            respuesta(400, ['success' => false, 'Mensaje:' => 'Todos los campos son abligatorios']);
            exit;
        }

        validacionCampoEntero($distrito);

        //Si es correcto
        $resultado = $modelo->agregarNuevoHospital(
            $nombre,
            $direccion,
            $distrito
        );

        if($resultado === 0){
            respuesta(201, ['success' => true, 'Mensaje:' => 'Nuevo Hospital ingresado Exitosamente']);
        }
        else if($resultado === 2){
            respuesta(409, ['success' => false, 'Mensaje:' => 'ID de distrito invalida']);
        }
        else{
            respuesta(409, ['success' => false, 'Mensaje:' => 'Este Hospital ya se encuentra registrado']);
        }
        
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== PUT ==========
    function modificarHospital(Hospital $modelo): void {

        $datosHospital = leerJson();

        //pasamos los datos a variables
        $id = $datosHospital['idHospital'] ?? null;
        $nombre = $datosHospital['nombreHospital'] ?? null;
        $direccion = $datosHospital['direccionHospital'] ?? null;
        $distrito = $datosHospital['distritoHospital'] ?? null;

        validacionID($id);
        validacionCampoEntero($distrito);
        validacionCamposVacios([$nombre]);
        validacionCamposVacios([$direccion]);

        $resultado = $modelo->actualizarHospital(
            $id,
            $nombre,
            $direccion,
            $distrito
        );

        //Segun la respuesta mostramos el mensaje
        switch($resultado){
            case 1:
                respuesta(404, ['success' => false, 'Mensaje:' => 'ID no existe']);
                break;
            case 2:
                respuesta(404, ['success' => false, 'Mensaje:' => 'ID municipio no existe']);
                break;
            default:
                respuesta(200, ['success' => true, 'Mensaje:' => 'Hospital Actualizado correctamente']);
        }
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== DELETE ==========
    function eliminarHospital(Hospital $modelo): void {
        $id = $_GET["idHospital"] ?? null;

        validacionID($id);

        $resultado = $modelo->eliminar($id);

        if($resultado){
            respuesta(200, ['success' => true, 'Mensaje:' => $resultado]);
        }
        else{
            respuesta(404, ['success' => false, 'Mensaje:' => 'Hospital no encontrado o eliminado']);
        }
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== RESPUESTA ==========
    //Se muestra la respuesta obtenida en un JSON
    function respuesta(int $codigo, array $datos): void {
        http_response_code($codigo);
        echo json_encode($datos);
        exit;
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== VALIDACIONES ==========
    function validacionID($id){
        if($id === null || !filter_var($id, FILTER_VALIDATE_INT) || $id <= 0){
            respuesta(400, ['success' => false, 'Mensaje:' => 'ID del hospital invalido']);
        }
    }

    function validacionCampoEntero($campo){
        if(!filter_var($campo, FILTER_VALIDATE_INT) || $campo <= 0){
            respuesta(400, ['success' => false, 'error' => 'Campo invalido']);
        }
    }

    function validacionCamposVacios($campos = []){
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

    function leerJson(): array{
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            respuesta(400, ['success' => false, 'mensaje' => 'JSON inválido']);
        }
        return $data;
    }
?>