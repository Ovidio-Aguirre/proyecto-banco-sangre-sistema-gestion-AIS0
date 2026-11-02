<?php
class EvaluacionController
{
    public function __construct(private EvaluacionDonante $modelo){}

    public function control(string $metodo, int $idDonante): void
    {
        // Ruta /donantes/{id}/evaluaciones
        match ($metodo) {
            'GET'   => $this->consultarEvaluaciones($idDonante),
            'POST'   => $this->registrarEvaluacion($idDonante),
            default => noPermitido(),
        };
    }

    /* --- PETICIONES --- */
    // GET
    private function consultarEvaluaciones(int $idDonante): void 
    {
        //Opcional. Historial completo
        if (($_GET['historial'] ?? null) === '1') {
            respuesta(200, ['success' => true, 'evaluaciones' => $this->modelo->obtenerHistorial($idDonante)]);    
        }

        // Por defecto: Ultima evaluacion
        $evaluacion = $this->modelo->obtenerUltimaEvaluacion($idDonante);
        if ($evaluacion === null) {
            respuesta(404, ['success' => false, 'mensaje' => 'No hay evaluaciones registradas']);
        }
        respuesta(200, ['success' => true, 'evaluacion' => $evaluacion]);
    }

    // POST
    private function registrarEvaluacion(int $idDonante): void 
    {
        $data = leerJson();

        /* --- Validaciones --- */
        // Campos obligatorios vacios
        $vacios = [];
        foreach (['peso', 'altura', 'resultado'] as $campo) {
            if (!isset($data[$campo]) || !is_scalar($data[$campo]) || trim((string)$data[$campo]) === '') {
                $vacios[] = $campo;
            }
        }

        if ($vacios){
            respuesta(400, ['success' => false, 'mensaje' => 'Campos vacios', 'campos' => $vacios]);
        }

        // Formatos
        if (!is_numeric($data['peso']) || $data['peso'] <= 0 || $data['peso'] > 999.99) {
            respuesta(400, ['success' => false, 'mensaje' => 'Peso inválido (kg, máximo 999.99)']);
        }

        if (!is_numeric($data['altura']) || $data['altura'] <= 0 || $data['altura'] > 9.99) {
            respuesta(400, ['success' => false, 'mensaje' => 'Altura inválida. Valor debe ser en metros)']);
        }

        if (!in_array($data['resultado'], ['APTO', 'NO_APTO'], true)) {
            respuesta(400, ['success' => false, 'mensaje' => 'Resultado debe ser APTO o NO_APTO']);
        }

        // Validaciones -> Motivo: Obligatorio en NO_APTO
         $motivo = null;
        if (isset($data['motivo'])) {
            if (!is_string($data['motivo'])) {
                respuesta(400, ['success' => false, 'mensaje' => 'Motivo inválido']);
            }
            $motivo = trim($data['motivo']) ?: null;
        }
        if ($data['resultado'] === 'NO_APTO' && $motivo === null) {
            respuesta(400, ['success' => false, 'mensaje' => 'El motivo es obligatorio si el resultado es NO_APTO']);
        }
        if ($motivo !== null && mb_strlen($motivo) > 255) {
            respuesta(400, ['success' => false, 'mensaje' => 'El motivo no puede superar 255 caracteres']);
        }

        //Insertar datos
        $res = $this->modelo->registrarEvaluacion(
            $idDonante,
            (float)$data['peso'],
            (float)$data['altura'],
            $data['resultado'],
            $motivo
        );

        if ($res['estado'] === 'donante_no_disponible') {
            respuesta(404, ['success' => false, 'mensaje' => 'Donante no encontrado o inactivo']);
        }
        respuesta(201, ['success' => true, 'id_evaluacion' => $res['id_evaluacion']]);
    }
}


