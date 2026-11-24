<?php

class Donacion{
    private mysqli $conn;

    // Transicion manual de estados
    private const TRANSICIONES = [
        'EN_EVALUACION' => ['DESCARTADA', 'DISPONIBLE'],
        'DISPONIBLE'    => [],
        'RESERVADA'     => [],
        'VENCIDA'       => ['DESCARTADA'],
        'DESCARTADA'    => [],
        'UTILIZADA'     => [],
    ];

    // Tabla de compatibilidad sanguinea
    private const COMPATIBILIDAD = [
        'O-'  => ['O-'],
        'O+'  => ['O-', 'O+'],
        'A-'  => ['O-', 'A-'],
        'A+'  => ['O-', 'O+', 'A-', 'A+'],
        'B-'  => ['O-', 'B-'],
        'B+'  => ['O-', 'O+', 'B-', 'B+'],
        'AB-' => ['O-', 'A-', 'B-', 'AB-'],
        'AB+' => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
    ];

    // Limite de donaciones
    private const INTERVALO_DIAS = 60;
    private const LIMITE_ANUAL = ['M' => 4, 'F' => 3];

    public function __construct(mysqli $dbConnection){
        $this-> conn = $dbConnection;
    }

    /**
     * Registrar una donacion para donantes aptos
     * @return array ['estado' => creado|donante_no_disponible sin_evaluacion
     *               |no_apto|evaluacion_usada|intervalo_minimo|limite_anual 'id_donacion' (creado)]
     */
    public function registrarDonacion(
        int $idDonante, 
        int $idUsuario 
    ) : array
    {
        // Validar donante activo
        $stmt = $this->conn->prepare(
                "SELECT sexo FROM donantes WHERE activo = 1 AND id_donante = ?");
        $stmt->bind_param("i",$idDonante);
        $stmt->execute();
        $donante = $stmt->get_result()->fetch_assoc();
        if ($donante === null){
            return ['estado' => 'donante_no_disponible'];
        }

        //Consultar ultima evaluacion
        $stmt = $this->conn->prepare(
                "SELECT id_evaluacion, resultado, (fecha_evaluacion = CURDATE()) AS fecha_hoy
                FROM evaluaciones_donante 
                WHERE id_donante = ? 
                ORDER BY fecha_evaluacion DESC, id_evaluacion DESC 
                LIMIT 1");
        $stmt->bind_param("i",$idDonante);
        $stmt->execute();
        $eval = $stmt->get_result()->fetch_assoc();

        // Validar evaluacion del dia y resultado APTO
        if ($eval === null || (int)$eval['fecha_hoy'] !== 1) {
            return ['estado' => 'sin_evaluacion'];
        }
        if ($eval['resultado'] !== 'APTO') {
            return ['estado' => 'no_apto'];
        }

        $idEvaluacion = (int)$eval['id_evaluacion'];

        // Validar si la evaluacion no se ha utilizado
        $stmt = $this->conn->prepare("SELECT 1 FROM donaciones WHERE id_evaluacion = ?");
        $stmt->bind_param("i", $idEvaluacion);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['estado' => 'evaluacion_usada'];
        }

         // Intervalo mínimo entre donaciones y límite anual
        $bloqueo = $this->validarFrecuencia($idDonante, $donante['sexo']);
        if ($bloqueo !== null) {
            return $bloqueo;
        }

        // fecha_caducidad = fecha_recoleccion + 42 dias
        $stmt = $this->conn->prepare(
            "INSERT INTO donaciones
            (id_evaluacion, id_usuario, id_solicitud, fecha_recoleccion, fecha_caducidad, estado)
            VALUES (?, ?, NULL, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 42 DAY), 'EN_EVALUACION')"
        );
        $stmt->bind_param("ii", $idEvaluacion, $idUsuario);
        $stmt->execute();

