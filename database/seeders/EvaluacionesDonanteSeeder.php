<?php

function seedEvaluacionesDonante(PDO $pdo): void
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
                   WHERE dui = :dui";

    $stmtDonante = $pdo->prepare($sqlDonante);

    foreach ($evaluaciones as $evaluacion) {

        $stmtDonante->execute([
            ":dui" => $evaluacion["dui"]
        ]);

        $idDonante = $stmtDonante->fetchColumn();

        if (!$idDonante) {
            throw new Exception(
                "No se encontró el donante: " . $evaluacion["dui"]
            );
        }

        $sqlBuscar = "SELECT id_evaluacion
                      FROM evaluaciones_donante
                      WHERE id_donante = :id_donante
                      AND fecha_evaluacion = :fecha
                      LIMIT 1";

        $stmtBuscar = $pdo->prepare($sqlBuscar);

        $stmtBuscar->execute([
            ":id_donante" => $idDonante,
            ":fecha" => $evaluacion["fecha"]
        ]);

        $idEvaluacion = $stmtBuscar->fetchColumn();

        if ($idEvaluacion) {

            $sql = "UPDATE evaluaciones_donante
                    SET peso = :peso,
                        altura = :altura,
                        resultado = :resultado,
                        motivo = :motivo
                    WHERE id_evaluacion = :id";

        } else {

            $sql = "INSERT INTO evaluaciones_donante
                    (id_donante, fecha_evaluacion, peso,
                     altura, resultado, motivo)
                    VALUES
                    (:id_donante, :fecha, :peso,
                     :altura, :resultado, :motivo)";
        }

        $stmt = $pdo->prepare($sql);

        $parametros = [
            ":peso" => $evaluacion["peso"],
            ":altura" => $evaluacion["altura"],
            ":resultado" => $evaluacion["resultado"],
            ":motivo" => $evaluacion["motivo"]
        ];

        if ($idEvaluacion) {
            $parametros[":id"] = $idEvaluacion;
        } else {
            $parametros[":id_donante"] = $idDonante;
            $parametros[":fecha"] = $evaluacion["fecha"];
        }

        $stmt->execute($parametros);
    }

    echo "Evaluaciones insertadas correctamente.\n";
}