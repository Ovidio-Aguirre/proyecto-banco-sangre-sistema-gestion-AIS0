<?php

function seedRoles(mysqli $conn): void
{
    $roles = [
        "Administrador",
        "Médico"
    ];

    $sql = "INSERT INTO roles (rol)
            VALUES (?)
            ON DUPLICATE KEY UPDATE rol = VALUES(rol)";

    $stmt = $conn->prepare($sql);

    foreach ($roles as $rol) {
        $stmt->bind_param("s", $rol);
        $stmt->execute();
    }

    echo "Roles insertados correctamente.\n";
}