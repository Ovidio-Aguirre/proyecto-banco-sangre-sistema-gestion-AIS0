<?php

class Donacion{
    private mysqli $conn;

    // Transicion manual de estados
    private const TRANSICIONES = [
        'EN_EVALUACION' => ['DESCARTADA', 'DISPONIBLE'],
        'DISPONIBLE'    => [],
        'RESERVADA'     => ['UTILIZADA'],
        'VENCIDA'       => ['DESCARTADA'],
        'DESCARTADA'    => [],
        'UTILIZADA'     => [],
    ];

    public function __construct(mysqli $dbConnection){
        $this-> conn = $dbConnection;
    }

    /**
     * Registrar una donacion para donantes aptos
     * @return array ['estado' => creado|donante_no_disponible|sin_evaluacion
     *               |no_apto|evaluacion_usada, 'id_donacion' (creado)]
     */
    public function registrarDonacion(
        int $idDonante, 
        int $idUsuario, 
        ?int $idSolicitud = null
    ) : array
    {
        // Validar donante activo
        $stmt = $this->conn->prepare(
                "SELECT 1 FROM donantes WHERE activo = 1 AND id_donante = ?");
        $stmt->bind_param("i",$idDonante);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0){
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

        // fecha_caducidad = fecha_recoleccion + 42 dias
        $stmt = $this->conn->prepare(
            "INSERT INTO donaciones
            (id_evaluacion, id_usuario, id_solicitud, fecha_recoleccion, fecha_caducidad, estado)
            VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 42 DAY), 'EN_EVALUACION')"
        );
        $stmt->bind_param("iii", $idEvaluacion, $idUsuario, $idSolicitud);
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

        if ($idSolicitud !== null) {
            // Vincular solo desde DISPONIBLE
            if ($actual['estado'] !== 'DISPONIBLE') {
                return 'estado_invalido';
            }
            $stmt = $this->conn->prepare(
                "UPDATE donaciones SET estado = 'RESERVADA', id_solicitud = ?
                WHERE id_donacion = ?"
            );
            $stmt->bind_param("ii", $idSolicitud, $idDonacion);
        } else {
            // Desvincular solo desde RESERVADA
            if ($actual['estado'] !== 'RESERVADA') {
                return 'estado_invalido';
            }
            $stmt = $this->conn->prepare(
                "UPDATE donaciones SET estado = 'DISPONIBLE', id_solicitud = NULL
                WHERE id_donacion = ?"
            );
            $stmt->bind_param("i", $idDonacion);
        }
        $stmt->execute();

        return 'actualizada';
    }

    /**
     * Reasignar donacion a otro donante por medio de la evaluacion
     * @return string actualizada|donacion_no_existe|donacion_no_reasignable
     *                evaluacion_no_apta|evaluacion_usada|donante_no_disponible
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
            "SELECT e.resultado, dn.activo, (e.fecha_evaluacion = CURDATE()) AS fecha_hoy
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
}