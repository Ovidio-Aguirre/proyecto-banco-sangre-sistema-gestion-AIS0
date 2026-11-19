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
                    'paciente' => $resultado
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
                            respuesta(400, ['success' => false, 'mensaje' => 'Tipo de orden inválido']);
                            exit;
                        }
                    } 

                    //Si existe sexo
                    if($sexo){
                        if($sexo !== 'M' && $sexo !== 'F'){
                            respuesta(400, ['success' => false, 'mensaje' => 'Tipo de sexo inválido']);
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
                
                //Valida si el tipo de sangre no existe
                if($resultado === 1){
                    respuesta(400, ['success' => false, 'mensaje' => 'Tipo de sangre inválido']);
                }
                // Sin resultados, lista vacia
                respuesta(200, ['success' => true, 'pacientes' => $resultado ?: []]);
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
                    respuesta(400, ['success' => false, 'mensaje' => 'Todos los campos son obligatorios']);
                    exit;
                }

                //el ingreso de fecha debe ser año, mes, dia y debe ser anterior al dia de hoy
                validarFecha($fecha);

                //sexo no puede ser distinto a M o F
                if($sexo !== 'M' && $sexo !== 'F'){
                    respuesta(400, ['success' => false, 'mensaje' => 'Tipo de sexo inválido']);
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
                    respuesta(201, ['success' => true, 'mensaje' => 'Nuevo registro de paciente ingresado correctamente']);
                }
                else if($resultado === 2){
                    respuesta(400, ['success' => false, 'mensaje' => 'Tipo de sangre inválido']);
                }
                else{
                    respuesta(409, ['success' => false, 'mensaje' => 'Este paciente ya está registrado']);
                }
                
            }

        }
?>