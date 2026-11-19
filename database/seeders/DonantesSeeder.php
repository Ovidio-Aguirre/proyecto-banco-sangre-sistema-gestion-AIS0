<?php

function seedDonantes(mysqli $conn): void
{
    $donantes = [
        [
            "tipo" => "A+",  
            "dui" => "00000000-1", 
            "nombre" => "Juan",      
            "apellido" => "Pérez",       
            "contacto" => "7000-0001", 
            "fecha_nacimiento" => "1990-05-12", 
            "sexo" => "M"
        ],
        [
            "tipo" => "O-",  
            "dui" => "00000000-2", 
            "nombre" => "María",     
            "apellido" => "López",       
            "contacto" => "7000-0002", 
            "fecha_nacimiento" => "1992-09-23", 
            "sexo" => "F"
        ],
        [
            "tipo" => "B+",  
            "dui" => "00000000-3", 
            "nombre" => "Carlos",    
            "apellido" => "Hernández",   
            "contacto" => "7000-0003", 
            "fecha_nacimiento" => "1985-02-18", 
            "sexo" => "M"
        ],
        [
            "tipo" => "AB+", 
            "dui" => "00000000-4", 
            "nombre" => "Ana",       
            "apellido" => "Martínez",    
            "contacto" => "7000-0004", 
            "fecha_nacimiento" => "1995-11-07", 
            "sexo" => "F"
        ],
        [
            "tipo" => "A-",  
            "dui" => "00000000-5", 
            "nombre" => "Luis",      
            "apellido" => "Ramírez",     
            "contacto" => "7000-0005", 
            "fecha_nacimiento" => "1988-03-30", 
            "sexo" => "M"
        ],
        [
            "tipo" => "B-",  
            "dui" => "00000000-6",
            "nombre" => "Karla",     
            "apellido" => "Menjívar",    
            "contacto" => "7000-0006", 
            "fecha_nacimiento" => "1993-07-14", 
            "sexo" => "F"
        ],
        [
            "tipo" => "AB-", 
            "dui" => "00000000-7", 
            "nombre" => "Roberto",   
            "apellido" => "Alvarado",    
            "contacto" => "7000-0007", 
            "fecha_nacimiento" => "1983-12-02", 
            "sexo" => "M"
        ],
        [
            "tipo" => "O+",  
            "dui" => "00000000-8", 
            "nombre" => "Gabriela",  
            "apellido" => "Flores",      
            "contacto" => "7000-0008", 
            "fecha_nacimiento" => "1997-01-25", 
            "sexo" => "F"
        ],
        [
            "tipo" => "O+",  
            "dui" => "00000000-9", 
            "nombre" => "Miguel",    
            "apellido" => "Guzmán",      
            "contacto" => "7000-0009", 
            "fecha_nacimiento" => "1991-08-19", 
            "sexo" => "M"
        ],
        [
            "tipo" => "A+",  
            "dui" => "00000001-0", 
            "nombre" => "Sofía",     
            "apellido" => "Quintanilla", 
            "contacto" => "7000-0010", 
            "fecha_nacimiento" => "1999-04-08", 
            "sexo" => "F"
        ],
        [
            "tipo" => "O-",  
            "dui" => "00000001-1", 
            "nombre" => "José",      
            "apellido" => "Rivas",       
            "contacto" => "7000-0011", 
            "fecha_nacimiento" => "1980-10-11", 
            "sexo" => "M"
        ],
        [
            "tipo" => "B+",  
            "dui" => "00000001-2", 
            "nombre" => "Daniela",   
            "apellido" => "Cruz",        
            "contacto" => "7000-0012",
            "fecha_nacimiento" => "1994-06-03", 
            "sexo" => "F"
        ],
    ];

    $sqlTipo = "SELECT id_tipo_sangre
                FROM tipo_sangre
                WHERE tipo = ?";

    $stmtTipo = $conn->prepare($sqlTipo);

    $sql = "INSERT INTO donantes
            (id_tipo_sangre, dui, nombre, apellido,
             contacto, fecha_nacimiento, sexo, activo)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
            id_tipo_sangre = VALUES(id_tipo_sangre),
            nombre = VALUES(nombre),
            apellido = VALUES(apellido),
            contacto = VALUES(contacto),
            fecha_nacimiento = VALUES(fecha_nacimiento),
            sexo = VALUES(sexo),
            activo = 1";

    $stmt = $conn->prepare($sql);

    foreach ($donantes as $donante) {

        $tipo = $donante["tipo"];
        $stmtTipo->bind_param("s", $tipo);
        $stmtTipo->execute();

        $resultado = $stmtTipo->get_result();
        $fila = $resultado->fetch_assoc();

        $idTipo = $fila["id_tipo_sangre"] ?? null;

        if (!$idTipo) {
            throw new Exception(
                "No se encontró el tipo de sangre: " . $donante["tipo"]
            );
        }

        $dui = $donante["dui"];
        $nombre = $donante["nombre"];
        $apellido = $donante["apellido"];
        $contacto = $donante["contacto"];
        $fecha = $donante["fecha_nacimiento"];
        $sexo = $donante["sexo"];
        $stmt->bind_param("issssss", $idTipo, $dui, $nombre, $apellido, $contacto, $fecha, $sexo);
        $stmt->execute();
    }
    $stmtTipo->close();
    $stmt->close();

    echo "Donantes insertados correctamente.\n";
}