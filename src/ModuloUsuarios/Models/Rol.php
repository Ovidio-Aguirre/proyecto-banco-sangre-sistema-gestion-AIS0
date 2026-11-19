<?php

class Rol
{
    private mysqli $conn;

    public function __construct(mysqli $dbConnection){
        $this->conn = $dbConnection;
    }

    /**
     * Listar los roles del sistema (Administrador y Médico)
     * @return array [['id_rol', 'rol'], ...]
     */
    public function listarRoles(): array
    {
        $admin  = ROL_ADMIN;
        $medico = ROL_MEDICO;

        $stmt = $this->conn->prepare(
            "SELECT id_rol, rol FROM roles WHERE rol IN (?, ?) ORDER BY id_rol"
        );
        $stmt->bind_param("ss", $admin, $medico);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}