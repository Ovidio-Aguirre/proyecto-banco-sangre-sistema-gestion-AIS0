<?php

function seedHospitales(PDO $pdo): void
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
                    WHERE m.nombre_municipio = :municipio
                    AND d.nombre_distrito = :distrito";

    $stmtDistrito = $pdo->prepare($sqlDistrito);

    foreach ($hospitales as $hospital) {

        $stmtDistrito->execute([
            ":municipio" => $hospital["municipio"],
            ":distrito" => $hospital["distrito"]
        ]);

        $idDistrito = $stmtDistrito->fetchColumn();

        if (!$idDistrito) {
            throw new Exception(
                "No se encontró el distrito de: " . $hospital["nombre"]
            );
        }

        $sqlBuscar = "SELECT id_hospital
                      FROM hospitales
                      WHERE nombre_hospital = :nombre";

        $stmtBuscar = $pdo->prepare($sqlBuscar);

        $stmtBuscar->execute([
            ":nombre" => $hospital["nombre"]
        ]);

        $idHospital = $stmtBuscar->fetchColumn();

        if ($idHospital) {

            $sql = "UPDATE hospitales
                    SET id_distrito = :id_distrito,
                        direccion = :direccion,
                        activo = 1
                    WHERE id_hospital = :id_hospital";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":id_distrito" => $idDistrito,
                ":direccion" => $hospital["direccion"],
                ":id_hospital" => $idHospital
            ]);

        } else {

            $sql = "INSERT INTO hospitales
                    (id_distrito, nombre_hospital, direccion, activo)
                    VALUES (:id_distrito, :nombre, :direccion, 1)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":id_distrito" => $idDistrito,
                ":nombre" => $hospital["nombre"],
                ":direccion" => $hospital["direccion"]
            ]);
        }
    }

    echo "Hospitales insertados correctamente.\n";
}