<?php

function seedDonaciones(mysqli $conn): void
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
                      WHERE d.dui = ?
                      AND e.fecha_evaluacion = ?
                      AND e.resultado = 'APTO'";

    $stmtEvaluacion = $conn->prepare($sqlEvaluacion);

    $sqlUsuario = "SELECT id_usuario
                   FROM usuarios
                   WHERE correo = ?";

    $stmtUsuario = $conn->prepare($sqlUsuario);

    foreach ($donaciones as $donacion) {

        $dui = $donacion["dui"];
        $fecha = $donacion["fecha_evaluacion"];
        $stmtEvaluacion->bind_param("ss", $dui, $fecha);
        $stmtEvaluacion->execute();

        $resultado = $stmtEvaluacion->get_result();
        $fila = $resultado->fetch_assoc();

        $idEvaluacion = $fila["id_evaluacion"] ?? null;

        if (!$idEvaluacion) {
            throw new Exception(
                "No se encontró una evaluación APTO para el DUI: "
                . $donacion["dui"]
            );
        }

        $correo = $donacion["usuario"];
        $stmtUsuario->bind_param("s", $correo);
        $stmtUsuario->execute();

        $resultado = $stmtUsuario->get_result();
        $fila = $resultado->fetch_assoc();

        $idUsuario = $fila["id_usuario"] ?? null;

        if (!$idUsuario) {
            throw new Exception(
                "No se encontró el usuario: " . $donacion["usuario"]
            );
        }

        $sql = "INSERT INTO donaciones 
                (id_evaluacion, 
                id_usuario, 
                id_solicitud, 
                fecha_recoleccion, 
                fecha_caducidad, 
                estado)
                VALUES
                (?, ?, NULL, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                id_usuario = VALUES(id_usuario), 
                fecha_recoleccion = VALUES(fecha_recoleccion), 
                fecha_caducidad = VALUES(fecha_caducidad),
                estado = VALUES(estado)";

        $stmt = $conn->prepare($sql);

        $fechaRecoleccion = $donacion["fecha_recoleccion"];
        $fechaCaducidad = $donacion["fecha_caducidad"];
        $estado = $donacion["estado"];

        $stmt->bind_param(
            "iisss",
            $idEvaluacion,
            $idUsuario,
            $fechaRecoleccion,
            $fechaCaducidad,
            $estado
        );

        $stmt->execute();
    }

    echo "Donaciones insertadas correctamente.\n";
}