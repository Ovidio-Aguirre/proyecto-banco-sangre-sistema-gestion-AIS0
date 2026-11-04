<?php
class InventarioController implements Controlador
{   
    public function __construct(
        private Inventario $modelo
    ){}

    public function control(string $metodo, array $partes): void
    {
        // Parametros de las rutas
        $id = $partes[1] ?? null;

        if (isset($partes[2])){ noEncontrado(); }

        // Validar solo metodo de lectura
        if ($metodo !== 'GET'){ noPermitido(); }

        // Ruta /inventario
        if ($id === null){
            $this->listarInventario();
            return;
        }

        // Ruta /inventario/{idTipoSangre}
        $this->obtenerPorTipo(validarId($id, 'ID de tipo de sangre inválido'));
    }

    /* --- PETICIONES --- */
    // GET /inventario
    private function listarInventario(): void
    {
        respuesta(200, ['success' => true, 'inventario' => $this->modelo->obtenerInventario()]);
    }

    // GET /inventario/{idTipoSangre}
    private function obtenerPorTipo(int $idTipoSangre): void
    {
        $inventario = $this->modelo->obtenerInventarioPorTipo($idTipoSangre);
        if ($inventario === null) {
            respuesta(404, ['success' => false, 'mensaje' => 'Tipo de sangre no encontrado']);
        }
        respuesta(200, ['success' => true, 'inventario' => $inventario]);
    }
}