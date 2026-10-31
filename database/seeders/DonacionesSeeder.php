<?php

function seedDonaciones(PDO $pdo): void
{
    $donaciones = [
        [
            "dui" => "00000000-1",
            "fecha_evaluacion" => "2026-09-15",
            "usuario" => "admin@bancosangre.test",
            "fecha_recoleccion" => "2026-09-20",
            "fecha_caducidad" => "2026-10-25",
            "estado" => "DISPONIBLE"
        ],
        [
            "dui" => "00000000-3",
            "fecha_evaluacion" => "2026-09-17",
            "usuario" => "admin@bancosangre.test",
            "fecha_recoleccion" => "2026-09-22",
            "fecha_caducidad" => "2026-10-27",
            "estado" => "DISPONIBLE"
        ]
    ];

    $sqlEvaluacion = "SELECT e.id_evaluacion
                      FROM evaluaciones_donante e
                      INNER JOIN donantes d
                      ON e.id_donante = d.id_donante
                      WHERE d.dui = :dui
                      AND e.fecha_evaluacion = :fecha
                      AND e.resultado = 'APTO'";

    $stmtEvaluacion = $pdo->prepare($sqlEvaluacion);

    $sqlUsuario = "SELECT id_usuario
                   FROM usuarios
                   WHERE correo = :correo";

    $stmtUsuario = $pdo->prepare($sqlUsuario);

    foreach ($donaciones as $donacion) {

        $stmtEvaluacion->execute([
            ":dui" => $donacion["dui"],
            ":fecha" => $donacion["fecha_evaluacion"]
        ]);

        $idEvaluacion = $stmtEvaluacion->fetchColumn();

        if (!$idEvaluacion) {
            throw new Exception(
                "No se encontró una evaluación APTO para el DUI: "
                . $donacion["dui"]
            );
        }

        $stmtUsuario->execute([
            ":correo" => $donacion["usuario"]
        ]);

        $idUsuario = $stmtUsuario->fetchColumn();

        if (!$idUsuario) {
            throw new Exception(
                "No se encontró el usuario: " . $donacion["usuario"]
            );
        }

        $sql = "INSERT INTO donaciones
                (id_evaluacion, id_usuario, id_solicitud,
                 fecha_recoleccion, fecha_caducidad, estado)
                VALUES
                (:evaluacion, :usuario, NULL,
                 :recoleccion, :caducidad, :estado)
                ON DUPLICATE KEY UPDATE
                id_usuario = VALUES(id_usuario),
                fecha_recoleccion = VALUES(fecha_recoleccion),
                fecha_caducidad = VALUES(fecha_caducidad),
                estado = VALUES(estado)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":evaluacion" => $idEvaluacion,
            ":usuario" => $idUsuario,
            ":recoleccion" => $donacion["fecha_recoleccion"],
            ":caducidad" => $donacion["fecha_caducidad"],
            ":estado" => $donacion["estado"]
        ]);
    }

    echo "Donaciones insertadas correctamente.\n";
}