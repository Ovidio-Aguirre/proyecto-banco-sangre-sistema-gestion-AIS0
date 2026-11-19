<?php

function seedPacientes(mysqli $conn): void
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
                WHERE tipo = ?";

    $stmtTipo = $conn->prepare($sqlTipo);

    foreach ($pacientes as $paciente) {

        $tipo = $paciente["tipo"];
        $stmtTipo->bind_param("s", $tipo);
        $stmtTipo->execute();

        $resultado = $stmtTipo->get_result();
        $fila = $resultado->fetch_assoc();

        $idTipo = $fila["id_tipo_sangre"] ?? null;

        if (!$idTipo) {
            throw new Exception(
                "No se encontró el tipo de sangre: " . $paciente["tipo"]
            );
        }

        $sqlBuscar = "SELECT id_paciente
                      FROM pacientes
                      WHERE id_tipo_sangre = ?
                      AND nombre_paciente = ?
                      AND apellido_paciente = ?
                      AND fecha_nacimiento = ?
                      AND sexo = ?
                      LIMIT 1";

        $stmtBuscar = $conn->prepare($sqlBuscar);

        $nombre = $paciente["nombre"];
        $apellido = $paciente["apellido"];
        $fecha = $paciente["fecha_nacimiento"];
        $sexo = $paciente["sexo"];
        $stmtBuscar->bind_param("issss", $idTipo, $nombre, $apellido, $fecha, $sexo);
        $stmtBuscar->execute();

        $resultado = $stmtBuscar->get_result();
        $fila = $resultado->fetch_assoc();
        $idPaciente = $fila["id_paciente"] ?? null;

        if (!$idPaciente) {

            $sql = "INSERT INTO pacientes
                    (id_tipo_sangre, nombre_paciente,
                     apellido_paciente, fecha_nacimiento, sexo)
                    VALUES
                    (?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $nombre = $paciente["nombre"];
            $apellido = $paciente["apellido"];
            $fecha = $paciente["fecha_nacimiento"];
            $sexo = $paciente["sexo"];
            $stmt->bind_param("issss", $idTipo, $nombre, $apellido, $fecha, $sexo);
            $stmt->execute();
        }
    }

    echo "Pacientes insertados correctamente.\n";
}