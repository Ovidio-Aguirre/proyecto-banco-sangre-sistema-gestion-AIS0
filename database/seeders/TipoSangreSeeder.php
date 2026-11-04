<?php

function seedTiposSangre(mysqli $conn): void
{
    $tipos = [
        "A+",
        "A-",
        "B+",
        "B-",
        "AB+",
        "AB-",
        "O+",
        "O-"
    ];

    $sql = "INSERT INTO tipo_sangre (tipo)
            VALUES (?)
            ON DUPLICATE KEY UPDATE tipo = VALUES(tipo)";

    $stmt = $conn->prepare($sql);

    foreach ($tipos as $tipo) {
        $stmt->bind_param("s", $tipo);
        $stmt->execute();
    }

    echo "Tipos de sangre insertados correctamente.\n";
}