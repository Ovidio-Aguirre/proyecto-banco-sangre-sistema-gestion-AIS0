<?php

function seedMunicipios(mysqli $conn): void
{
    $municipios = [
        "Ahuachapán" => [
            "Ahuachapán Norte",
            "Ahuachapán Centro",
            "Ahuachapán Sur"
        ],

        "Santa Ana" => [
            "Santa Ana Norte",
            "Santa Ana Centro",
            "Santa Ana Este",
            "Santa Ana Oeste"
        ],

        "Sonsonate" => [
            "Sonsonate Norte",
            "Sonsonate Centro",
            "Sonsonate Este",
            "Sonsonate Oeste"
        ],

        "Chalatenango" => [
            "Chalatenango Norte",
            "Chalatenango Centro",
            "Chalatenango Sur"
        ],

        "La Libertad" => [
            "La Libertad Norte",
            "La Libertad Centro",
            "La Libertad Oeste",
            "La Libertad Este",
            "La Libertad Costa",
            "La Libertad Sur"
        ],

        "San Salvador" => [
            "San Salvador Norte",
            "San Salvador Oeste",
            "San Salvador Este",
            "San Salvador Centro",
            "San Salvador Sur"
        ],

        "Cuscatlán" => [
            "Cuscatlán Norte",
            "Cuscatlán Sur"
        ],

        "La Paz" => [
            "La Paz Oeste",
            "La Paz Centro",
            "La Paz Este"
        ],

        "Cabañas" => [
            "Cabañas Este",
            "Cabañas Oeste"
        ],

        "San Vicente" => [
            "San Vicente Norte",
            "San Vicente Sur"
        ],

        "Usulután" => [
            "Usulután Norte",
            "Usulután Este",
            "Usulután Oeste"
        ],

        "San Miguel" => [
            "San Miguel Norte",
            "San Miguel Centro",
            "San Miguel Oeste"
        ],

        "Morazán" => [
            "Morazán Norte",
            "Morazán Sur"
        ],

        "La Unión" => [
            "La Unión Norte",
            "La Unión Sur"
        ]
    ];

    $sqlDepartamento = "SELECT id_departamento
                        FROM departamentos
                        WHERE nombre_departamento = ?";

    $stmtDepartamento = $conn->prepare($sqlDepartamento);

    $sqlMunicipio = "INSERT INTO municipios
                     (id_departamento, nombre_municipio)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE
                     nombre_municipio = VALUES(nombre_municipio)";

    $stmtMunicipio = $conn->prepare($sqlMunicipio);

    foreach ($municipios as $departamento => $listaMunicipios) {

        $stmtDepartamento->bind_param("s", $departamento);
        $stmtDepartamento->execute();

        $resultado = $stmtDepartamento->get_result();
        $fila = $resultado->fetch_assoc();
        $idDepartamento = $fila["id_departamento"] ?? null;

        if (!$idDepartamento) {
            throw new Exception(
                "No se encontró el departamento: " . $departamento
            );
        }

        foreach ($listaMunicipios as $municipio) {

            $stmtMunicipio->bind_param("is", $idDepartamento, $municipio);
            $stmtMunicipio->execute();
        }
    }

    echo "Municipios insertados correctamente.\n";
}