<?php

function seedUsuarios(PDO $pdo): void
{
    $usuarios = [
        [
            "rol" => "Administrador",
            "usuario" => "admin",
            "contrasena" => "Admin123*",
            "correo" => "admin@bancosangre.test"
        ],
        [
            "rol" => "Médico",
            "usuario" => "medico",
            "contrasena" => "Medico123*",
            "correo" => "medico@bancosangre.test"
        ]
    ];

    $sqlRol = "SELECT id_rol
               FROM roles
               WHERE rol = :rol";

    $stmtRol = $pdo->prepare($sqlRol);

    foreach ($usuarios as $usuario) {

        $stmtRol->execute([
            ":rol" => $usuario["rol"]
        ]);

        $idRol = $stmtRol->fetchColumn();

        if (!$idRol) {
            throw new Exception(
                "No se encontró el rol: " . $usuario["rol"]
            );
        }

        $hash = password_hash(
            $usuario["contrasena"],
            PASSWORD_DEFAULT
        );

        $sqlBuscar = "SELECT id_usuario
                      FROM usuarios
                      WHERE correo = :correo
                      OR nombre_usuario = :nombre
                      LIMIT 1";

        $stmtBuscar = $pdo->prepare($sqlBuscar);

        $stmtBuscar->execute([
            ":correo" => $usuario["correo"],
            ":nombre" => $usuario["usuario"]
        ]);

        $idUsuario = $stmtBuscar->fetchColumn();

        if ($idUsuario) {

            $sql = "UPDATE usuarios
                    SET id_rol = :id_rol,
                        nombre_usuario = :nombre,
                        contrasena = :contrasena,
                        correo = :correo,
                        activo = 1
                    WHERE id_usuario = :id_usuario";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":id_rol" => $idRol,
                ":nombre" => $usuario["usuario"],
                ":contrasena" => $hash,
                ":correo" => $usuario["correo"],
                ":id_usuario" => $idUsuario
            ]);

        } else {

            $sql = "INSERT INTO usuarios
                    (id_rol, nombre_usuario, contrasena, correo, activo)
                    VALUES (:id_rol, :nombre, :contrasena, :correo, 1)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":id_rol" => $idRol,
                ":nombre" => $usuario["usuario"],
                ":contrasena" => $hash,
                ":correo" => $usuario["correo"]
            ]);
        }
    }

    echo "Usuarios insertados correctamente.\n";
}