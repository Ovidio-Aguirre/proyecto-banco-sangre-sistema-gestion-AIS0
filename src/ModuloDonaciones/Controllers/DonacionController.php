<?php
class DonacionController implements Controlador
{   
    private const ESTADOS = ['EN_EVALUACION', 'DISPONIBLE', 'DESCARTADA', 'VENCIDA', 'RESERVADA', 'UTILIZADA'];

    public function __construct(
        private Donacion $modelo
    ){}

    public function control(string $metodo, array $partes): void
    {
        // Parametros de las rutas
        $id = $partes[1] ?? null;
        $sub = $partes[2] ?? null;

        // Ruta /donaciones
        if ($id === null){
            match ($metodo) {
                'GET'   => $this->listarTodas(),
                'POST'  => $this->crearDonacion(),
                default => noPermitido(),
            };
            return;
        }

        // Ruta /donaciones/elegibles
        if ($id === 'elegibles'){
            if (isset($partes[2])){ noEncontrado(); }
            $metodo === 'GET' ? $this->listarElegibles() : noPermitido();
            return;
        }

        $idDonacion = validarId($id, 'ID de donacion inválido');

        // Ruta /donaciones/{id}/solicitud
        if ($sub === 'solicitud'){
            if (isset($partes[3])){ noEncontrado(); }
            $metodo === 'PATCH' ? $this->asignarSolicitud($idDonacion) : noPermitido();
            return;
        }

        // Ruta /donaciones/{id}/reasignar
        if ($sub === 'reasignar'){
            if (isset($partes[3])){ noEncontrado(); }
            $metodo === 'PATCH' ? $this->reasignarDonacion($idDonacion) : noPermitido();
            return;
        }

        if ($sub !== null){ noEncontrado(); }
        
        // Ruta /donaciones/{id}
        match ($metodo) {
            'GET'   => $this->obtenerDonacion($idDonacion),
            'PUT'   => $this->actualizarDonacion($idDonacion),
            default => noPermitido(),
        };
    }
        
    /* --- OTROS --- */
    // Referencia en DonanteController: GET/donantes/{id}/donaciones
    public function historial(string $metodo, int $idDonante): void
    {
        if ($metodo !== 'GET'){ noPermitido(); }
        respuesta(200, ['success' => true, 'donaciones' => $this->modelo->obtenerDonacionesPorDonante($idDonante)]);
    }

    /* --- PETICIONES --- */
    // GET /donaciones?fecha_ini=&fecha_fin=&id_tipo_sangre=&estado=
    private function listarTodas() : void
    {
        /* -- Validacion de filtros -- */
        // Rango de fechas
        $fechaIni = isset($_GET['fecha_ini']) ? validarFecha($_GET['fecha_ini'], 'fecha_ini', true) : null;
        $fechaFin = isset($_GET['fecha_fin']) ? validarFecha($_GET['fecha_fin'], 'fecha_fin', true) : null;

        if ($fechaIni !== null && $fechaFin !== null && $fechaIni > $fechaFin) {
            respuesta(400, ['success' => false, 'mensaje' => 'fecha_inicial no puede ser mayor a fecha_fin']);
        }

        // Filtro por tipo sanguineo
        $idTipoSangre = isset($_GET['id_tipo_sangre'])
        ? validarId($_GET['id_tipo_sangre'], 'Tipo de sangre inválido')
        : null;

        // Filtro por estado
        $estado = isset($_GET['estado']) ? $this->validarEstado($_GET['estado']) : null;

        $donaciones = $this->modelo->buscarDonaciones($fechaIni, $fechaFin, $idTipoSangre, $estado);
        respuesta(200, ['success' => true, 'donaciones' => $donaciones]);
    }

    // GET /donaciones/elegibles
    private function listarElegibles(): void
    {
        respuesta(200, ['success' => true, 'evaluaciones' => $this->modelo->obtenerEvaluacionesElegibles()]);
    }
    
    // GET /donaciones/{id}
    private function obtenerDonacion(int $idDonacion): void
    {
        $donacion = $this->modelo->obtenerDonacionPorId($idDonacion);
        if ($donacion === null) {
            respuesta(404, ['success' => false, 'mensaje' => 'Donación no encontrada']);
        }
        respuesta(200, ['success' => true, 'donacion' => $donacion]);
    }

    // POST /donaciones 
    private function crearDonacion(): void
    {
        $data = leerJson();

        // Validar campos
        if (!isset($data['id_donante'])) {
            respuesta(400, ['success' => false, 'mensaje' => 'Falta el campo id_donante']);
        }
        $idDonante   = validarId($data['id_donante'], 'ID de donante inválido');
        $idUsuario = usuarioActual(); // LEER SESION

        $res = $this->modelo->registrarDonacion($idDonante, $idUsuario);

        switch ($res['estado']) {
            case 'donante_no_disponible':
                respuesta(404, ['success' => false, 'mensaje' => 'Donante no encontrado o inactivo']);
            case 'sin_evaluacion':
                respuesta(409, ['success' => false, 'mensaje' => 'El donante no tiene evaluación del día']);
            case 'no_apto':
                respuesta(409, ['success' => false, 'mensaje' => 'El donante no es apto']);
            case 'evaluacion_usada':
                respuesta(409, ['success' => false, 'mensaje' => 'La evaluación ya fue utilizada']);
            default:
                registrarActividad(
                    'Registrar donación',
                    'DON-' . str_pad((string)$res['id_donacion'], 3, '0', STR_PAD_LEFT) . ' registrada (donante #' . $idDonante . ')'
                );
                respuesta(201, ['success' => true, 'id_donacion' => $res['id_donacion']]);
        }
    }

