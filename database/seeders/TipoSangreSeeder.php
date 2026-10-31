<?php

function seedTiposSangre(PDO $pdo): void
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
            VALUES (:tipo)
            ON DUPLICATE KEY UPDATE tipo = VALUES(tipo)";

    $stmt = $pdo->prepare($sql);

    foreach ($tipos as $tipo) {
        $stmt->execute([
            ":tipo" => $tipo
        ]);
    }

    echo "Tipos de sangre insertados correctamente.\n";
}