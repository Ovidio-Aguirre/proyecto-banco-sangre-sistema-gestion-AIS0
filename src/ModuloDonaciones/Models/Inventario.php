<?php

class Inventario{
    private mysqli $conn;

    public function __construct(mysqli $dbConnection){
        $this->conn = $dbConnection;
    }

    /**
     * Obtener unidades por tipo de sangre
     * @return array [['id_tipo_sangre', 'tipo_sangre', 'disponibles', 'reservadas','vencidas'], ...]
     */
    public function obtenerInventario(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id_tipo_sangre, t.tipo AS tipo_sangre,
                COUNT(CASE WHEN d.estado_real = 'DISPONIBLE' THEN 1 END) AS disponibles,
                COUNT(CASE WHEN d.estado_real = 'RESERVADA'  THEN 1 END) AS reservadas,
                COUNT(CASE WHEN d.estado_real = 'VENCIDA'    THEN 1 END) AS vencidas
            FROM tipo_sangre t
            LEFT JOIN donantes dn ON dn.id_tipo_sangre = t.id_tipo_sangre
            LEFT JOIN evaluaciones_donante e ON e.id_donante = dn.id_donante
            LEFT JOIN (
                SELECT id_donacion, id_evaluacion,
                    CASE WHEN estado IN ('DISPONIBLE','RESERVADA') AND fecha_caducidad < CURDATE()
                            THEN 'VENCIDA' ELSE estado END AS estado_real
                FROM donaciones
                WHERE estado IN ('DISPONIBLE', 'RESERVADA', 'VENCIDA')
            ) d ON d.id_evaluacion = e.id_evaluacion
            GROUP BY t.id_tipo_sangre, t.tipo
            ORDER BY t.id_tipo_sangre"
        );
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener inventario por tipo de sangre
     * @return array|null ['id_tipo_sangre', 'tipo_sangre', 'disponibles', 'reservadas',
     *                    'vencidas', 'unidades' => ['id_donacion', 'fecha_recoleccion', 
     *                    'fecha_caducidad', 'estado', 'dias_para_vencer']]
     *                    null: el tipo de sangre no existe
     */
    public function obtenerInventarioPorTipo(int $idTipoSangre): ?array
    {
        // Totales
        $resumen = null;
        foreach ($this->obtenerInventario() as $fila) {
            if ((int)$fila['id_tipo_sangre'] === $idTipoSangre) {
                $resumen = $fila;
                break;
            }
        }
        if ($resumen === null) {
            return null;
        }

        // Unidades individuales
        $stmt = $this->conn->prepare(
            "SELECT d.id_donacion, d.fecha_recoleccion, d.fecha_caducidad,
                CASE WHEN d.estado IN ('DISPONIBLE','RESERVADA') AND d.fecha_caducidad < CURDATE()
                THEN 'VENCIDA' ELSE d.estado END AS estado,
                GREATEST(DATEDIFF(d.fecha_caducidad, CURDATE()), 0) AS dias_para_vencer
            FROM donaciones d
            JOIN evaluaciones_donante e ON e.id_evaluacion = d.id_evaluacion
            JOIN donantes dn ON dn.id_donante = e.id_donante
            WHERE dn.id_tipo_sangre = ?
            AND d.estado IN ('DISPONIBLE', 'RESERVADA', 'VENCIDA')
            ORDER BY d.fecha_caducidad ASC, d.id_donacion ASC"
        );
        $stmt->bind_param("i", $idTipoSangre);
        $stmt->execute();
        $resumen['unidades'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return $resumen;
    }
}