<?php

class ReporteController implements Controlador
{
    private const PERIODOS = ['hoy', 'semanal', 'mensual', '3-meses', '6-meses', 'anual'];

    public function __construct(
        private Reporte $modelo
    ){}

    public function control(string $metodo, array $partes): void
    {
        $sub = $partes[1] ?? null;

        if (isset($partes[2])){ noEncontrado(); }

        // Ruta /reportes/{recurso}
        match ($sub) {
            'resumen'              => $metodo === 'GET' ? $this->resumen() : noPermitido(),
            'donaciones-mensuales' => $metodo === 'GET' ? $this->donacionesMensuales() : noPermitido(),
            default                => noEncontrado(),
        };
    }

    /* --- PETICIONES --- */
    // GET /reportes/resumen?periodo=3-meses|6-meses|anual
    private function resumen(): void
    {
        $periodo = $this->validarPeriodo();
        respuesta(200, [
            'success' => true,
            'periodo' => $periodo,
            'resumen' => $this->modelo->obtenerResumen($periodo)
        ]);
    }

    // GET /reportes/donaciones-mensuales?periodo=3-meses|6-meses|anual
    private function donacionesMensuales(): void
    {
        $periodo = $this->validarPeriodo();
        respuesta(200, [
            'success'    => true,
            'periodo'    => $periodo,
            'donaciones' => $this->modelo->obtenerDonacionesMensuales($periodo)
        ]);
    }

    /* --- UTILIDADES --- */
    // Validaciones
    private function validarPeriodo(): string
    {
        $periodo = $_GET['periodo'] ?? 'mensual';
        if (!is_string($periodo) || !in_array($periodo, self::PERIODOS, true)) {
            respuesta(400, ['success' => false, 'mensaje' => 'Periodo inválido', 'permitidos' => self::PERIODOS]);
        }
        return $periodo;
    }
}