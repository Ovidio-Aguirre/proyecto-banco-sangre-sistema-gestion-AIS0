
<?php

function seedSolicitudes(mysqli $conn): void
{
    $solicitudes = [
        // PENDIENTES: para probar aprobar y rechazar
        ["paciente_nombre" => "José",  "paciente_apellido" => "Castro",  "paciente_nacimiento" => "1975-12-10",
         "hospital" => "Hospital Nacional Rosales",
         "fecha_solicitud" => "2026-11-17", "unidades" => 1, "estado" => "PENDIENTE",
         "justificacion" => "Anemia severa, requiere transfusión", "motivo_rechazo" => null, "fecha_entrega" => null],

        ["paciente_nombre" => "Laura", "paciente_apellido" => "Gómez",   "paciente_nacimiento" => "1998-08-21",
         "hospital" => "Hospital Nacional de Niños Benjamín Bloom",
         "fecha_solicitud" => "2026-11-18", "unidades" => 2, "estado" => "PENDIENTE",
         "justificacion" => "Cirugía programada con riesgo de sangrado", "motivo_rechazo" => null, "fecha_entrega" => null],

        // APROBADAS: para probar asignar unidades y entregar
        ["paciente_nombre" => "Pedro", "paciente_apellido" => "Ramírez", "paciente_nacimiento" => "1980-04-15",
         "hospital" => "Hospital Nacional San Juan de Dios de Santa Ana",
         "fecha_solicitud" => "2026-11-16", "unidades" => 2, "estado" => "APROBADA",
         "justificacion" => "Hemorragia posoperatoria", "motivo_rechazo" => null, "fecha_entrega" => null],

        ["paciente_nombre" => "Laura", "paciente_apellido" => "Gómez",   "paciente_nacimiento" => "1998-08-21",
         "hospital" => "Hospital Nacional San Juan de Dios de San Miguel",
         "fecha_solicitud" => "2026-11-15", "unidades" => 3, "estado" => "APROBADA",
         "justificacion" => "Trauma por accidente vehicular", "motivo_rechazo" => null, "fecha_entrega" => null],

        // RECHAZADA: con motivo
        ["paciente_nombre" => "José",  "paciente_apellido" => "Castro",  "paciente_nacimiento" => "1975-12-10",
         "hospital" => "Hospital Nacional Rosales",
         "fecha_solicitud" => "2026-11-10", "unidades" => 4, "estado" => "RECHAZADA",
         "justificacion" => "Reserva preventiva de unidades", "motivo_rechazo" => "No se justifica la cantidad solicitada", "fecha_entrega" => null],

        // ENTREGADA: con fecha de entrega
        ["paciente_nombre" => "Pedro", "paciente_apellido" => "Ramírez", "paciente_nacimiento" => "1980-04-15",
         "hospital" => "Hospital Nacional Rosales",
         "fecha_solicitud" => "2026-11-05", "unidades" => 1, "estado" => "ENTREGADA",
         "justificacion" => "Anemia crónica", "motivo_rechazo" => null, "fecha_entrega" => "2026-11-08"],
    ];

    $stmtPaciente = $conn->prepare(
        "SELECT id_paciente FROM pacientes
         WHERE nombre_paciente = ? AND apellido_paciente = ? AND fecha_nacimiento = ?
         LIMIT 1"
    );

    $stmtHospital = $conn->prepare(
        "SELECT id_hospital FROM hospitales WHERE nombre_hospital = ?"
    );

    $stmtBuscar = $conn->prepare(
        "SELECT id_solicitud FROM solicitudes
         WHERE id_paciente = ? AND id_hospital = ?
         AND fecha_solicitud = ? AND justificacion = ?
         LIMIT 1"
    );

    $stmtUpdate = $conn->prepare(
        "UPDATE solicitudes
         SET unidades_solicitadas = ?, estado = ?, fecha_entrega = ?, motivo_rechazo = ?
         WHERE id_solicitud = ?"
    );

    $stmtInsert = $conn->prepare(
        "INSERT INTO solicitudes
         (id_paciente, id_hospital, fecha_solicitud, fecha_entrega,
          unidades_solicitadas, estado, justificacion, motivo_rechazo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($solicitudes as $solicitud) {

        $nombre = $solicitud["paciente_nombre"];
        $apellido = $solicitud["paciente_apellido"];
        $nacimiento = $solicitud["paciente_nacimiento"];
        $stmtPaciente->bind_param("sss", $nombre, $apellido, $nacimiento);
        $stmtPaciente->execute();
        $fila = $stmtPaciente->get_result()->fetch_assoc();
        $idPaciente = $fila["id_paciente"] ?? null;

        if (!$idPaciente) {
            throw new Exception(
                "No se encontró el paciente: " . $nombre . " " . $apellido
            );
        }

        $hospital = $solicitud["hospital"];
        $stmtHospital->bind_param("s", $hospital);
        $stmtHospital->execute();
        $fila = $stmtHospital->get_result()->fetch_assoc();
        $idHospital = $fila["id_hospital"] ?? null;

        if (!$idHospital) {
            throw new Exception(
                "No se encontró el hospital: " . $hospital
            );
        }

        $fechaSolicitud = $solicitud["fecha_solicitud"];
        $justificacion = $solicitud["justificacion"];
        $stmtBuscar->bind_param("iiss", $idPaciente, $idHospital, $fechaSolicitud, $justificacion);
        $stmtBuscar->execute();
        $fila = $stmtBuscar->get_result()->fetch_assoc();
        $idSolicitud = $fila["id_solicitud"] ?? null;

        $unidades = $solicitud["unidades"];
        $estado = $solicitud["estado"];
        $fechaEntrega = $solicitud["fecha_entrega"];
        $motivo = $solicitud["motivo_rechazo"];

        if ($idSolicitud) {
            $stmtUpdate->bind_param("isssi", $unidades, $estado, $fechaEntrega, $motivo, $idSolicitud);
            $stmtUpdate->execute();
        } else {
            $stmtInsert->bind_param(
                "iississs",
                $idPaciente,
                $idHospital,
                $fechaSolicitud,
                $fechaEntrega,
                $unidades,
                $estado,
                $justificacion,
                $motivo
            );
            $stmtInsert->execute();
        }
    }

    $stmtPaciente->close();
    $stmtHospital->close();
    $stmtBuscar->close();
    $stmtUpdate->close();
    $stmtInsert->close();

    echo "Solicitudes insertadas correctamente.\n";
}
