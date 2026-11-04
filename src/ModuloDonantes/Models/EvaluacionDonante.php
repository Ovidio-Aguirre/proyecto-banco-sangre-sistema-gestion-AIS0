<?php

class EvaluacionDonante{
    private mysqli $conn;

    public function __construct(mysqli $dbConnection){
        $this->conn = $dbConnection;
    }

    /**
     * Registrar nueva evaluacion a un donante activo
     * @return array ['estado' => creado|donante_no_disponible,'id_evaluacion' (creado)]
     */
    public function registrarEvaluacion(
        int $idDonante,
        float $peso,
        float $altura,
        string $resultado, //'APTO','NO_APTO'
        ?string $motivo = null
        ) : array
        {
            $stmt = $this->conn->prepare(
                "SELECT 1 FROM donantes WHERE activo = 1 AND id_donante = ?");
            $stmt->bind_param("i",$idDonante);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0){
                return ['estado' => 'donante_no_disponible'];
            }

            $stmt = $this->conn->prepare(
                "INSERT INTO evaluaciones_donante
                (id_donante, fecha_evaluacion, peso, altura, resultado, motivo)
                VALUES (?, CURDATE(), ?, ?, ?, ?)"
            );
            $stmt->bind_param("iddss", $idDonante, $peso, $altura, $resultado, $motivo);
            $stmt->execute();

            return ['estado' => 'creado','id_evaluacion' => $this->conn->insert_id]; 
        }
    
    /**
     * Listar el historial de evaluaciones de un donante
     * @return array Lista de evaluaciones
     */
    public function obtenerHistorial(int $idDonante) : array
    {
        $stmt = $this->conn->prepare(
                "SELECT id_evaluacion, fecha_evaluacion, peso, altura, resultado, motivo 
                FROM evaluaciones_donante 
                WHERE id_donante = ? 
                ORDER BY fecha_evaluacion DESC, id_evaluacion DESC");
        $stmt->bind_param("i",$idDonante);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener la ultima evaluacion del donante
     * @return array Datos de la evaluacion
     */
    public function obtenerUltimaEvaluacion(int $idDonante) : ?array
    {
        $stmt = $this->conn->prepare(
                "SELECT id_evaluacion, fecha_evaluacion, peso, altura, resultado, motivo 
                FROM evaluaciones_donante 
                WHERE id_donante = ? 
                ORDER BY fecha_evaluacion DESC, id_evaluacion DESC 
                LIMIT 1");
        $stmt->bind_param("i",$idDonante);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }
}