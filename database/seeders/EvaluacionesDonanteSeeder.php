<?php

function seedEvaluacionesDonante(mysqli $conn): void
{
    $evaluaciones = [
        // Juan (A+)
        [
            "dui" => "00000000-1", "fecha" => "2026-10-09", 
            "peso" => 75.50, "altura" => 1.75, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // María (O-): NO_APTO, luego APTO
        [
            "dui" => "00000000-2", "fecha" => "2026-09-16", 
            "peso" => 48.00, "altura" => 1.60, 
            "resultado" => "NO_APTO", "motivo" => "No cumple con el peso mínimo establecido"
        ],
        [
            "dui" => "00000000-2", "fecha" => "2026-10-12", 
            "peso" => 53.00, "altura" => 1.60, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Carlos (B+)
        [
            "dui" => "00000000-3", "fecha" => "2026-10-15", 
            "peso" => 80.00, "altura" => 1.80, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Ana (AB+): NO_APTO, luego APTO
        [
            "dui" => "00000000-4", "fecha" => "2026-09-18", 
            "peso" => 60.00, "altura" => 1.65, 
            "resultado" => "NO_APTO", "motivo" => "Hemoglobina por debajo del mínimo"
        ],
        [
            "dui" => "00000000-4", "fecha" => "2026-10-19", 
            "peso" => 60.00, "altura" => 1.65, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Luis (A-)
        [
            "dui" => "00000000-5", "fecha" => "2026-10-22", 
            "peso" => 82.00, "altura" => 1.78, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Karla (B-): NO_APTO, luego APTO
        [
            "dui" => "00000000-6", "fecha" => "2026-10-05", 
            "peso" => 62.00, "altura" => 1.62, 
            "resultado" => "NO_APTO", "motivo" => "Presión arterial elevada"
        ],
        [
            "dui" => "00000000-6", "fecha" => "2026-10-26", 
            "peso" => 63.50, "altura" => 1.62, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Roberto (AB-)
        [
            "dui" => "00000000-7", "fecha" => "2026-10-29", 
            "peso" => 90.00, "altura" => 1.82, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Gabriela (O+): NO_APTO, luego APTO
        [
            "dui" => "00000000-8", "fecha" => "2026-09-28", 
            "peso" => 58.00, "altura" => 1.58, 
            "resultado" => "NO_APTO", "motivo" => "Cuadro gripal reciente"
        ],
        [
            "dui" => "00000000-8", "fecha" => "2026-11-02", 
            "peso" => 58.00, "altura" => 1.58, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Miguel (O+)
        [
            "dui" => "00000000-9", "fecha" => "2026-11-05", 
            "peso" => 78.00, "altura" => 1.72, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Sofía (A+): NO_APTO, luego APTO
        [
            "dui" => "00000001-0", "fecha" => "2026-10-10", 
            "peso" => 55.00, "altura" => 1.60, 
            "resultado" => "NO_APTO", "motivo" => "Anemia detectada (hemoglobina baja)"
        ],
        [
            "dui" => "00000001-0", "fecha" => "2026-11-09", 
            "peso" => 57.00, "altura" => 1.61, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // José (O-)
        [
            "dui" => "00000001-1", "fecha" => "2026-11-12", 
            "peso" => 88.00, "altura" => 1.77, 
            "resultado" => "APTO",    "motivo" => null
        ],

        // Daniela (B+)
        [
            "dui" => "00000001-2", "fecha" => "2026-11-16", 
            "peso" => 64.00, "altura" => 1.66, 
            "resultado" => "APTO",    "motivo" => null
        ],
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
                "ddssi",
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