<?php

function seedRoles(PDO $pdo): void
{
    $roles = [
        "Administrador",
        "Médico",
        "Recepcionista",
        "Auditor"
    ];

    $sql = "INSERT INTO roles (rol)
            VALUES (:rol)
            ON DUPLICATE KEY UPDATE rol = VALUES(rol)";

    $stmt = $pdo->prepare($sql);

    foreach ($roles as $rol) {
        $stmt->execute([
            ":rol" => $rol
        ]);
    }

    echo "Roles insertados correctamente.\n";
}