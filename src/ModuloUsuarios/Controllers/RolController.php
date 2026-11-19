<?php

class RolController implements Controlador
{
    public function __construct(
        private Rol $modelo
    ){}

    public function control(string $metodo, array $partes): void
    {
        requerirRol([ROL_ADMIN]); // Solo administrador

        if (isset($partes[1])){ noEncontrado(); }

        // Ruta /roles
        match ($metodo) {
            'GET'   => $this->listarRoles(),
            default => noPermitido(),
        };
    }

    /* --- PETICIONES --- */
    // GET /roles
    private function listarRoles(): void
    {
        respuesta(200, ['success' => true, 'roles' => $this->modelo->listarRoles()]);
    }
}