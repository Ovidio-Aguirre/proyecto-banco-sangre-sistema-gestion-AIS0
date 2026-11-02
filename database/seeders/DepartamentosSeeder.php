<?php

function seedDepartamentos(mysqli $conn): void
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
            VALUES (?)
            ON DUPLICATE KEY UPDATE nombre_departamento = VALUES(nombre_departamento)";

    $stmt = $conn->prepare($sql);

    foreach ($departamentos as $departamento) {
        $stmt->bind_param("s", $departamento);
        $stmt->execute();
    }

    echo "Departamentos insertados correctamente.\n";
}