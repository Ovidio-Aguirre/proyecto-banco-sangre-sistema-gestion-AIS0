<?php

function seedMunicipios(PDO $pdo): void
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
                        WHERE nombre_departamento = :nombre";

    $stmtDepartamento = $pdo->prepare($sqlDepartamento);

    $sqlMunicipio = "INSERT INTO municipios
                     (id_departamento, nombre_municipio)
                     VALUES (:id_departamento, :nombre)
                     ON DUPLICATE KEY UPDATE
                     nombre_municipio = VALUES(nombre_municipio)";

    $stmtMunicipio = $pdo->prepare($sqlMunicipio);

    foreach ($municipios as $departamento => $listaMunicipios) {

        $stmtDepartamento->execute([
            ":nombre" => $departamento
        ]);

        $idDepartamento = $stmtDepartamento->fetchColumn();

        if (!$idDepartamento) {
            throw new Exception(
                "No se encontró el departamento: " . $departamento
            );
        }

        foreach ($listaMunicipios as $municipio) {

            $stmtMunicipio->execute([
                ":id_departamento" => $idDepartamento,
                ":nombre" => $municipio
            ]);
        }
    }

    echo "Municipios insertados correctamente.\n";
}