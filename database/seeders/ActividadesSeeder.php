<?php

function seedActividades(mysqli $conn): void
{
    // rol: Administrador | Médico
    $actividades = [
        // Administrador: usuarios y hospitales
        ["rol" => "Administrador", "accion" => "Registrar hospital",     "descripcion" => "Hospital Nacional Rosales",                         "fecha_hora" => "2026-11-04 08:15:00"],
        ["rol" => "Administrador", "accion" => "Crear usuario",          "descripcion" => "Alta de usuario con rol Médico",                    "fecha_hora" => "2026-11-04 08:40:00"],
        ["rol" => "Administrador", "accion" => "Editar hospital",        "descripcion" => "Hospital Nacional de Niños Benjamín Bloom",         "fecha_hora" => "2026-11-06 10:05:00"],
        ["rol" => "Administrador", "accion" => "Actualizar usuario",     "descripcion" => "Usuario #2 (correo)",                               "fecha_hora" => "2026-11-09 09:30:00"],
        ["rol" => "Administrador", "accion" => "Aprobar solicitud",      "descripcion" => "SOL-003 aprobada",                                  "fecha_hora" => "2026-11-16 11:20:00"],
        ["rol" => "Administrador", "accion" => "Aprobar solicitud",      "descripcion" => "SOL-004 aprobada",                                  "fecha_hora" => "2026-11-15 15:45:00"],
        ["rol" => "Administrador", "accion" => "Rechazar solicitud",     "descripcion" => "SOL-005 rechazada",                                 "fecha_hora" => "2026-11-10 14:10:00"],

        // Médico: donantes, evaluaciones, donaciones y solicitudes
        ["rol" => "Médico", "accion" => "Registrar donante",             "descripcion" => "Juan Pérez (00000000-1)",                           "fecha_hora" => "2026-10-09 08:50:00"],
        ["rol" => "Médico", "accion" => "Evaluar donante",               "descripcion" => "Juan Pérez evaluado como apto",                     "fecha_hora" => "2026-10-09 09:10:00"],
        ["rol" => "Médico", "accion" => "Registrar donación",            "descripcion" => "DON-001 registrada (donante #1)",                   "fecha_hora" => "2026-10-09 09:35:00"],
        ["rol" => "Médico", "accion" => "Cambiar estado de donación",    "descripcion" => "DON-001 pasó a DISPONIBLE",                         "fecha_hora" => "2026-10-09 11:00:00"],
        ["rol" => "Médico", "accion" => "Evaluar donante",               "descripcion" => "María López evaluada como no apta",                 "fecha_hora" => "2026-10-12 08:30:00"],
        ["rol" => "Médico", "accion" => "Registrar donante",             "descripcion" => "Gabriela Flores (00000000-8)",                      "fecha_hora" => "2026-11-02 09:00:00"],
        ["rol" => "Médico", "accion" => "Registrar donación",            "descripcion" => "DON-008 registrada (donante #8)",                   "fecha_hora" => "2026-11-02 10:15:00"],
        ["rol" => "Médico", "accion" => "Cambiar estado de donación",    "descripcion" => "DON-008 pasó a DISPONIBLE",                         "fecha_hora" => "2026-11-02 12:00:00"],
        ["rol" => "Médico", "accion" => "Asignar unidades",              "descripcion" => "2 unidad(es) asignadas a SOL-003",                  "fecha_hora" => "2026-11-17 09:25:00"],
        ["rol" => "Médico", "accion" => "Entregar solicitud",            "descripcion" => "SOL-006 entregada",                                 "fecha_hora" => "2026-11-08 16:40:00"],
        ["rol" => "Médico", "accion" => "Desvincular unidad",            "descripcion" => "DON-002 desvinculada de su solicitud",              "fecha_hora" => "2026-11-18 10:50:00"],
        ["rol" => "Médico", "accion" => "Reasignar donación",            "descripcion" => "DON-012 reasignada a la evaluación #16",            "fecha_hora" => "2026-11-18 13:05:00"],
        ["rol" => "Médico", "accion" => "Evaluar donante",               "descripcion" => "Daniela Cruz evaluada como apta",                   "fecha_hora" => "2026-11-16 08:45:00"],
    ];

    // Primer usuario activo de cada rol
    $stmtUsuario = $conn->prepare(
        "SELECT u.id_usuario
         FROM usuarios u
         INNER JOIN roles r ON r.id_rol = u.id_rol
         WHERE r.rol = ? AND u.activo = 1
         ORDER BY u.id_usuario
         LIMIT 1"
    );

    $stmtBuscar = $conn->prepare(
        "SELECT id_actividad FROM registro_actividad
         WHERE id_usuario = ? AND accion = ? AND descripcion = ? AND fecha_hora = ?
         LIMIT 1"
    );

    $stmtInsert = $conn->prepare(
        "INSERT INTO registro_actividad (id_usuario, accion, descripcion, fecha_hora)
         VALUES (?, ?, ?, ?)"
    );

    $usuarios = []; // cache: rol => id_usuario

    foreach ($actividades as $actividad) {

        $rol = $actividad["rol"];

        if (!isset($usuarios[$rol])) {
            $stmtUsuario->bind_param("s", $rol);
            $stmtUsuario->execute();
            $fila = $stmtUsuario->get_result()->fetch_assoc();

            if (!$fila) {
                throw new Exception("No hay un usuario activo con el rol: " . $rol);
            }
            $usuarios[$rol] = (int) $fila["id_usuario"];
        }

        $idUsuario = $usuarios[$rol];
        $accion = $actividad["accion"];
        $descripcion = $actividad["descripcion"];
        $fechaHora = $actividad["fecha_hora"];

        $stmtBuscar->bind_param("isss", $idUsuario, $accion, $descripcion, $fechaHora);
        $stmtBuscar->execute();
        $existe = $stmtBuscar->get_result()->fetch_assoc();

        if (!$existe) {
            $stmtInsert->bind_param("isss", $idUsuario, $accion, $descripcion, $fechaHora);
            $stmtInsert->execute();
        }
    }

    $stmtUsuario->close();
    $stmtBuscar->close();
    $stmtInsert->close();

    echo "Actividades insertadas correctamente.\n";
}