<?php

class Reporte
{
    private mysqli $conn;

    // Definir las donaciones no efectivas
    private const EXCLUIDAS = "('EN_EVALUACION', 'DESCARTADA')";

    public function __construct(mysqli $dbConnection){
        $this->conn = $dbConnection;
    }

    /**
     * Obtener las cifras de las tarjetas del dashboard en el periodo
     * @param string $periodo hoy|semanal|mensual|3-meses|6-meses|anual
     * @return array ['donantes', 'donaciones',
     *               'solicitudes' => ['total', 'atendidas', 'pendientes']]
     */
    public function obtenerResumen(string $periodo): array
    {
        $desde = $this->fechaInicio($periodo)->format('Y-m-d');

        // Donaciones efectivas en el periodo
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS donaciones
            FROM donaciones
            WHERE estado NOT IN " . self::EXCLUIDAS . "
            AND fecha_recoleccion >= ?"
        );
        $stmt->bind_param("s", $desde);
        $stmt->execute();
        $don = $stmt->get_result()->fetch_assoc();

        // Donantes activos (histórico, no depende del periodo)
        $donantes = (int)$this->conn->query(
            "SELECT COUNT(*) FROM donantes WHERE activo = 1"
        )->fetch_row()[0];

        // Solicitudes: atendidas = RECHAZADA o ENTREGADA
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total,
                COALESCE(SUM(estado IN ('RECHAZADA', 'ENTREGADA')), 0) AS atendidas
            FROM solicitudes
            WHERE fecha_solicitud >= ?
            AND estado <> 'INHABILITADA'"
        );
        $stmt->bind_param("s", $desde);
        $stmt->execute();
        $sol = $stmt->get_result()->fetch_assoc();

        $total     = (int)$sol['total'];
        $atendidas = (int)$sol['atendidas'];

        return [
            'donantes'    => $donantes,
            'donaciones'  => (int)$don['donaciones'],
            'solicitudes' => [
                'total'      => $total,
                'atendidas'  => $atendidas,
                'pendientes' => $total - $atendidas,
            ],
        ];
    }

    /**
     * Obtener donaciones efectivas del periodo (incluye periodos en 0)
     * Agrupa por día en hoy|semanal|mensual y por mes en el resto
     * @param string $periodo hoy|semanal|mensual|3-meses|6-meses|anual
     * @return array [['fecha' => 'YYYY-MM-DD' o 'YYYY-MM', 'total' => int], ...]
     */
    public function obtenerDonacionesMensuales(string $periodo): array
    {
        $inicio = $this->fechaInicio($periodo);
        $desde  = $inicio->format('Y-m-d');
        $porDia = in_array($periodo, ['hoy', 'semanal', 'mensual'], true);

        $formatoSql = $porDia ? '%Y-%m-%d' : '%Y-%m';
        $formatoPhp = $porDia ? 'Y-m-d' : 'Y-m';

        $stmt = $this->conn->prepare(
            "SELECT DATE_FORMAT(fecha_recoleccion, '$formatoSql') AS fecha, COUNT(*) AS total
            FROM donaciones
            WHERE estado NOT IN " . self::EXCLUIDAS . "
            AND fecha_recoleccion >= ?
            GROUP BY fecha"
        );
        $stmt->bind_param("s", $desde);
        $stmt->execute();

        $conteo = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) {
            $conteo[$fila['fecha']] = (int)$fila['total'];
        }

        // Recorre cada día o mes del rango para no dejar huecos en la gráfica
        $serie  = [];
        $actual = clone $inicio;
        $fin    = $porDia
            ? (new DateTime('today'))
            : (new DateTime('first day of this month'))->setTime(0, 0);
        while ($actual <= $fin) {
            $clave   = $actual->format($formatoPhp);
            $serie[] = ['fecha' => $clave, 'total' => $conteo[$clave] ?? 0];
            $actual->modify($porDia ? '+1 day' : '+1 month');
        }

        return $serie;
    }

    /**
     * Calcular el primer día del periodo solicitado
     * @return DateTime Inicio del rango a las 00:00 (valor desconocido equivale a mensual)
     */
    private function fechaInicio(string $periodo): DateTime
    {
        $fecha = match ($periodo) {
            'hoy'     => new DateTime('today'),
            'semanal' => new DateTime('monday this week'),
            '3-meses' => new DateTime('first day of this month -2 months'),
            '6-meses' => new DateTime('first day of this month -5 months'),
            'anual'   => new DateTime('first day of january this year'),
            default   => new DateTime('first day of this month'),
        };

        return $fecha->setTime(0, 0);
    }
}