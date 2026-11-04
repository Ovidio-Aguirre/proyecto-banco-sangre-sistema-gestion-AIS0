<?php

    class PacienteController implements Controlador
        {
            public function __construct(
                private Paciente $modelo
            ) {}

            public function control(string $metodo, array $partes): void
            {
                $id = $partes[1] ?? null;

                // /pacientes
                if ($id === null) {
                    match ($metodo) {
                        'GET'  => $this->buscarPaciente(),
                        'POST' => $this->ingresarNuevoPaciente(),
                        default => noPermitido(),
                    };
                    return;
                }

                // /pacientes/{id}
                $idPaciente = validarId($id, 'ID de paciente inválido');

                if (isset($partes[2])) {
                    noEncontrado();
                }

                match ($metodo) {
                    'GET'    => $this->obtenerPaciente($idPaciente),
                    'PUT'    => $this->modificarPaciente($idPaciente),
                    default  => noPermitido(),
                };
            }
            //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
            //========== GET ==========

            function obtenerPaciente(int $id): void{
                $resultado = $this->modelo->buscarPorID($id);

                if (!$resultado) {
                    respuesta(404, ['success' => false,'mensaje' => 'Paciente no encontrado']);
                }

                respuesta(200, [
                    'success' => true,
                    'Hospital' => $resultado
                ]);
            }
            function buscarPaciente(): void{
                //Busqueda por Nombre o apellido, tipo de sangre o sexo
                if(isset($_GET["busquedaPaciente"])||
                isset($_GET["tipoSangrePaciente"])||
                isset($_GET["sexoPaciente"])||
                isset($_GET["orden"])){
                    $busqueda = trim($_GET["busquedaPaciente"] ?? '');
                    $tipo = trim($_GET["tipoSangrePaciente"] ?? '');
                    $sexo = trim($_GET["sexoPaciente"] ?? '');
                    $sexo = strtoupper($sexo);
                    $orden = trim($_GET["orden"] ?? '');
                    $orden = strtoupper($orden);

                    validarCamposVacios([$busqueda, $tipo, $sexo, $orden]);
                    if($tipo){
                        validarCampoEntero($tipo);
                    }
                    //Si existe orden
                    if($orden){
                        if($orden !== 'ASC' && $orden !== 'DESC'){
                            respuesta(400, ['success' => false, 'error' => 'Tipo de orden inválido']);
                            exit;
                        }
                    } 

                    //Si existe sexo
                    if($sexo){
                        if($sexo !== 'M' && $sexo !== 'F'){
                            respuesta(400, ['success' => false, 'error' => 'Tipo de sexo inválido']);
                            exit;
                        }
                    }

                    $resultado = $this->modelo->buscarPorFiltros(
                        $busqueda,
                        $tipo,
                        $sexo,
                        $orden
                    );
                }

                //Busqueda de todos los pacientes
                else{
                    $resultado = $this->modelo->listarPacientes();
                }
                
                if($resultado){
                    if($resultado === 1){
                        respuesta(400, ['success' => false, 'Mensaje' => 'Tipo de sangre inválido']);
                    }
                    respuesta(200, ['success' => true, 'Pacientes' => $resultado]);
                }
                else{
                    respuesta(400, ['success' => false, 'Mensaje' => 'No se encontro ningun paciente']);
                }
            }

            //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
            //========== POST ==========
            function ingresarNuevoPaciente(): void {
                $datosPaciente = leerJson();

                $nombre = $datosPaciente["nombrePaciente"] ?? '';
                $apellido = $datosPaciente["apellidoPaciente"] ?? '';
                $tipo = $datosPaciente["tipoSangre"] ?? '';
                $fecha = $datosPaciente["fechaPaciente"] ?? '';
                $sexo = $datosPaciente["sexoPaciente"] ?? '';
                $sexo = strtoupper($sexo);

                //Si alguno de los campos ingresados esta vacio
                if ( $nombre === '' || $apellido === '' || $tipo === '' || $fecha === ''|| $sexo === '') {
                    respuesta(400, ['success' => false, 'Mensaje:' => 'Todos los campos son abligatorios']);
                    exit;
                }

                //el ingreso de fecha debe ser año, mes, dia y debe ser anterior al dia de hoy
                validarFecha($fecha);

                //sexo no puede ser distinto a M o F
                if($sexo !== 'M' && $sexo !== 'F'){
                    respuesta(400, ['success' => false, 'Mensaje:' => 'Tipo de sexo inválido']);
                    exit;
                }

                $resultado = $this->modelo->agregarNuevoPaciente(
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
            function modificarPaciente(int $id): void {
                $datosPaciente = leerJson();

                //pasamos los datos a variables
                $nombre = $datosPaciente['nombrePaciente'] ?? null;
                $apellido = $datosPaciente['apellidoPaciente'] ?? null;
                $tipo = $datosPaciente['tipoSangre'] ?? null;
                $fecha = $datosPaciente['fechaPaciente'] ?? null;
                $sexo = $datosPaciente['sexoPaciente'] ?? null;
                $sexo = strtoupper($sexo);

                validarCamposVacios([$nombre]);
                validarCamposVacios([$apellido]);
                validarCampoEntero($tipo);

                //el ingreso de fecha debe ser año, mes, dia y debe ser anterior al dia de hoy
                validarFecha($fecha);

                //sexo no puede ser distinto a M o F
                if($sexo !== 'M' && $sexo !== 'F'){
                    respuesta(400, ['success' => false, 'Mensaje:' => 'Caracter de sexo invaido']);
                    exit;
                }
                

                $resultado = $this->modelo->actualizarPaciente(
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
        }
?>