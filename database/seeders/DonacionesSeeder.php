<?php

function seedDonaciones(mysqli $conn): void
{
    $donaciones = [
        ["dui" => "00000000-1", "fecha_evaluacion" => "2026-10-09", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-10-09", "fecha_caducidad" => "2026-11-20", "estado" => "DISPONIBLE"], // A+
        ["dui" => "00000000-2", "fecha_evaluacion" => "2026-10-12", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-10-12", "fecha_caducidad" => "2026-11-23", "estado" => "DISPONIBLE"], // O-
        ["dui" => "00000000-3", "fecha_evaluacion" => "2026-10-15", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-10-15", "fecha_caducidad" => "2026-11-26", "estado" => "DISPONIBLE"], // B+
        ["dui" => "00000000-4", "fecha_evaluacion" => "2026-10-19", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-10-19", "fecha_caducidad" => "2026-11-30", "estado" => "DISPONIBLE"], // AB+
        ["dui" => "00000000-5", "fecha_evaluacion" => "2026-10-22", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-10-22", "fecha_caducidad" => "2026-12-03", "estado" => "DISPONIBLE"], // A-
        ["dui" => "00000000-6", "fecha_evaluacion" => "2026-10-26", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-10-26", "fecha_caducidad" => "2026-12-07", "estado" => "DISPONIBLE"], // B-
        ["dui" => "00000000-7", "fecha_evaluacion" => "2026-10-29", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-10-29", "fecha_caducidad" => "2026-12-10", "estado" => "DISPONIBLE"], // AB-
        ["dui" => "00000000-8", "fecha_evaluacion" => "2026-11-02", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-11-02", "fecha_caducidad" => "2026-12-14", "estado" => "DISPONIBLE"], // O+
        ["dui" => "00000000-9", "fecha_evaluacion" => "2026-11-05", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-11-05", "fecha_caducidad" => "2026-12-17", "estado" => "DISPONIBLE"], // O+
        ["dui" => "00000001-0", "fecha_evaluacion" => "2026-11-09", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-11-09", "fecha_caducidad" => "2026-12-21", "estado" => "DISPONIBLE"], // A+
        ["dui" => "00000001-1", "fecha_evaluacion" => "2026-11-12", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-11-12", "fecha_caducidad" => "2026-12-24", "estado" => "DISPONIBLE"], // O-
        ["dui" => "00000001-2", "fecha_evaluacion" => "2026-11-16", "usuario" => "admin@bancosangre.test", "fecha_recoleccion" => "2026-11-16", "fecha_caducidad" => "2026-12-28", "estado" => "DISPONIBLE"], // B+
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
                id_solicitud = NULL,
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