<?php

//ACLARACIONES
/*
-Una solicitud solo se le cambiar el estado de PENDIENTE a RECHAZADA o APROBADA
-No se puede cambiar una solicitud ya APROBADA o RECHAZADA a PENDIENTE
-Solo se puede poner como ENTREGADA una solicitud APROBADA (PATCH)
-Solo se puede eliminar o INHABILITAR una solicitud con estado de RECHAZADA
-Las solicitudes son externas: no se editan, solo se aprueban, rechazan y entregan
*/
class SolicitudController implements Controlador
        {
            public function __construct(
                private Solicitud $modelo,
                private Donacion $donaciones
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

                // /solicitudes/{id}/unidades-compatibles | /solicitudes/{id}/unidades
                if (isset($partes[2])) {
                    if (isset($partes[3])) {
                        noEncontrado();
                    }
                    if ($partes[2] === 'unidades-compatibles') {
                        if ($metodo !== 'GET') {
                            noPermitido();
                        }
                        $this->unidadesCompatibles($idSolicitud);
                        return;
                    }
                    if ($partes[2] === 'unidades') {
                        if ($metodo !== 'POST') {
                            noPermitido();
                        }
                        $this->asignarUnidades($idSolicitud);
                        return;
                    }
                    noEncontrado();
                }

                match ($metodo) {
                    'GET'    => $this->obtenerSolicitud($idSolicitud),
                    'PUT'    => $this->resolverUnaSolicitud($idSolicitud),
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
                    'solicitud' => $resultado
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
                            respuesta(400, ['success' => false, 'mensaje' => 'Tipo de orden inválido']);
                            exit;
                        }
                    } 
                    //Estados validos: PENDIENTE, APROBADA, RECHAZADA, ENTREGADA
                    if($estado){
                        if($estado !== 'PENDIENTE' && $estado !== 'APROBADA' && $estado !== 'RECHAZADA' && $estado !== 'ENTREGADA' ){
                            respuesta(400, ['success' => false, 'mensaje' => 'Estado inválido']);
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

                //Busqueda de todas las solicitudes
                else{
                    $resultado = $this->modelo->listarSolicitudes();
                }
                
                // Sin resultados, lista vacia con 200
                respuesta(200, ['success' => true, 'solicitudes' => $resultado ?: []]);
            }

            // Unidades compatibles y cupo de solicitud
            function unidadesCompatibles(int $id): void {
                $resultado = $this->donaciones->obtenerUnidadesCompatibles($id);

                if ($resultado === null) {
                    respuesta(404, ['success' => false, 'mensaje' => 'Solicitud no encontrada']);
                }

                respuesta(200, array_merge(['success' => true], $resultado));
            }

            //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
            //========== POST ==========
            // Punto de entrada de solicitudes externas
            function ingresarNuevaSolicitud(): void {
                $datosSolicitud = leerJson();

                $paciente = $datosSolicitud["idPaciente"] ?? '';
                $hospital = $datosSolicitud["idHospital"] ?? '';
                $unidades = $datosSolicitud["unidadesSolicitadas"] ?? '';
                $justificacion = $datosSolicitud["justificacion"] ?? '';

                //Si alguno de los campos ingresados esta vacio
                if ( $paciente === '' || $hospital === '' || $unidades === '' || $justificacion === '') {
                    respuesta(400, ['success' => false, 'mensaje' => 'Todos los campos son obligatorios']);
                    exit;
                }

                validarCampoEntero($paciente);
                validarCampoEntero($hospital);
                validarCampoEntero($unidades);

                if($unidades > 10){
                    respuesta(400, ['success' => false, 'mensaje' => 'Demasiadas unidades solicitadas']);
                    exit;
                }

                $fecha = new DateTime();

                $resultado = $this->modelo->agregarNuevaSolicitud(
                    $paciente,
                    $hospital,
                    $fecha->format('Y-m-d'),
                    $unidades,
                    $justificacion
                );

                if($resultado === 0){
                    respuesta(201, ['success' => true, 'mensaje' => 'Nueva solicitud ingresada correctamente']);
                }
                else if($resultado === 1){
                    respuesta(400, ['success' => false, 'mensaje' => 'Paciente no registrado']);
                }
                else{
                    respuesta(400, ['success' => false, 'mensaje' => 'Hospital no registrado']);
                }
                
            }

            // Asignar unidades a una solicitud APROBADA. {"ids": [1, 2]}
            function asignarUnidades(int $id): void {
                $datos = leerJson();
                $ids = $datos['ids'] ?? null;

                if (!is_array($ids) || count($ids) === 0) {
                    respuesta(400, ['success' => false, 'mensaje' => 'Debe indicar al menos una unidad']);
                }
                foreach ($ids as $idDonacion) {
                    if (!is_int($idDonacion) && !(is_string($idDonacion) && ctype_digit($idDonacion))) {
                        respuesta(400, ['success' => false, 'mensaje' => 'Los IDs de unidad deben ser enteros']);
                    }
                }

                $resultado = $this->donaciones->asignarUnidades($id, $ids);

                switch ($resultado) {
                    case 'actualizada':
                        registrarActividad(
                            'Asignar unidades',
                            count($ids) . ' unidad(es) asignadas a SOL-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT)
                        );
                        respuesta(200, ['success' => true, 'mensaje' => 'Unidades asignadas correctamente']);
                        break;
                    case 'sin_unidades':
                        respuesta(400, ['success' => false, 'mensaje' => 'Debe indicar al menos una unidad']);
                        break;
                    case 'solicitud_no_existe':
                        respuesta(404, ['success' => false, 'mensaje' => 'Solicitud no encontrada']);
                        break;
                    case 'donacion_no_existe':
                        respuesta(404, ['success' => false, 'mensaje' => 'Una de las unidades no existe']);
                        break;
                    case 'solicitud_no_aprobada':
                        respuesta(409, ['success' => false, 'mensaje' => 'La solicitud no está aprobada']);
                        break;
                    case 'solicitud_completa':
                        respuesta(409, ['success' => false, 'mensaje' => 'Las unidades superan las solicitadas']);
                        break;
                    case 'estado_invalido':
                        respuesta(409, ['success' => false, 'mensaje' => 'Una de las unidades no está disponible']);
                        break;
                    case 'tipo_incompatible':
                        respuesta(409, ['success' => false, 'mensaje' => 'Una de las unidades no es compatible con el paciente']);
                        break;
                    default:
                        respuesta(500, ['success' => false, 'mensaje' => 'No se pudieron asignar las unidades']);
                }
            }

            //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
            //========== PUT ==========
            // Aprobar o rechazar una solicitud PENDIENTE. {estado, motivoRechazo?}
            function resolverUnaSolicitud(int $id): void {
                $datosSolicitud = leerJson();

                $estado = strtoupper(trim($datosSolicitud['estado'] ?? ''));
                $motivo = $datosSolicitud['motivoRechazo'] ?? null;

                //Estados validos: APROBADA, RECHAZADA
                if($estado !== 'APROBADA' && $estado !== 'RECHAZADA'){
                    respuesta(400, ['success' => false, 'mensaje' => 'Estado inválido: solo se puede aprobar o rechazar']);
                }

                if($estado === 'RECHAZADA'){
                    if($motivo === '' || $motivo === NULL){
                        respuesta(400, ['success' => false, 'mensaje' => 'Si va a rechazar una solicitud se debe colocar el motivo de la misma']);
                    }
                }
                
                $resultado = $this->modelo->resolverSolicitud(
                    $id,
                    $estado,
                    $motivo
                );

                switch($resultado){
                    case 1:
                        respuesta(404, ['success' => false, 'mensaje' => 'ID de solicitud no encontrada']);
                        break;
                    case 2:
                        respuesta(409, ['success' => false, 'mensaje' => 'La solicitud ya fue procesada (aprobada, rechazada o entregada)']);
                        break;
                    case 3:
                        respuesta(400, ['success' => false, 'mensaje' => 'Estado inválido']);
                        break;
                    default:
                        registrarActividad(
                            $estado === 'APROBADA' ? 'Aprobar solicitud' : 'Rechazar solicitud',
                            'SOL-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT)
                                . ($estado === 'APROBADA' ? ' aprobada' : ' rechazada')
                        );
                        respuesta(200, ['success' => true, 'mensaje' => 'Solicitud actualizada correctamente']);
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
                    respuesta(409, ['success' => false, 'mensaje' => 'La solicitud no se encuentra aprobada']);
                }
                if($resultado === 2){
                    respuesta(409, ['success' => false, 'mensaje' => 'La solicitud ya esta entregada']);
                }
                if($resultado === 3){
                    respuesta(409, ['success' => false, 'mensaje' => 'La solicitud no tiene todas las unidades asignadas']);
                }
                if($resultado === false){
                    respuesta(500, [
                        'success' => false,
                        'mensaje' => 'No se pudo entregar la solicitud'
                    ]);
                }

                registrarActividad(
                    'Entregar solicitud',
                    'SOL-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . ' entregada'
                );

                respuesta(200, [
                    'success' => true,
                    'mensaje' => 'Solicitud entregada al paciente'
                ]);
            }
        }
?>