<?php
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . "/../Models/Solicitud.php";

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== METODO INGRESADO ==========
    try {
        require_once __DIR__ . "/../../../app/config/database.php"; 
        $modelo = new Solicitud($conn);
        $metodo = $_SERVER['REQUEST_METHOD'];

        //Obtenemos el metodo por el cual fue llamado
        switch ($metodo) {
            case 'GET':
                buscarSolicitud($modelo);
                break;
            case 'POST':
                ingresarNuevaSolicitud($modelo);
                break;
            case 'PUT':
                modificarSolicitud($modelo);
                break;
            case 'DELETE':
                eliminarUnaSolicitud($modelo);
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
    function buscarSolicitud(Solicitud $modelo): void{
        //Busqueda por ID
        if(isset($_GET["idSolicitud"])){
            $id = trim($_GET["idSolicitud"]) ?? NULL;
            //Si la busqueda esta vacia o no es un numero
            if($id === '' || !ctype_digit($id)){
                respuesta(400, ['success' => false, 'Mensaje:' => 'Campo de ID invalido']);
            }
            //Si es correcto
            $resultado = $modelo->buscarPorID(
                $id
            );
        }

        //Busqueda por Nombre o apellido del paciente, estado o nombre del hospital
        else if(isset($_GET["busquedaPaciente"])||
        isset($_GET["busquedaHospital"])||
        isset($_GET["estadoSolicitud"])){
            $paciente = trim($_GET["busquedaPaciente"] ?? '');
            $hospital = trim($_GET["busquedaHospital"] ?? '');
            $estado = trim($_GET["estadoSolicitud"] ?? '');

            //Si alguno de los campos ingresados esta vacio
            if ( $paciente === '' && $hospital === '' && $estado === '') {
                respuesta(400, ['success' => false, 'error' => 'Campo sin llenar']);
                exit;
            }
            //Si es correcto
            $resultado = $modelo->buscarPorFiltros(
                $paciente,
                $hospital,
                $estado
            );
        }

        //busqueda por id del paciente o hospital
        else if(isset($_GET["idPaciente"])||
        isset($_GET["idHospital"])){
            $paciente = trim($_GET["idPaciente"] ?? '');
            $hospital = trim($_GET["idHospital"] ?? '');

            //Si alguno de los campos ingresados esta vacio
            if ( $paciente === '' && $hospital === '') {
                respuesta(400, ['success' => false, 'error' => 'Campo sin llenar']);
                exit;
            }

            //Si es correcto
            $resultado = $modelo->buscarPorIDPacienteHospital(
                $paciente,
                $hospital,
            );
        }

        //Busqueda de todos los hospitales
        else{
            $resultado = $modelo->listarSolicitudes();
        }
        
        if($resultado){
            respuesta(200, ['success' => true, 'Pacientes' => $resultado]);
        }
        else{
            respuesta(400, ['success' => false, 'Mensaje' => 'No se encontro ninguna solicitud']);
        }
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== POST ==========
    function ingresarNuevaSolicitud(Solicitud $modelo): void {
        $paciente = trim($_POST["idPaciente"] ?? NULL);
        $hospital = trim($_POST["idHospital"] ?? NULL);
        $unidades = trim($_POST["unidadesSolicitadas"] ?? NULL);
        $justificacion = trim($_POST["justificacion"] ?? '');

        //Si alguno de los campos ingresados esta vacio
        if ( $paciente === '' && $hospital === '' && $unidades === '' && $justificacion === '') {
            respuesta(400, ['success' => false, 'Mensaje:' => 'Todos los campos son abligatorios']);
            exit;
        }

        //paciente no puede ser texto
        if(!filter_var($paciente, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Paciente solo puede ser un valor numerico entero']);
            exit;
        }

        //hospital no puede ser texto
        if(!filter_var($hospital, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Hospital solo puede ser un valor numerico entero']);
            exit;
        }

        //unidades no puede ser texto
        if(!filter_var($unidades, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Unidades solo puede ser un valor numerico entero']);
            exit;
        }

        $fecha = new DateTime();

        //Si es correcto
        $resultado = $modelo->agregarNuevaSolicitud(
            $paciente,
            $hospital,
            $fecha->format('Y.m-d'),
            $unidades,
            $justificacion
        );

        if($resultado === 0){
            respuesta(201, ['success' => true, 'Mensaje:' => 'Nueva solicitud ingresada correctamente']);
        }
        else if($resultado === 1){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Paciente no registrado']);
        }
        else{
            respuesta(400, ['success' => false, 'Mensaje:' => 'Hospital no registrado']);
        }
        
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== PUT ==========
    function modificarSolicitud(Solicitud $modelo): void {
        $datosSolicitud = json_decode(file_get_contents("php://input"), true);

        //Comprobacion de si hay datos en la lista de datos ingresados
        if(!is_array($datosSolicitud)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Datos inválidos']);
            exit;
        }

        //pasamos los datos a variables
        $id = $datosSolicitud['idSolicitud'] ?? null;
        $paciente = $datosSolicitud['idPaciente'] ?? null;
        $hospital = $datosSolicitud['idHospital'] ?? null;
        $justificacion = $datosSolicitud['justificacion'] ?? null;
        $unidades = $datosSolicitud['unidadesSolicitadas'] ?? null;
        $estado = $datosSolicitud['estado'] ?? null;

        //vemos si la id esta escrita
        if($id === null || !filter_var($id, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'ID de la solicitud requerida']);
            exit;
        }

        //no puede estar un campo vacio
        if($paciente === '' && $hospital === '' && $unidades === '' && $justificacion === '' && $estado === ''){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Ningun campo puede estar vacio']);
            exit;
        }

        //paciente no puede ser texto
        if(!filter_var($paciente, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Paciente solo puede ser un valor numerico entero']);
            exit;
        }

        //hospital no puede ser texto
        if(!filter_var($hospital, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Hospital solo puede ser un valor numerico entero']);
            exit;
        }

        //unidades no puede ser texto
        if(!filter_var($unidades, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Unidades solo puede ser un valor numerico entero']);
            exit;
        }
        

        $resultado = $modelo->actualizarSolicitud(
            $id,
            $paciente,
            $hospital,
            $unidades,
            $justificacion,
            $estado
        );

        //Segun la respuesta mostramos un mensaje u otro
        switch($resultado){
            case 1:
                respuesta(404, ['success' => false, 'Mensaje:' => 'Hospital no registrado']);
                break;
            case 2:
                respuesta(404, ['success' => false, 'Mensaje:' => 'Paciente no registrado']);
                break;
            case 3:
                respuesta(404, ['success' => false, 'Mensaje:' => 'ID de solicitud no encontrada']);
                break;
            default:
                respuesta(200, ['success' => true, 'Mensaje:' => 'Paciente actualizado correctamente']);
        }
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== DELETE   ==========
    function eliminarUnaSolicitud(Solicitud $modelo): void {
        $id = $_GET["idSolicitud"] ?? null;

        if ($id === null || !ctype_digit($id)) {
            respuesta(400, ['success' => false, 'mensaje' => 'ID de solicitud inválido']);
            exit;
        }

        $resultado = $modelo->eliminarSolicitud($id);

        if($resultado){
            respuesta(200, ['success' => true, 'Mensaje:' => $resultado]);
        }
        else{
            respuesta(404, ['success' => false, 'Mensaje:' => 'Solicitud no encontrada o eliminada']);
        }
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