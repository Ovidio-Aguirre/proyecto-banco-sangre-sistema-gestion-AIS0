<?php

function seedEvaluacionesDonante(mysqli $conn): void
{
    $evaluaciones = [
        [
            "dui" => "00000000-1",
            "fecha" => "2026-09-15",
            "peso" => 75.50,
            "altura" => 1.75,
            "resultado" => "APTO",
            "motivo" => null
        ],
        [
            "dui" => "00000000-2",
            "fecha" => "2026-09-16",
            "peso" => 48.00,
            "altura" => 1.60,
            "resultado" => "NO_APTO",
            "motivo" => "No cumple con el peso mínimo establecido"
        ],
        [
            "dui" => "00000000-3",
            "fecha" => "2026-09-17",
            "peso" => 80.00,
            "altura" => 1.80,
            "resultado" => "APTO",
            "motivo" => null
        ],
        [
            "dui" => "00000000-4",
            "fecha" => "2026-09-18",
            "peso" => 60.00,
            "altura" => 1.65,
            "resultado" => "PENDIENTE",
            "motivo" => null
        ]
    ];

    $sqlDonante = "SELECT id_donante
                   FROM donantes
                   WHERE dui = ?";

    $stmtDonante = $conn->prepare($sqlDonante);

    foreach ($evaluaciones as $evaluacion) {

        $dui = $evaluacion["dui"];
        $stmtDonante->bind_param("s", $dui);
        $stmtDonante->execute();

        $resultado = $stmtDonante->get_result();
        $fila = $resultado->fetch_assoc();

        $idDonante = $fila["id_donante"] ?? null;

        if (!$idDonante) {
            throw new Exception(
                "No se encontró el donante: " . $evaluacion["dui"]
            );
        }

        $sqlBuscar = "SELECT id_evaluacion
                      FROM evaluaciones_donante
                      WHERE id_donante = ?
                      AND fecha_evaluacion = ?
                      LIMIT 1";

        $stmtBuscar = $conn->prepare($sqlBuscar);

        $fecha = $evaluacion["fecha"];
        $stmtBuscar->bind_param("is", $idDonante, $fecha);
        $stmtBuscar->execute();

        $resultado = $stmtBuscar->get_result();
        $fila = $resultado->fetch_assoc();

        $idEvaluacion = $fila["id_evaluacion"] ?? null;

        if ($idEvaluacion) {

            $sql = "UPDATE evaluaciones_donante
                    SET peso = ?,
                        altura = ?,
                        resultado = ?,
                        motivo = ?
                    WHERE id_evaluacion = ?";

        } else {

            $sql = "INSERT INTO evaluaciones_donante
                    (id_donante, fecha_evaluacion, peso, altura, resultado, motivo)
                    VALUES
                    (?, ?, ?, ?, ?, ?)";
        }

        $stmt = $conn->prepare($sql);

        $peso = $evaluacion["peso"];
        $altura = $evaluacion["altura"];
        $resultadoEvaluacion = $evaluacion["resultado"];
        $motivo = $evaluacion["motivo"];

        if ($idEvaluacion) {

            $stmt->bind_param(
                "ddsii",
                $peso,
                $altura,
                $resultadoEvaluacion,
                $motivo,
                $idEvaluacion
            );

        } else {

            $idDonante = (int) $idDonante;
            $fecha = $evaluacion["fecha"];

            $stmt->bind_param(
                "isddss",
                $idDonante,
                $fecha,
                $peso,
                $altura,
                $resultadoEvaluacion,
                $motivo
            );
        }

        $stmt->execute();
    }

    echo "Evaluaciones insertadas correctamente.\n";
}