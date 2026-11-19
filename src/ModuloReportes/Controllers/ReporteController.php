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
            'actividad'            => $metodo === 'GET' ? $this->actividad() : noPermitido(),
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

    // GET /reportes/actividad?pagina=1&por_pagina=20
    //     &busqueda=&desde=&hasta=                           (log completo, solo admin)
    private function actividad(): void
    {
        // Modo dashboard: solo las últimas N acciones
        if (isset($_GET['limite'])) {
            $limite = filter_var($_GET['limite'], FILTER_VALIDATE_INT);
            if ($limite === false || $limite < 1 || $limite > 20) {
                respuesta(400, ['success' => false, 'mensaje' => 'El límite debe ser un entero entre 1 y 20']);
            }
            $res = $this->modelo->obtenerActividad($limite);
            respuesta(200, ['success' => true, 'actividad' => $res['actividad']]);
        }

        // Modo log completo: solo administrador
        if (($_SESSION['rol'] ?? '') !== ROL_ADMIN) {
            respuesta(403, ['success' => false, 'mensaje' => 'Solo el administrador puede ver el registro completo']);
        }

        $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
        if ($pagina === false || $pagina < 1) {
            respuesta(400, ['success' => false, 'mensaje' => 'Página inválida']);
        }

        $porPagina = filter_var($_GET['por_pagina'] ?? 20, FILTER_VALIDATE_INT);
        if ($porPagina === false || $porPagina < 1 || $porPagina > 100) {
            respuesta(400, ['success' => false, 'mensaje' => 'por_pagina debe ser un entero entre 1 y 100']);
        }

        $busqueda = null;
        if (isset($_GET['busqueda'])) {
            $busqueda = is_string($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
            if ($busqueda === '') { $busqueda = null; }
        }

        $desde = isset($_GET['desde']) ? validarFecha($_GET['desde'], 'desde', true) : null;
        $hasta = isset($_GET['hasta']) ? validarFecha($_GET['hasta'], 'hasta', true) : null;
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            respuesta(400, ['success' => false, 'mensaje' => 'desde no puede ser mayor a hasta']);
        }

        $res = $this->modelo->obtenerActividad($porPagina, ($pagina - 1) * $porPagina, $busqueda, $desde, $hasta);

        respuesta(200, [
            'success'    => true,
            'actividad'  => $res['actividad'],
            'total'      => $res['total'],
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'paginas'    => (int)ceil($res['total'] / $porPagina)
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