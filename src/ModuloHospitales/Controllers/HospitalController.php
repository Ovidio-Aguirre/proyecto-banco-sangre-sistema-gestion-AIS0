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
            $id = trim($_GET["idHospital"]) ?? NULL;
            //Si la busqueda esta vacia
            if($id === ''){
                respuesta(200, ['success' => false, 'Mensaje:' => 'Campo de ID vacio']);
            }
            //Si es correcto
            $resultado = $modelo->buscarPorID(
                $id
            );
        }

        //Busqueda por Nombre, departamento, municipio o distrito
        else if(isset($_GET["nombreHospital"])||
        isset($_GET["departamentoHospital"])||
        isset($_GET["municipioHospital"])||
        isset($_GET["distritoHospital"])){
            $nombre = trim($_GET["nombreHospital"] ?? '');
            $departamento = trim($_GET["departamentoHospital"] ?? NULL);
            $municipio = trim($_GET["municipioHospital"] ?? '');
            $distrito = trim($_GET["distritoHospital"] ?? '');
            //Si alguno de los campos ingresados esta vacio
            if ( $nombre === '' && $departamento === '' && $municipio === '' && $distrito === '') {
                respuesta(400, ['success' => false, 'error' => 'Campo sin llenar']);
                exit;
            }
            //Si es correcto
            $resultado = $modelo->buscarPorFiltros(
                trim($_GET["nombreHospital"] ?? ''),
                trim($_GET["departamentoHospital"] ?? ''),
                trim($_GET["municipioHospital"] ?? ''),
                trim($_GET["distritoHospital"] ?? '')
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
            respuesta(200, ['success' => true, 'error' => 'No se encontro ningun hopital']);
        }
        
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== POST ==========
    function ingresarNuevoHospital(Hospital $modelo): void {
        $nombre = trim($_GET["nombreHospital"] ?? '');
        $direccion = trim($_GET["direccionHospital"] ?? '');
        $departamento = trim($_GET["departamentoHospital"] ?? '');
        $municipio = trim($_GET["municipioHospital"] ?? '');
        $distrito = trim($_GET["distritoHospital"] ?? '');

        //Si alguno de los campos ingresados esta vacio
        if ( $nombre === '' || $direccion === '' || $departamento === '' || $municipio === ''|| $distrito === '') {
            respuesta(400, ['success' => false, 'Mensaje:' => 'Todos los campos son abligatorios']);
            exit;
        }

        //Si es correcto
        $resultado = $modelo->agregarNuevoHospital(
            $nombre,
            $direccion,
            $departamento,
            $municipio,
            $distrito
        );

        respuesta(200, ['success' => true, 'Mensaje:' => $resultado]);
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== PUT ==========
    function modificarHospital(Hospital $modelo): void {
        $datosHospital = json_decode(file_get_contents("php://input"), true);

        //Comprobacion de si hay datos en la lista de datos ingresados
        if(!is_array($datosHospital)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Datos inválidos']);
            exit;
        }

        //pasamos los datos a variables
        $id = $datosHospital['idHospital'] ?? null;
        $nombre = $datosHospital['nombreHospital'] ?? null;
        $direccion = $datosHospital['direccionHospital'] ?? null;
        $departamento = $datosHospital['departamentoHospital'] ?? null;
        $municipio = $datosHospital['municipioHospital'] ?? null;
        $distrito = $datosHospital['distritoHospital'] ?? null;

        //vemos si la id esta escrita
        if($id === null || !filter_var($id, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'ID del hospital requerido']);
            exit;
        }

        //el nombre no puede estar vacio
        if($nombre === ''){
            respuesta(400, ['success' => false, 'Mensaje:' => 'El nombre no puede estar vacio']);
            exit;
        }

        $resultado = $modelo->actualizarHospital(
            $id,
            $nombre,
            $direccion,
            $departamento,
            $municipio,
            $distrito
        );

        respuesta(200, ['success' => true, 'Mensaje:' => $resultado]);
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== RESPUESTA   ==========
    //Se muestra la respuesta obtenida en un JSON
    function respuesta(int $codigo, array $datos): void {
        http_response_code($codigo);
        echo json_encode($datos);
        exit;
    }
?>