    // PUT /donaciones/{id}
    private function actualizarDonacion(int $idDonacion): void
    {
        $data = leerJson();

        // Validar campos
        if (!isset($data['estado'])) {
            respuesta(400, ['success' => false, 'mensaje' => 'Falta el campo estado']);
        }
        $estado = $this->validarEstado($data['estado']);

        $res = $this->modelo->actualizarDonacion($idDonacion, $estado);

        switch ($res) {
            case 'donacion_no_existe':
                respuesta(404, ['success' => false, 'mensaje' => 'Donación no encontrada']);
            case 'transicion_invalida':
                respuesta(409, ['success' => false, 'mensaje' => 'Transición de estado no permitida']);
            case 'sin_cambios':
                respuesta(200, [
                    'success'  => true,
                    'donacion' => $this->modelo->obtenerDonacionPorId($idDonacion),
                    'mensaje'  => 'La donación ya tiene ese estado, sin cambios'
                ]);
            default:
                registrarActividad(
                    'Cambiar estado de donación',
                    'DON-' . str_pad((string)$idDonacion, 3, '0', STR_PAD_LEFT) . ' pasó a ' . $estado
                );
                respuesta(200, [
                    'success'  => true,
                    'donacion' => $this->modelo->obtenerDonacionPorId($idDonacion),
                    'mensaje'  => 'Donación actualizada'
                ]);
        }
    }
    
    // PATCH /donaciones/{id}/solicitud
    private function asignarSolicitud(int $idDonacion): void
    {
        $data = leerJson();
        
        // Validar si existe la solicitud
        if (!array_key_exists('id_solicitud', $data)) {
            respuesta(400, ['success' => false, 'mensaje' => 'Falta el campo id_solicitud']);
        }

        // Vincular o desvincular la solicitud
        $idSolicitud = $data['id_solicitud'] === null
            ? null
            : validarId($data['id_solicitud'], 'ID de solicitud inválido');

        $res = $this->modelo->asignarSolicitud($idDonacion, $idSolicitud);

        switch ($res) {
            case 'donacion_no_existe':
                respuesta(404, ['success' => false, 'mensaje' => 'Donación no encontrada']);
            case 'solicitud_no_existe':
                respuesta(404, ['success' => false, 'mensaje' => 'Solicitud no encontrada']);
            case 'estado_invalido':
                respuesta(409, ['success' => false, 'mensaje' => 'La donación no se puede vincular/desvincular en su estado actual']);
            case 'solicitud_no_aprobada':
                respuesta(409, ['success' => false, 'mensaje' => 'La solicitud no está aprobada']);
            case 'solicitud_completa':
                respuesta(409, ['success' => false, 'mensaje' => 'La solicitud ya tiene todas las unidades solicitadas']);
            case 'tipo_incompatible':
                respuesta(409, ['success' => false, 'mensaje' => 'El tipo de sangre no es compatible con el paciente']);
            default:
                registrarActividad(
                    $idSolicitud === null ? 'Desvincular unidad' : 'Vincular unidad',
                    'DON-' . str_pad((string)$idDonacion, 3, '0', STR_PAD_LEFT)
                        . ($idSolicitud === null
                            ? ' desvinculada de su solicitud'
                            : ' vinculada a SOL-' . str_pad((string)$idSolicitud, 3, '0', STR_PAD_LEFT))
                );
                respuesta(200, [
                    'success'  => true,
                    'donacion' => $this->modelo->obtenerDonacionPorId($idDonacion),
                    'mensaje'  => $idSolicitud === null ? 'Solicitud desvinculada' : 'Solicitud vinculada'
                ]);
        }
    }

    // PATCH /donaciones/{id}/reasignar
    private function reasignarDonacion(int $idDonacion): void
    {
        $data = leerJson();

        if (!isset($data['id_evaluacion'])) {
            respuesta(400, ['success' => false, 'mensaje' => 'Falta el campo id_evaluacion']);
        }
        $idEvaluacion = validarId($data['id_evaluacion'], 'ID de evaluación inválido');
        
        $res = $this->modelo->reasignarDonacion($idDonacion, $idEvaluacion);

        switch ($res) {
            case 'donacion_no_existe':
                respuesta(404, ['success' => false, 'mensaje' => 'Donación no encontrada']);
            case 'donacion_no_reasignable':
                respuesta(409, ['success' => false, 'mensaje' => 'La donación no se puede reasignar en su estado actual']);
            case 'evaluacion_no_apta':
                respuesta(409, ['success' => false, 'mensaje' => 'La evaluación no es apta o no es actual']);
            case 'donante_no_disponible':
                respuesta(409, ['success' => false, 'mensaje' => 'El donante está inactivo']);
            case 'evaluacion_usada':
                respuesta(409, ['success' => false, 'mensaje' => 'La evaluación ya fue utilizada']);
            default:
                registrarActividad(
                    'Reasignar donación',
                    'DON-' . str_pad((string)$idDonacion, 3, '0', STR_PAD_LEFT) . ' reasignada a la evaluación #' . $idEvaluacion
                );
                respuesta(200, [
                    'success'  => true,
                    'donacion' => $this->modelo->obtenerDonacionPorId($idDonacion),
                    'mensaje'  => 'Donación reasignada'
                ]);
        }
    }

    /* --- UTILIDADES --- */
    // Validaciones
    private function validarEstado(mixed $estado): string
    {
        $estado = is_string($estado) ? strtoupper(trim($estado)) : '';
        if (!in_array($estado, self::ESTADOS, true)) {
            respuesta(400, ['success' => false, 'mensaje' => 'Estado inválido', 'permitidos' => self::ESTADOS]);
        }
        return $estado;
    }
}