        return ['estado' => 'creado','id_donacion' => $this->conn->insert_id]; 
    }

    /**
     * Consultar historial de donaciones del donante a partir del id
     * @return array Lista de donaciones
     */
    public function obtenerDonacionesPorDonante(int $idDonante): array
    {
        $this->marcarVencidas(); //Actualizar donaciones vencidas
        $stmt = $this->conn->prepare(
                "SELECT d.id_donacion, d.id_evaluacion, d.fecha_recoleccion, d.fecha_caducidad, d.estado 
                FROM donaciones d
                JOIN evaluaciones_donante e ON e.id_evaluacion = d.id_evaluacion
                WHERE e.id_donante = ? 
                ORDER BY d.fecha_recoleccion DESC, d.id_donacion DESC");
        $stmt->bind_param("i",$idDonante);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Consultar donacion a partir del id
     * @return array|null datos de la donacion
     *                    null: donacion no existe
     */
    public function obtenerDonacionPorId(int $idDonacion): ?array
    {
        $this->marcarVencidas(); //Actualizar donaciones vencidas
        $stmt = $this->conn->prepare(
            "SELECT d.id_donacion, d.id_evaluacion, e.id_donante,
                    dn.nombre, dn.apellido, dn.id_tipo_sangre,
                    d.id_solicitud, d.fecha_recoleccion, d.fecha_caducidad, d.estado
            FROM donaciones d
            JOIN evaluaciones_donante e ON e.id_evaluacion = d.id_evaluacion
            JOIN donantes dn ON dn.id_donante = e.id_donante
            WHERE d.id_donacion = ?"
        );
        $stmt->bind_param("i", $idDonacion);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Lista donaciones con opcion de filtros combinables
     * @param string|null   $fechaIni Desde YYYY-MM-DD
     * @param string|null   $fechaFin Hasta YYYY-MM-DD
     * @param int|null      $idTipoSangre tabla de tipos sanguineos
     * @param string|null   $estado tabla de estados
     * @return array Lista de coincidencias con filtros,
     *               sin filtro devuelve todas
     */
    public function buscarDonaciones(
        ?string $fechaIni = null, 
        ?string $fechaFin = null,
        ?int $idTipoSangre = null, 
        ?string $estado=null
    ): array{
        $this->marcarVencidas(); //Actualizar donaciones vencidas
        $sql = "SELECT d.id_donacion, d.id_evaluacion, e.id_donante,
                        dn.nombre, dn.apellido, dn.id_tipo_sangre,
                        d.fecha_recoleccion, d.fecha_caducidad, d.estado,
                        u.nombre_usuario AS registrado_por
                FROM donaciones d
                JOIN evaluaciones_donante e ON e.id_evaluacion = d.id_evaluacion
                JOIN donantes dn ON dn.id_donante = e.id_donante
                LEFT JOIN usuarios u ON u.id_usuario = d.id_usuario
                WHERE 1 = 1";
        $tipos  = "";
        $params = [];

        if ($fechaIni !== null) {
            $sql .= " AND d.fecha_recoleccion >= ?";
            $tipos .= "s";
            $params[] = $fechaIni;
        }

        if ($fechaFin !== null) {
            $sql .= " AND d.fecha_recoleccion <= ?";
            $tipos .= "s";
            $params[] = $fechaFin;
        }

        if ($idTipoSangre !== null) {
            $sql .= " AND dn.id_tipo_sangre = ?";
            $tipos .= "i";
            $params[] = $idTipoSangre;
        }

        if ($estado !== null) {
            $sql .= " AND d.estado = ?";
            $tipos .= "s";
            $params[] = $estado;
        }

        // Orden DESC por defecto
        $sql .= " ORDER BY d.fecha_recoleccion DESC, d.id_donacion DESC";

        $stmt = $this->conn->prepare($sql);
        if ($params) {
            $stmt->bind_param($tipos, ...$params);
        }
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Actualizar estado de una donacion
     * @return string   actualizada|donacion_no_existe|transicion_invalida
     */
    public function actualizarDonacion(
        int $idDonacion, 
        string $estado
    ) : string
    {
        $stmt = $this->conn->prepare("SELECT estado,id_solicitud FROM donaciones WHERE id_donacion = ?");
        $stmt->bind_param("i", $idDonacion);
        $stmt->execute();
        $actual = $stmt->get_result()->fetch_assoc();

        if ($actual === null) {
            return 'donacion_no_existe';
        }

        if ($estado === $actual['estado']) {
            return 'sin_cambios';
        }

        if (!in_array($estado, self::TRANSICIONES[$actual['estado']] ?? [], true)) {
            return 'transicion_invalida';
        }

        if ($estado === 'DISPONIBLE') {
            if ($actual['id_solicitud'] !== null) {
                $estado = 'RESERVADA';
            }
        }
        $stmt = $this->conn->prepare(
            "UPDATE donaciones SET estado = ? WHERE id_donacion = ?"
        );
        $stmt->bind_param("si", $estado, $idDonacion);
        $stmt->execute();

        return 'actualizada';
    }

    /**
     * Vincular o desvincular solicitud
     * @param int|null $idSolicitud null:desvincula
     * @return string actualizada|donacion_no_existe|estado_invalido
     */
    public function asignarSolicitud(int $idDonacion, ?int $idSolicitud) : string
    {
        //Vincular solicitud asignada
        if ($idSolicitud !== null) {
            return $this->asignarUnidades($idSolicitud, [$idDonacion]);
        }

        $this->marcarVencidas(); //Actualizar donaciones vencidas

        // Evaluar estado de donacion
        $stmt = $this->conn->prepare(
            "SELECT estado FROM donaciones WHERE id_donacion = ?"
        );
        $stmt->bind_param("i", $idDonacion);
        $stmt->execute();
        $actual = $stmt->get_result()->fetch_assoc();

        if ($actual === null) {
            return 'donacion_no_existe';
        }

        // Desvincular solo desde RESERVADA
        if ($actual['estado'] !== 'RESERVADA') {
            return 'estado_invalido';
        }
        $stmt = $this->conn->prepare(
            "UPDATE donaciones SET estado = 'DISPONIBLE', id_solicitud = NULL
            WHERE id_donacion = ?"
        );
        $stmt->bind_param("i", $idDonacion);
        $stmt->execute();

        return 'actualizada';
    }

    /**
     * Asignar unidades a solicitud APROBADA
     * @return string actualizada|sin_unidades
     */
    public function asignarUnidades(int $idSolicitud, array $idsDonacion) : string
    {
        $ids = array_values(array_unique(array_map('intval', $idsDonacion)));
        if (!$ids) {
            return 'sin_unidades';
        }

        $this->marcarVencidas(); //Actualizar donaciones vencidas

        $this->conn->begin_transaction();
        try {
            $resultado = $this->validarYAsignar($idSolicitud, $ids);
            if ($resultado === 'actualizada') {
                $this->conn->commit();
            } else {
                $this->conn->rollback();
            }
            return $resultado;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Consultar unidades compatibles con la solicitud
     * @return array|null null: solicitud no existe
     */
    public function obtenerUnidadesCompatibles(int $idSolicitud): ?array
    {
        $this->marcarVencidas(); //Actualizar donaciones vencidas

        $sol = $this->infoSolicitud($idSolicitud);
        if ($sol === null) {
            return null;
        }

        $restante = max(0, (int)$sol['unidades_solicitadas'] - (int)$sol['asignadas']);
        $unidades = [];

        // Validar solicitud aprobada
        if ($sol['estado'] === 'APROBADA' && $restante > 0) {
            $tipos  = $this->tiposCompatibles($sol['tipo_requerido']);
            $marcas = implode(',', array_fill(0, count($tipos), '?'));

            // Priodidad tipo sanguineo exacto
            $stmt = $this->conn->prepare(
                "SELECT d.id_donacion, dn.id_tipo_sangre, ts.tipo AS tipo_sangre,
                        d.fecha_recoleccion, d.fecha_caducidad,
                        DATEDIFF(d.fecha_caducidad, CURDATE()) AS dias_para_vencer,
                        (ts.tipo = ?) AS es_exacta
                FROM donaciones d
                JOIN evaluaciones_donante e ON e.id_evaluacion = d.id_evaluacion
                JOIN donantes dn ON dn.id_donante = e.id_donante
                JOIN tipo_sangre ts ON ts.id_tipo_sangre = dn.id_tipo_sangre
                WHERE d.estado = 'DISPONIBLE'
                  AND d.id_solicitud IS NULL
                  AND d.fecha_caducidad >= CURDATE()
                  AND ts.tipo IN ($marcas)
                ORDER BY es_exacta DESC, d.fecha_caducidad ASC, d.id_donacion ASC"
            );
            $params = array_merge([$sol['tipo_requerido']], $tipos);
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            $stmt->execute();
            $unidades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        // Consultar unidades ya asignadas a la solicitud
        $stmt = $this->conn->prepare(
            "SELECT d.id_donacion, ts.tipo AS tipo_sangre, d.fecha_caducidad, d.estado
            FROM donaciones d
            JOIN evaluaciones_donante e ON e.id_evaluacion = d.id_evaluacion
            JOIN donantes dn ON dn.id_donante = e.id_donante
            JOIN tipo_sangre ts ON ts.id_tipo_sangre = dn.id_tipo_sangre
            WHERE d.id_solicitud = ? AND d.estado IN ('RESERVADA','UTILIZADA')
            ORDER BY d.fecha_caducidad ASC, d.id_donacion ASC"
        );
        $stmt->bind_param("i", $idSolicitud);
        $stmt->execute();
        $asignadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            'id_solicitud'         => $idSolicitud,
            'estado'               => $sol['estado'],
            'tipo_requerido'       => $sol['tipo_requerido'],
            'unidades_solicitadas' => (int)$sol['unidades_solicitadas'],
            'unidades_asignadas'   => (int)$sol['asignadas'],
            'cupo_restante'        => $restante,
            'unidades'             => $unidades,
            'asignadas'            => $asignadas,
        ];
    }

    /**
     * Validar cantidad y tipo de unidades solicitades para asignar unidades
     * @return array Datos resumen de la solicitud
     */
    private function infoSolicitud(int $idSolicitud, bool $bloquear = false): ?array
    {
        if ($bloquear) {
            $stmt = $this->conn->prepare(
                "SELECT 1 FROM solicitudes WHERE id_solicitud = ? FOR UPDATE"
            );
            $stmt->bind_param("i", $idSolicitud);
            $stmt->execute();
            $stmt->get_result();
        }

        $stmt = $this->conn->prepare(
            "SELECT s.estado, s.unidades_solicitadas, ts.tipo AS tipo_requerido,
                    (SELECT COUNT(*) FROM donaciones d
                      WHERE d.id_solicitud = s.id_solicitud
                        AND d.estado NOT IN ('DESCARTADA','VENCIDA')) AS asignadas
            FROM solicitudes s
            JOIN pacientes p ON p.id_paciente = s.id_paciente
            JOIN tipo_sangre ts ON ts.id_tipo_sangre = p.id_tipo_sangre
            WHERE s.id_solicitud = ?"
        );
        $stmt->bind_param("i", $idSolicitud);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Logica de asignacion de unidades
     * @return string actualizada|solicitud_no_existe|solicitud_no_aprobada
     *                |solicitud_completa|donacion_no_existe|estado_invalido|tipo_incompatible
     */
    private function validarYAsignar(int $idSolicitud, array $ids): string
    {
        $sol = $this->infoSolicitud($idSolicitud, true);
        if ($sol === null) {
            return 'solicitud_no_existe';
        }
        if ($sol['estado'] !== 'APROBADA') {
            return 'solicitud_no_aprobada';
        }
        if ((int)$sol['asignadas'] + count($ids) > (int)$sol['unidades_solicitadas']) {
            return 'solicitud_completa';
        }

        $compatibles = $this->tiposCompatibles($sol['tipo_requerido']);

        $stmtLock = $this->conn->prepare(
            "SELECT estado, id_solicitud, id_evaluacion FROM donaciones
             WHERE id_donacion = ? FOR UPDATE"
        );
        $stmtTipo = $this->conn->prepare(
            "SELECT ts.tipo
            FROM evaluaciones_donante e
            JOIN donantes dn ON dn.id_donante = e.id_donante
            JOIN tipo_sangre ts ON ts.id_tipo_sangre = dn.id_tipo_sangre
            WHERE e.id_evaluacion = ?"
        );
        $stmtUpd = $this->conn->prepare(
            "UPDATE donaciones SET estado = 'RESERVADA', id_solicitud = ?
             WHERE id_donacion = ?"
        );

        foreach ($ids as $idDonacion) {
            $stmtLock->bind_param("i", $idDonacion);
            $stmtLock->execute();
            $don = $stmtLock->get_result()->fetch_assoc();

            if ($don === null) {
                return 'donacion_no_existe';
            }
            // Solo unidades DISPONIBLES y libres
            if ($don['estado'] !== 'DISPONIBLE' || $don['id_solicitud'] !== null) {
                return 'estado_invalido';
            }

            $idEvaluacion = (int)$don['id_evaluacion'];
            $stmtTipo->bind_param("i", $idEvaluacion);
            $stmtTipo->execute();
            $tipo = $stmtTipo->get_result()->fetch_assoc()['tipo'] ?? null;

            if ($tipo === null || !in_array($tipo, $compatibles, true)) {
                return 'tipo_incompatible';
            }

            $stmtUpd->bind_param("ii", $idSolicitud, $idDonacion);
            $stmtUpd->execute();
        }

        return 'actualizada';
    }

    /**
     * Reasignar donacion a otro donante por medio de la evaluacion
     * @return string actualizada|donacion_no_existe|donacion_no_reasignable
     *                evaluacion_no_apta|evaluacion_usada|donante_no_disponible|intervalo_minimo|limite_anual
     */
    public function reasignarDonacion(int $idDonacion, int $idEvaluacionNueva) : string
    {
        $this->marcarVencidas(); //Actualizar donaciones vencidas

        // Validar que existe la donacion
        $stmt = $this->conn->prepare(
            "SELECT estado, id_evaluacion FROM donaciones WHERE id_donacion = ?"
        );
        $stmt->bind_param("i", $idDonacion);
        $stmt->execute();
        $don = $stmt->get_result()->fetch_assoc();

        if ($don === null) {
            return 'donacion_no_existe';
        }
        
        // Validar estado de donacion
        if (in_array($don['estado'], ['DESCARTADA', 'VENCIDA', 'RESERVADA', 'UTILIZADA'], true)) {
            return 'donacion_no_reasignable';
        }
        if ((int)$don['id_evaluacion'] === $idEvaluacionNueva) {
            return 'actualizada'; // sin cambios
        }

        // Validar la evaluacion a utilizar en la reasignacion
        $stmt = $this->conn->prepare(
            "SELECT e.resultado, e.id_donante, dn.activo, dn.sexo, (e.fecha_evaluacion = CURDATE()) AS fecha_hoy
            FROM evaluaciones_donante e
            JOIN donantes dn ON dn.id_donante = e.id_donante
            WHERE e.id_evaluacion = ?"
        );
        $stmt->bind_param("i", $idEvaluacionNueva);
        $stmt->execute();
        $eval = $stmt->get_result()->fetch_assoc();

        if ($eval === null || $eval['resultado'] !== 'APTO' || (int)$eval['fecha_hoy'] !== 1) {
            return 'evaluacion_no_apta';
        }
        if ((int)$eval['activo'] !== 1) {
            return 'donante_no_disponible';
        }

        // Verificar que la evaluacion no este asignada a otra donacion
        $stmt = $this->conn->prepare("SELECT 1 FROM donaciones WHERE id_evaluacion = ?");
        $stmt->bind_param("i", $idEvaluacionNueva);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return 'evaluacion_usada';
        }

        $bloqueo = $this->validarFrecuencia((int)$eval['id_donante'], $eval['sexo'], $idDonacion);
        if ($bloqueo !== null) {
            return $bloqueo['estado'];
        }

        $stmt = $this->conn->prepare(
        "UPDATE donaciones SET id_evaluacion = ? WHERE id_donacion = ?"
        );
        $stmt->bind_param("ii", $idEvaluacionNueva, $idDonacion);
        $stmt->execute();

        return 'actualizada';
    }

    /**
     * Evaluaciones APTAS de hoy
     * @return array Lista de evaluaciones elegibles para registrar donacion
     */
    public function obtenerEvaluacionesElegibles(): array
    {
        $result = $this->conn->query(
            "SELECT e.id_evaluacion, dn.id_donante, dn.nombre, dn.apellido, dn.dui
            FROM evaluaciones_donante e
            JOIN donantes dn ON dn.id_donante = e.id_donante
            WHERE dn.activo = 1
            AND e.resultado = 'APTO'
            AND e.fecha_evaluacion = CURDATE()
            AND e.id_evaluacion = (
                SELECT e2.id_evaluacion
                FROM evaluaciones_donante e2
                WHERE e2.id_donante = e.id_donante
                ORDER BY e2.fecha_evaluacion DESC, e2.id_evaluacion DESC
                LIMIT 1
            )
            AND NOT EXISTS (
                SELECT 1 FROM donaciones d WHERE d.id_evaluacion = e.id_evaluacion
            )
            ORDER BY dn.apellido, dn.nombre"
        );

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // -------------- UTILIDADES -------------- //
    /** 
     * Actualizar estado VENCIDA en donaciones caducadas
     * @return int cantidad de donaciones afectadas
    */
    public function marcarVencidas(): int
    {
        $stmt = $this->conn->prepare(
            "UPDATE donaciones SET estado = 'VENCIDA'
            WHERE estado IN ('DISPONIBLE', 'RESERVADA')
            AND fecha_caducidad < CURDATE()"
        );
        $stmt->execute();
        return $stmt->affected_rows;
    }

    /**
     * Devolver compatibilidad sanguinea
     */
    private function tiposCompatibles(string $tipoRequerido): array
    {
        return self::COMPATIBILIDAD[$tipoRequerido] ?? [$tipoRequerido];
    }

    /**
     * Verifica el intervalo minimo y el limite anual
     * @param int|null $excluirDonacion donacion a ignorar (al reasignar)
     * @return array|null null: puede donar
     *                    ['estado' => 'intervalo_minimo','proxima_fecha' => 'YYYY-MM-DD']
     *                    ['estado' => 'limite_anual', 'limite' => int]
     */
    private function validarFrecuencia(int $idDonante, string $sexo, ?int $excluirDonacion = null): ?array
    {
        $excluir = $excluirDonacion ?? 0;

        $stmt = $this->conn->prepare(
            "SELECT DATEDIFF(CURDATE(), MAX(d.fecha_recoleccion)) AS dias,
                    DATE_ADD(MAX(d.fecha_recoleccion), INTERVAL " . self::INTERVALO_DIAS . " DAY) AS proxima_fecha,
                    COALESCE(SUM(d.fecha_recoleccion > DATE_SUB(CURDATE(), INTERVAL 12 MONTH)), 0) AS en_12_meses
            FROM donaciones d
            JOIN evaluaciones_donante e ON e.id_evaluacion = d.id_evaluacion
            WHERE e.id_donante = ? AND d.id_donacion <> ?"
        );
        $stmt->bind_param("ii", $idDonante, $excluir);
        $stmt->execute();
        $f = $stmt->get_result()->fetch_assoc();

        // Sin donaciones previas, 'dias' es NULL y no hay restriccion
        if ($f['dias'] !== null && (int)$f['dias'] < self::INTERVALO_DIAS) {
            return ['estado' => 'intervalo_minimo', 'proxima_fecha' => $f['proxima_fecha']];
        }

        $limite = self::LIMITE_ANUAL[$sexo];
        if ((int)$f['en_12_meses'] >= $limite) {
            return ['estado' => 'limite_anual', 'limite' => $limite];
        }

        return null;
    }
}