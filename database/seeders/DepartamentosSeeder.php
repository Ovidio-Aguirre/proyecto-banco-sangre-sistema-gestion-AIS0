<?php

function seedDepartamentos(PDO $pdo): void
{
    $departamentos = [
        "Ahuachapán",
        "Santa Ana",
        "Sonsonate",
        "Chalatenango",
        "La Libertad",
        "San Salvador",
        "Cuscatlán",
        "La Paz",
        "Cabañas",
        "San Vicente",
        "Usulután",
        "San Miguel",
        "Morazán",
        "La Unión"
    ];

    $sql = "INSERT INTO departamentos (nombre_departamento)
            VALUES (:departamento)
            ON DUPLICATE KEY UPDATE nombre_departamento = VALUES(nombre_departamento)";

    $stmt = $pdo->prepare($sql);

    foreach ($departamentos as $departamento) {
        $stmt->execute([
            ":departamento" => $departamento
        ]);
    }

    echo "Departamentos insertados correctamente.\n";
}