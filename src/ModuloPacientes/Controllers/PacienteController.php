<?php
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . "/../Models/Paciente.php";

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== METODO INGRESADO ==========
    try {
        require_once __DIR__ . "/../../../app/config/database.php"; 
        $modelo = new Paciente($conn);
        $metodo = $_SERVER['REQUEST_METHOD'];

        //Obtenemos el metodo por el cual fue llamado
        switch ($metodo) {
            case 'GET':
                buscarPaciente($modelo);
                break;
            case 'POST':
                ingresarNuevoPaciente($modelo);
                break;
            case 'PUT':
                modificarPaciente($modelo);
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
    function buscarPaciente(Paciente $modelo): void{
        //Busqueda por ID
        if(isset($_GET["idPaciente"])){
            $id = trim($_GET["idPaciente"]) ?? NULL;
            //Si la busqueda esta vacia o no es un numero
            if($id === '' || !ctype_digit($id)){
                respuesta(400, ['success' => false, 'Mensaje:' => 'Campo de ID invalido']);
            }
            //Si es correcto
            $resultado = $modelo->buscarPorID(
                $id
            );
        }

        //Busqueda por Nombre o apellido, tipo de sangre o sexo
        else if(isset($_GET["busquedaPaciente"])||
        isset($_GET["tipoSangrePaciente"])||
        isset($_GET["sexoPaciente"])){
            $busqueda = trim($_GET["busquedaPaciente"] ?? '');
            $tipo = trim($_GET["tipoSangrePaciente"] ?? NULL);
            $sexo = trim($_GET["sexoPaciente"] ?? '');

            //Si alguno de los campos ingresados esta vacio
            if ( $busqueda === '' && $tipo === '' && $sexo === '') {
                respuesta(400, ['success' => false, 'error' => 'Campo sin llenar']);
                exit;
            }
            //Si es correcto
            $resultado = $modelo->buscarPorFiltros(
                $busqueda,
                $tipo,
                $sexo
            );
        }

        //Busqueda de todos los hospitales
        else{
            $resultado = $modelo->listarPacientes();
        }
        
        if($resultado){
            respuesta(200, ['success' => true, 'Pacientes' => $resultado]);
        }
        else{
            respuesta(400, ['success' => false, 'Mensaje' => 'No se encontro ningun paciente']);
        }
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== POST ==========
    function ingresarNuevoPaciente(Paciente $modelo): void {
        $nombre = trim($_GET["nombrePaciente"] ?? '');
        $apellido = trim($_GET["apellidoPaciente"] ?? '');
        $tipo = trim($_GET["tipoSangrePaciente"] ?? '');
        $fecha = trim($_GET["fechaNacimientoPaciente"] ?? '');
        $sexo = trim($_GET["sexoPaciente"] ?? '');
        $sexo = strtoupper($sexo);

        //Si alguno de los campos ingresados esta vacio
        if ( $nombre === '' || $apellido === '' || $tipo === '' || $fecha === ''|| $sexo === '') {
            respuesta(400, ['success' => false, 'Mensaje:' => 'Todos los campos son abligatorios']);
            exit;
        }

        //tipo no puede ser texto
        if(!filter_var($tipo, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Tipo de sangre solo puede ser un valor numerico entero']);
            exit;
        }

        //el ingreso de fecha debe ser año, mes, dia y debe ser anterior al dia de hoy
        $fechaValidada = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$fechaValidada || $fechaValidada->format('Y-m-d') !== $fecha || $fechaValidada > new DateTime('today')){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Tipo de fecha ingresada no es valida']);
            exit;
        }

        //sexo no puede ser distinto a M o F
        if($sexo !== 'M' && $sexo !== 'F'){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Caracter de sexo invaido']);
            exit;
        }

        //Si es correcto
        $resultado = $modelo->agregarNuevoPaciente(
            $nombre,
            $apellido,
            $tipo,
            $fecha,
            $sexo
        );

        if($resultado === 0){
            respuesta(201, ['success' => true, 'Mensaje:' => 'Nuevo registro de paciente ingresado correctamente']);
        }
        else if($resultado === 2){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Tipo de sangre invalido']);
        }
        else{
            respuesta(409, ['success' => false, 'Mensaje:' => 'Este paciente ya esta registrado']);
        }
        
    }

    //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //========== PUT ==========
    function modificarPaciente(Paciente $modelo): void {
        $datosPaciente = json_decode(file_get_contents("php://input"), true);

        //Comprobacion de si hay datos en la lista de datos ingresados
        if(!is_array($datosPaciente)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Datos inválidos']);
            exit;
        }

        //pasamos los datos a variables
        $id = $datosPaciente['idPaciente'] ?? null;
        $nombre = $datosPaciente['nombrePaciente'] ?? null;
        $apellido = $datosPaciente['apellidoPaciente'] ?? null;
        $tipo = $datosPaciente['tipoSangrePaciente'] ?? null;
        $fecha = $datosPaciente['fechaPaciente'] ?? null;
        $sexo = $datosPaciente['sexoPaciente'] ?? null;

        //vemos si la id esta escrita
        if($id === null || !filter_var($id, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'ID del paciente requerido']);
            exit;
        }

        //el nombre no puede estar vacio
        if($nombre === ''){
            respuesta(400, ['success' => false, 'Mensaje:' => 'El nombre no puede estar vacio']);
            exit;
        }

        //el apellido no puede estar vacio
        if($apellido === ''){
            respuesta(400, ['success' => false, 'Mensaje:' => 'El apellido no puede estar vacio']);
            exit;
        }

        //tipo no puede ser texto
        if(!filter_var($tipo, FILTER_VALIDATE_INT)){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Tipo de sangre solo puede ser un valor numerico entero']);
            exit;
        }

        //el ingreso de fecha debe ser año, mes, dia y debe ser anterior al dia de hoy
        $fechaValidada = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$fechaValidada || $fechaValidada->format('Y-m-d') !== $fecha || $fechaValidada > new DateTime('today')){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Tipo de fecha ingresada no es valida']);
            exit;
        }

        //sexo no puede ser distinto a M o F
        if($sexo !== 'M' && $sexo !== 'F'){
            respuesta(400, ['success' => false, 'Mensaje:' => 'Caracter de sexo invaido']);
            exit;
        }
        

        $resultado = $modelo->actualizarPaciente(
            $id,
            $nombre,
            $apellido,
            $tipo,
            $fecha,
            $sexo
        );

        //Segun la respuesta mostramos un mensaje u otro
        switch($resultado){
            case 1:
                respuesta(404, ['success' => false, 'Mensaje:' => 'ID ingresado es invalido']);
                break;
            case 2:
                respuesta(404, ['success' => false, 'Mensaje:' => 'El tipo de sangre ingresado es invalido']);
                break;
            default:
                respuesta(200, ['success' => true, 'Mensaje:' => 'Paciente actualizado correctamente']);
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