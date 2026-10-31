<?php

function seedSolicitudes(PDO $pdo): void
{
    $solicitudes = [
        [
            "paciente" => "Pedro",
            "apellido" => "Ramírez",
            "hospital" => "Hospital Nacional Rosales",
            "fecha" => "2026-09-20",
            "unidades" => 2,
            "estado" => "PENDIENTE",
            "justificacion" => "Solicitud de unidades de sangre para paciente."
        ],
        [
            "paciente" => "Laura",
            "apellido" => "Gómez",
            "hospital" => "Hospital Nacional de Niños Benjamín Bloom",
            "fecha" => "2026-09-21",
            "unidades" => 1,
            "estado" => "APROBADA",
            "justificacion" => "Solicitud de una unidad de sangre."
        ],
        [
            "paciente" => "José",
            "apellido" => "Castro",
            "hospital" => "Hospital Nacional San Juan de Dios de Santa Ana",
            "fecha" => "2026-09-22",
            "unidades" => 3,
            "estado" => "PENDIENTE",
            "justificacion" => "Solicitud de tres unidades de sangre."
        ]
    ];

    $sqlPaciente = "SELECT id_paciente
                    FROM pacientes
                    WHERE nombre_paciente = :nombre
                    AND apellido_paciente = :apellido
                    LIMIT 1";

    $stmtPaciente = $pdo->prepare($sqlPaciente);

    $sqlHospital = "SELECT id_hospital
                    FROM hospitales
                    WHERE nombre_hospital = :nombre";

    $stmtHospital = $pdo->prepare($sqlHospital);

    foreach ($solicitudes as $solicitud) {

        $stmtPaciente->execute([
            ":nombre" => $solicitud["paciente"],
            ":apellido" => $solicitud["apellido"]
        ]);

        $idPaciente = $stmtPaciente->fetchColumn();

        if (!$idPaciente) {
            throw new Exception(
                "No se encontró el paciente: " . $solicitud["paciente"]
            );
        }

        $stmtHospital->execute([
            ":nombre" => $solicitud["hospital"]
        ]);

        $idHospital = $stmtHospital->fetchColumn();

        if (!$idHospital) {
            throw new Exception(
                "No se encontró el hospital: " . $solicitud["hospital"]
            );
        }

        $sqlBuscar = "SELECT id_solicitud
                      FROM solicitudes
                      WHERE id_paciente = :paciente
                      AND id_hospital = :hospital
                      AND fecha_solicitud = :fecha
                      LIMIT 1";

        $stmtBuscar = $pdo->prepare($sqlBuscar);

        $stmtBuscar->execute([
            ":paciente" => $idPaciente,
            ":hospital" => $idHospital,
            ":fecha" => $solicitud["fecha"]
        ]);

        $idSolicitud = $stmtBuscar->fetchColumn();

        if ($idSolicitud) {

            $sql = "UPDATE solicitudes
                    SET unidades_solicitadas = :unidades,
                        estado = :estado,
                        justificacion = :justificacion
                    WHERE id_solicitud = :id";

        } else {

            $sql = "INSERT INTO solicitudes
                    (id_paciente, id_hospital, fecha_solicitud,
                     unidades_solicitadas, estado, justificacion)
                    VALUES
                    (:paciente, :hospital, :fecha,
                     :unidades, :estado, :justificacion)";
        }

        $stmt = $pdo->prepare($sql);

        $parametros = [
            ":unidades" => $solicitud["unidades"],
            ":estado" => $solicitud["estado"],
            ":justificacion" => $solicitud["justificacion"]
        ];

        if ($idSolicitud) {
            $parametros[":id"] = $idSolicitud;
        } else {
            $parametros[":paciente"] = $idPaciente;
            $parametros[":hospital"] = $idHospital;
            $parametros[":fecha"] = $solicitud["fecha"];
        }

        $stmt->execute($parametros);
    }

    echo "Solicitudes insertadas correctamente.\n";
}