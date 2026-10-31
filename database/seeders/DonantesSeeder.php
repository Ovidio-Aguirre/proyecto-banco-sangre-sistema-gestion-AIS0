<?php

function seedDonantes(PDO $pdo): void
{
    $donantes = [
        [
            "tipo" => "A+",
            "dui" => "00000000-1",
            "nombre" => "Juan",
            "apellido" => "Pérez",
            "contacto" => "7000-0001",
            "fecha_nacimiento" => "1990-05-12",
            "sexo" => "M"
        ],
        [
            "tipo" => "O-",
            "dui" => "00000000-2",
            "nombre" => "María",
            "apellido" => "López",
            "contacto" => "7000-0002",
            "fecha_nacimiento" => "1992-09-23",
            "sexo" => "F"
        ],
        [
            "tipo" => "B+",
            "dui" => "00000000-3",
            "nombre" => "Carlos",
            "apellido" => "Hernández",
            "contacto" => "7000-0003",
            "fecha_nacimiento" => "1985-02-18",
            "sexo" => "M"
        ],
        [
            "tipo" => "AB+",
            "dui" => "00000000-4",
            "nombre" => "Ana",
            "apellido" => "Martínez",
            "contacto" => "7000-0004",
            "fecha_nacimiento" => "1995-11-07",
            "sexo" => "F"
        ]
    ];

    $sqlTipo = "SELECT id_tipo_sangre
                FROM tipo_sangre
                WHERE tipo = :tipo";

    $stmtTipo = $pdo->prepare($sqlTipo);

    $sql = "INSERT INTO donantes
            (id_tipo_sangre, dui, nombre, apellido,
             contacto, fecha_nacimiento, sexo, activo)
            VALUES
            (:id_tipo, :dui, :nombre, :apellido,
             :contacto, :fecha, :sexo, 1)
            ON DUPLICATE KEY UPDATE
            id_tipo_sangre = VALUES(id_tipo_sangre),
            nombre = VALUES(nombre),
            apellido = VALUES(apellido),
            contacto = VALUES(contacto),
            fecha_nacimiento = VALUES(fecha_nacimiento),
            sexo = VALUES(sexo),
            activo = 1";

    $stmt = $pdo->prepare($sql);

    foreach ($donantes as $donante) {

        $stmtTipo->execute([
            ":tipo" => $donante["tipo"]
        ]);

        $idTipo = $stmtTipo->fetchColumn();

        if (!$idTipo) {
            throw new Exception(
                "No se encontró el tipo de sangre: " . $donante["tipo"]
            );
        }

        $stmt->execute([
            ":id_tipo" => $idTipo,
            ":dui" => $donante["dui"],
            ":nombre" => $donante["nombre"],
            ":apellido" => $donante["apellido"],
            ":contacto" => $donante["contacto"],
            ":fecha" => $donante["fecha_nacimiento"],
            ":sexo" => $donante["sexo"]
        ]);
    }

    echo "Donantes insertados correctamente.\n";
}