
<?php

function seedUsuarios(mysqli $conn): void
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
               WHERE rol = ?";

    $stmtRol = $conn->prepare($sqlRol);

    foreach ($usuarios as $usuario) {

        $rol = $usuario["rol"];
        $stmtRol->bind_param("s", $rol);
        $stmtRol->execute();

        $resultado = $stmtRol->get_result();
        $fila = $resultado->fetch_assoc();

        $idRol = $fila["id_rol"] ?? null;

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
                      WHERE correo = ?
                      OR nombre_usuario = ?
                      LIMIT 1";

        $stmtBuscar = $conn->prepare($sqlBuscar);

        $correo = $usuario["correo"];
        $nombre = $usuario["usuario"];

        $stmtBuscar->bind_param("ss", $correo, $nombre);
        $stmtBuscar->execute();

        $resultado = $stmtBuscar->get_result();
        $fila = $resultado->fetch_assoc();

        $idUsuario = $fila["id_usuario"] ?? null;

        if ($idUsuario) {

            $sql = "UPDATE usuarios
                    SET id_rol = ?,
                        nombre_usuario = ?,
                        contrasena = ?,
                        correo = ?,
                        activo = 1
                    WHERE id_usuario = ?";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "isssi",
                $idRol,
                $nombre,
                $hash,
                $correo,
                $idUsuario
            );

            $stmt->execute();

        } else {

            $sql = "INSERT INTO usuarios
                    (id_rol, nombre_usuario, contrasena, correo, activo)
                    VALUES (?, ?, ?, ?, 1)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "isss",
                $idRol,
                $nombre,
                $hash,
                $correo
            );

            $stmt->execute();
        }

        $stmtBuscar->close();
    }

    $stmtRol->close();

    echo "Usuarios insertados correctamente.\n";
}

