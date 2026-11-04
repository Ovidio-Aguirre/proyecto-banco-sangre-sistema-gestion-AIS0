
<?php

function seedSolicitudes(mysqli $conn): void
{
    $solicitudes = [
        [
            "paciente" => "Pedro",
            "apellido" => "Ramírez",
            "hospital" => "Hospital Nacional Rosales",
            "fecha" => "2026-09-20",
            "fecha_entrega" => "2026-09-22",
            "unidades" => 2,
            "estado" => "PENDIENTE",
            "justificacion" => "Solicitud de unidades de sangre para paciente.",
            "motivo_rechazo" => null
        ],
        [
            "paciente" => "Laura",
            "apellido" => "Gómez",
            "hospital" => "Hospital Nacional de Niños Benjamín Bloom",
            "fecha" => "2026-09-21",
            "fecha_entrega" => "2026-09-23",
            "unidades" => 1,
            "estado" => "APROBADA",
            "justificacion" => "Solicitud de una unidad de sangre.",
            "motivo_rechazo" => null
        ],
        [
            "paciente" => "José",
            "apellido" => "Castro",
            "hospital" => "Hospital Nacional San Juan de Dios de Santa Ana",
            "fecha" => "2026-09-22",
            "fecha_entrega" => null,
            "unidades" => 3,
            "estado" => "RECHAZADA",
            "justificacion" => null,
            "motivo_rechazo" => "no se presentó la justificación médica que respalde la cantidad de unidades de sangre solicitadas."
        ]
    ];

    $sqlPaciente = "SELECT id_paciente
                    FROM pacientes
                    WHERE nombre_paciente = ?
                    AND apellido_paciente = ?
                    LIMIT 1";

    $stmtPaciente = $conn->prepare($sqlPaciente);

    $sqlHospital = "SELECT id_hospital
                    FROM hospitales
                    WHERE nombre_hospital = ?";

    $stmtHospital = $conn->prepare($sqlHospital);

    foreach ($solicitudes as $solicitud) {

        $nombre = $solicitud["paciente"];
        $apellido = $solicitud["apellido"];

        $stmtPaciente->bind_param("ss", $nombre, $apellido);
        $stmtPaciente->execute();

        $resultado = $stmtPaciente->get_result();
        $fila = $resultado->fetch_assoc();

        $idPaciente = $fila["id_paciente"] ?? null;

        if (!$idPaciente) {
            throw new Exception(
                "No se encontró el paciente: " . $solicitud["paciente"]
            );
        }

        $nombre = $solicitud["hospital"];

        $stmtHospital->bind_param("s", $nombre);
        $stmtHospital->execute();

        $resultado = $stmtHospital->get_result();
        $fila = $resultado->fetch_assoc();

        $idHospital = $fila["id_hospital"] ?? null;

        if (!$idHospital) {
            throw new Exception(
                "No se encontró el hospital: " . $solicitud["hospital"]
            );
        }

        $sqlBuscar = "SELECT id_solicitud
                      FROM solicitudes
                      WHERE id_paciente = ?
                      AND id_hospital = ?
                      AND fecha_solicitud = ?
                      LIMIT 1";

        $stmtBuscar = $conn->prepare($sqlBuscar);

        $fecha = $solicitud["fecha"];

        $stmtBuscar->bind_param(
            "iis",
            $idPaciente,
            $idHospital,
            $fecha
        );

        $stmtBuscar->execute();

        $resultado = $stmtBuscar->get_result();
        $fila = $resultado->fetch_assoc();

        $idSolicitud = $fila["id_solicitud"] ?? null;

        if ($idSolicitud) {

            $sql = "UPDATE solicitudes
                    SET unidades_solicitadas = ?,
                        estado = ?,
                        justificacion = ?,
                        fecha_entrega = ?,
                        motivo_rechazo = ?
                    WHERE id_solicitud = ?";

        } else {

            $sql = "INSERT INTO solicitudes
                    (id_paciente, id_hospital, fecha_solicitud,
                     unidades_solicitadas, estado, justificacion,
                     fecha_entrega, motivo_rechazo)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)";
        }

        $stmt = $conn->prepare($sql);

        $unidades = $solicitud["unidades"];
        $estado = $solicitud["estado"];
        $justificacion = $solicitud["justificacion"];
        $fechaEntrega = $solicitud["fecha_entrega"];
        $motivoRechazo = $solicitud["motivo_rechazo"];

        if ($idSolicitud) {

            $stmt->bind_param(
                "issssi",
                $unidades,
                $estado,
                $justificacion,
                $fechaEntrega,
                $motivoRechazo,
                $idSolicitud
            );

        } else {

            $stmt->bind_param(
                "iisissss",
                $idPaciente,
                $idHospital,
                $fecha,
                $unidades,
                $estado,
                $justificacion,
                $fechaEntrega,
                $motivoRechazo
            );
        }

        $stmt->execute();

        $stmtBuscar->close();
        $stmt->close();
    }

    $stmtPaciente->close();
    $stmtHospital->close();

    echo "Solicitudes insertadas correctamente.\n";
}
