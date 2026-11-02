<?php

function seedHospitales(mysqli $conn): void
{
    $hospitales = [
        [
            "municipio" => "San Salvador Centro",
            "distrito" => "San Salvador",
            "nombre" => "Hospital Nacional Rosales",
            "direccion" => "Dirección de prueba, San Salvador"
        ],
        [
            "municipio" => "San Salvador Centro",
            "distrito" => "San Salvador",
            "nombre" => "Hospital Nacional de Niños Benjamín Bloom",
            "direccion" => "Dirección de prueba, San Salvador"
        ],
        [
            "municipio" => "Santa Ana Centro",
            "distrito" => "Santa Ana",
            "nombre" => "Hospital Nacional San Juan de Dios de Santa Ana",
            "direccion" => "Dirección de prueba, Santa Ana"
        ],
        [
            "municipio" => "San Miguel Centro",
            "distrito" => "San Miguel",
            "nombre" => "Hospital Nacional San Juan de Dios de San Miguel",
            "direccion" => "Dirección de prueba, San Miguel"
        ]
    ];

    $sqlDistrito = "SELECT d.id_distrito
                    FROM distritos d
                    INNER JOIN municipios m
                    ON d.id_municipio = m.id_municipio
                    WHERE m.nombre_municipio = ?
                    AND d.nombre_distrito = ?";

    $stmtDistrito = $conn->prepare($sqlDistrito);

    foreach ($hospitales as $hospital) {

        $municipio = $hospital["municipio"];
        $distrito = $hospital["distrito"];
        $stmtDistrito->bind_param("ss", $municipio, $distrito);
        $stmtDistrito->execute();

        $resultado = $stmtDistrito->get_result();
        $fila = $resultado->fetch_assoc();

        $idDistrito = $fila["id_distrito"] ?? null;

        if (!$idDistrito) {
            throw new Exception(
                "No se encontró el distrito de: " . $hospital["nombre"]
            );
        }

        $sqlBuscar = "SELECT id_hospital
                      FROM hospitales
                      WHERE nombre_hospital = ?";

        $stmtBuscar = $conn->prepare($sqlBuscar);

        $nombre = $hospital["nombre"];
        $stmtBuscar->bind_param("s", $nombre);
        $stmtBuscar->execute();

        $resultado = $stmtBuscar->get_result();
        $fila = $resultado->fetch_assoc();

        $idHospital = $fila["id_hospital"] ?? null;
  

        if ($idHospital) {

            $sql = "UPDATE hospitales
                    SET id_distrito = ?,
                        direccion = ?,
                        activo = 1
                    WHERE id_hospital = ?";

            $stmt = $conn->prepare($sql);

            $direccion = $hospital["direccion"];
            $stmt->bind_param("isi", $idDistrito, $direccion, $idHospital);
            $stmt->execute();

        } else {

            $sql = "INSERT INTO hospitales
                    (id_distrito, nombre_hospital, direccion, activo)
                    VALUES (?, ?, ?, 1)";

            $stmt = $conn->prepare($sql);

            $nombre = $hospital["nombre"];
            $direccion = $hospital["direccion"];
            $stmt->bind_param("iss", $idDistrito, $nombre, $direccion);
            $stmt->execute();
        }
    }

    echo "Hospitales insertados correctamente.\n";
}