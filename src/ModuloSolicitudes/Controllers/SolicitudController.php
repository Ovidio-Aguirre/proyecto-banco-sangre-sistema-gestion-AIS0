<?php

//ACLARACIONES
/*
-Una solicitud solo se le cambiar el estado de PENDIENTE a RECHAZADA o APROBADA
-No se puede cambiar una solicitud ya APROBADA o RECHAZADA a PENDIENTE
-Solo se puede poner como ENTREGADA una solicitud APROBADA (PATCH)
-Solo se puede eliminar o INAHABILITAR una solicitud con estado de RECHAZADA

-En la Base de datos se debe de colocar un campo llamado fecha_entrega este debe permitir NULO
*/
class SolicitudController implements Controlador
        {
            public function __construct(
                private Solicitud $modelo
            ) {}

            public function control(string $metodo, array $partes): void
            {
                $id = $partes[1] ?? null;

                // /solicitudes
                if ($id === null) {
                    match ($metodo) {
                        'GET'  => $this->buscarSolicitud(),
                        'POST' => $this->ingresarNuevaSolicitud(),
                        default => noPermitido(),
                    };
                    return;
                }

                // /solicitudes/{id}
                $idSolicitud = validarId($id, 'ID de solicitud inválido');

                if (isset($partes[2])) {
                    noEncontrado();
                }

                match ($metodo) {
                    'GET'    => $this->obtenerSolicitud($idSolicitud),
                    'PUT'    => $this->modificarSolicitud($idSolicitud),
                    'PATCH' => $this->entregarUnaSolicitud($idSolicitud),
                    default  => noPermitido(),
                };
            }

            //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
            //========== GET ==========

            function obtenerSolicitud(int $id): void{
                $resultado = $this->modelo->buscarPorID($id);

                if (!$resultado) {
                    respuesta(404, ['success' => false,'mensaje' => 'Solicitud no encontrada']);
                }

                respuesta(200, [
                    'success' => true,
                    'Hospital' => $resultado
                ]);
            }

            function buscarSolicitud(): void{
                //Busqueda por Nombre o apellido del paciente, estado o nombre del hospital
                if(isset($_GET["busquedaPaciente"])||
                isset($_GET["busquedaHospital"])||
                isset($_GET["estadoSolicitud"])||
                isset($_GET["orden"])){
                    $paciente = trim($_GET["busquedaPaciente"] ?? '');
                    $hospital = trim($_GET["busquedaHospital"] ?? '');
                    $estado = trim($_GET["estadoSolicitud"] ?? '');
                    $estado = strtoupper($estado);
                    $orden = trim($_GET["orden"] ?? '');
                    $orden = strtoupper($orden);

                    validarCamposVacios([$paciente, $hospital, $estado, $orden]);

                    //Si existe orden
                    if($orden){
                        if($orden !== 'ASC' && $orden !== 'DESC'){
                            respuesta(400, ['success' => false, 'error' => 'Tipo de orden inválido']);
                            exit;
                        }
                    } 
                    //Estados validos: PENDIENTE, ACEPTADA, RECHAZADA, ENTREGADA
                    if($estado){
                        if($estado !== 'PENDIENTE' && $estado !== 'APROBADA' && $estado !== 'RECHAZADA' && $estado !== 'ENTREGADA' ){
                            respuesta(400, ['success' => false, 'Mensaje:' => 'Estado invalido']);
                        }
                    }

                    $resultado = $this->modelo->buscarPorFiltros(
                        $paciente,
                        $hospital,
                        $estado,
                        $orden
                    );
                }

                //busqueda por id del paciente o hospital
                else if(isset($_GET["idPaciente"])||
                isset($_GET["idHospital"])){
                    $paciente = trim($_GET["idPaciente"] ?? '');
                    $hospital = trim($_GET["idHospital"] ?? '');

                    validarCamposVacios([$paciente, $hospital]);

                    //Si es correcto
                    $resultado = $this->modelo->buscarPorIDPacienteHospital(
                        $paciente,
                        $hospital,
                    );
                }

                //Busqueda de todos los hospitales
                else{
                    $resultado = $this->modelo->listarSolicitudes();
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
            function ingresarNuevaSolicitud(): void {
                $datosSolicitud = leerJson();

                $paciente = $datosSolicitud["idPaciente"] ?? '';
                $hospital = $datosSolicitud["idHospital"] ?? '';
                $unidades = $datosSolicitud["unidadesSolicitadas"] ?? '';
                $justificacion = $datosSolicitud["justificacion"] ?? '';

                //Si alguno de los campos ingresados esta vacio
                if ( $paciente === '' || $hospital === '' || $unidades === '' || $justificacion === '') {
                    respuesta(400, ['success' => false, 'Mensaje:' => 'Todos los campos son abligatorios']);
                    exit;
                }

                validarCampoEntero($paciente);
                validarCampoEntero($hospital);
                validarCampoEntero($unidades);

                if($unidades > 10){
                    respuesta(400, ['success' => false, 'Mensaje:' => 'Demaciadas unidades solicitadas']);
                    exit;
                }

                $fecha = new DateTime();

                $resultado = $this->modelo->agregarNuevaSolicitud(
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
            function modificarSolicitud(int $id): void {
                $datosSolicitud = leerJson();

                //pasamos los datos a variables
                $paciente = $datosSolicitud['idPaciente'] ?? null;
                $hospital = $datosSolicitud['idHospital'] ?? null;
                $justificacion = $datosSolicitud['justificacion'] ?? null;
                $unidades = $datosSolicitud['unidadesSolicitadas'] ?? null;
                $estado = $datosSolicitud['estado'] ?? null;
                $estado = strtoupper($estado);
                $motivo = $datosSolicitud['motivoRechazo'] ?? null;

                validarCamposVacios([$paciente, $hospital, $justificacion, $unidades, $estado]);

                validarCampoEntero($paciente);
                validarCampoEntero($hospital);
                validarCampoEntero($unidades);

                if($unidades > 10){
                    respuesta(400, ['success' => false, 'Mensaje:' => 'Demaciadas unidades solicitadas']);
                    exit;
                }

                //Estados validos: PENDIENTE, ACEPTADA, RECHAZADA
                if($estado){
                    if($estado !== 'PENDIENTE' && $estado !== 'APROBADA' && $estado !== 'RECHAZADA' ){
                        respuesta(400, ['success' => false, 'Mensaje:' => 'Estado invalido']);
                    }
                }

                if($estado === 'RECHAZADA'){
                    if($motivo === ''){
                        respuesta(400, ['success' => false, 'Mensaje:' => 'Si va a rechazar un solicitud se debe colocar el motivo de la misma']);
                    }
                }
                
                $resultado = $this->modelo->actualizarSolicitud(
                    $id,
                    $paciente,
                    $hospital,
                    $unidades,
                    $justificacion,
                    $estado,
                    $motivo
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
                    case 4:
                        respuesta(404, ['success' => false, 'Mensaje:' => 'Solicitud ya entregada, aprobada o rechazada']);
                        break;
                    default:
                        respuesta(200, ['success' => true, 'Mensaje:' => 'Paciente actualizado correctamente']);
                }
            }

            //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
            //========== PATCH ==========
            function entregarUnaSolicitud(int $id){
                $estado = 'ENTREGADA';
                $resultado = $this->modelo->entregarSolicitud(
                    $estado,
                    $id
                );
                if($resultado === 1){
                    respuesta(409, ['success' => false, 'Mensaje:' => 'La solicitud no se encuentra aprobada']);
                }
                if($resultado === 2){
                    respuesta(409, ['success' => false, 'Mensaje:' => 'La solicitud ya esta entregada']);
                }
                if($resultado === false){
                    respuesta(500, [
                        'success' => false,
                        'mensaje' => 'No se pudo entregar la solicitud'
                    ]);
                }

                respuesta(200, [
                    'success' => true,
                    'mensaje' => 'Solicitud entregada al paciente'
                ]);
            }
        }
?>