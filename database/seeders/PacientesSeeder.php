<?php

function seedPacientes(PDO $pdo): void
{
    $pacientes = [
        [
            "tipo" => "O+",
            "nombre" => "Pedro",
            "apellido" => "Ramírez",
            "fecha_nacimiento" => "1980-04-15",
            "sexo" => "M"
        ],
        [
            "tipo" => "A+",
            "nombre" => "Laura",
            "apellido" => "Gómez",
            "fecha_nacimiento" => "1998-08-21",
            "sexo" => "F"
        ],
        [
            "tipo" => "B-",
            "nombre" => "José",
            "apellido" => "Castro",
            "fecha_nacimiento" => "1975-12-10",
            "sexo" => "M"
        ]
    ];

    $sqlTipo = "SELECT id_tipo_sangre
                FROM tipo_sangre
                WHERE tipo = :tipo";

    $stmtTipo = $pdo->prepare($sqlTipo);

    foreach ($pacientes as $paciente) {

        $stmtTipo->execute([
            ":tipo" => $paciente["tipo"]
        ]);

        $idTipo = $stmtTipo->fetchColumn();

        if (!$idTipo) {
            throw new Exception(
                "No se encontró el tipo de sangre: " . $paciente["tipo"]
            );
        }

        $sqlBuscar = "SELECT id_paciente
                      FROM pacientes
                      WHERE id_tipo_sangre = :id_tipo
                      AND nombre_paciente = :nombre
                      AND apellido_paciente = :apellido
                      AND fecha_nacimiento = :fecha
                      AND sexo = :sexo
                      LIMIT 1";

        $stmtBuscar = $pdo->prepare($sqlBuscar);

        $stmtBuscar->execute([
            ":id_tipo" => $idTipo,
            ":nombre" => $paciente["nombre"],
            ":apellido" => $paciente["apellido"],
            ":fecha" => $paciente["fecha_nacimiento"],
            ":sexo" => $paciente["sexo"]
        ]);

        $idPaciente = $stmtBuscar->fetchColumn();

        if (!$idPaciente) {

            $sql = "INSERT INTO pacientes
                    (id_tipo_sangre, nombre_paciente,
                     apellido_paciente, fecha_nacimiento, sexo)
                    VALUES
                    (:id_tipo, :nombre, :apellido, :fecha, :sexo)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":id_tipo" => $idTipo,
                ":nombre" => $paciente["nombre"],
                ":apellido" => $paciente["apellido"],
                ":fecha" => $paciente["fecha_nacimiento"],
                ":sexo" => $paciente["sexo"]
            ]);
        }
    }

    echo "Pacientes insertados correctamente.\n";
}