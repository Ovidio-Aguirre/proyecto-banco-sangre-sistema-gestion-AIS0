<?php

class EvaluacionDonante{
    private mysqli $conn;
    private const PESO_MINIMO = 50;  // kg
    private const EDAD_MINIMA = 18;
    private const EDAD_LIMITE = 65;

    public function __construct(mysqli $dbConnection){
        $this->conn = $dbConnection;
    }

    /**
     * Registrar nueva evaluacion a un donante activo
     * Resultado NO_APTO forzado si no cumple la edad o peso requerido
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
                "SELECT TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad
                FROM donantes WHERE activo = 1 AND id_donante = ?");
            $stmt->bind_param("i",$idDonante);
            $stmt->execute();
            $donante = $stmt->get_result()->fetch_assoc();
            if ($donante === null){
                return ['estado' => 'donante_no_disponible'];
            }

            // Validar aptitudes obligatorias
            $razones = [];
            if ($peso < self::PESO_MINIMO) {
                $razones[] = 'Peso menor al mínimo de ' . self::PESO_MINIMO . ' kg';
            }
            $edad = (int)$donante['edad'];
            if ($edad < self::EDAD_MINIMA || $edad >= self::EDAD_LIMITE) {
                $razones[] = 'Edad fuera del rango permitido (' . self::EDAD_MINIMA . ' a ' . (self::EDAD_LIMITE - 1) . ' años)';
            }
            $forzado = false;
            if ($razones) {
                $forzado = ($resultado === 'APTO');
                $resultado = 'NO_APTO';
                $automatico = implode('; ', $razones);
                $motivo = $motivo !== null ? $automatico . '. ' . $motivo : $automatico;
                $motivo = mb_substr($motivo, 0, 255);
            }

            $stmt = $this->conn->prepare(
                "INSERT INTO evaluaciones_donante
                (id_donante, fecha_evaluacion, peso, altura, resultado, motivo)
                VALUES (?, CURDATE(), ?, ?, ?, ?)"
            );
            $stmt->bind_param("iddss", $idDonante, $peso, $altura, $resultado, $motivo);
            $stmt->execute();

            return [
                'estado'        => 'creado',
                'id_evaluacion' => $this->conn->insert_id,
                'resultado'     => $resultado,
                'motivo'        => $motivo,
                'forzado'       => $forzado
            ];
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