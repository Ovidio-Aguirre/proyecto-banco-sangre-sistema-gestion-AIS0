<?php

function seedDistritos(PDO $pdo): void
{
    $datos = [
    // Departamento de Ahuachapán
    "Ahuachapán Norte" => [
        "Atiquizaya",
        "El Refugio",
        "San Lorenzo",
        "Turín"
    ],
    "Ahuachapán Centro" => [
        "Ahuachapán",
        "Apaneca",
        "Concepción de Ataco",
        "Tacuba"
    ],
    "Ahuachapán Sur" => [
        "Guaymango",
        "Jujutla",
        "San Francisco Menéndez",
        "San Pedro Puxtla"
    ],

    // Departamento de San Salvador
    "San Salvador Norte" => [
        "Aguilares",
        "El Paisnal",
        "Guazapa"
    ],
    "San Salvador Oeste" => [
        "Apopa",
        "Nejapa"
    ],
    "San Salvador Este" => [
        "Ilopango",
        "San Martín",
        "Soyapango",
        "Tonacatepeque"
    ],
    "San Salvador Centro" => [
        "Ayutuxtepeque",
        "Mejicanos",
        "San Salvador",
        "Cuscatancingo",
        "Ciudad Delgado"
    ],
    "San Salvador Sur" => [
        "Panchimalco",
        "Rosario de Mora",
        "San Marcos",
        "Santo Tomás",
        "Santiago Texacuangos"
    ],

    // Departamento de La Libertad
    "La Libertad Norte" => [
        "Quezaltepeque",
        "San Matías",
        "San Pablo Tacachico"
    ],
    "La Libertad Centro" => [
        "San Juan Opico",
        "Ciudad Arce"
    ],
    "La Libertad Oeste" => [
        "Colón",
        "Jayaque",
        "Sacacoyo",
        "Tepecoyo",
        "Talnique"
    ],
    "La Libertad Este" => [
        "Antiguo Cuscatlán",
        "Huizúcar",
        "Nuevo Cuscatlán",
        "San José Villanueva",
        "Zaragoza"
    ],
    "La Libertad Costa" => [
        "Chiltiupán",
        "Jicalapa",
        "La Libertad",
        "Tamanique",
        "Teotepeque"
    ],
    "La Libertad Sur" => [
        "Comasagua",
        "Santa Tecla"
    ],

    // Departamento de Chalatenango
    "Chalatenango Norte" => [
        "La Palma",
        "Citalá",
        "San Ignacio"
    ],
    "Chalatenango Centro" => [
        "Nueva Concepción",
        "Tejutla",
        "La Reina",
        "Agua Caliente",
        "Dulce Nombre de María",
        "El Paraíso",
        "San Fernando",
        "San Francisco Morazán",
        "San Rafael",
        "Santa Rita"
    ],
    "Chalatenango Sur" => [
        "Chalatenango",
        "Arcatao",
        "Azacualpa",
        "Comalapa",
        "Concepción Quezaltepeque",
        "El Carrizal",
        "La Laguna",
        "Las Vueltas",
        "Nombre de Jesús",
        "Nueva Trinidad",
        "Ojos de Agua",
        "Potonico",
        "San Antonio de La Cruz",
        "San Antonio Los Ranchos",
        "San Francisco Lempa",
        "San Isidro Labrador",
        "San José Cancasque",
        "San Miguel de Mercedes",
        "San José Las Flores",
        "San Luis del Carmen"
    ],

    // Departamento de Cuscatlán
    "Cuscatlán Norte" => [
        "Suchitoto",
        "San José Guayabal",
        "Oratorio de Concepción",
        "San Bartolomé Perulapía",
        "San Pedro Perulapán"
    ],
    "Cuscatlán Sur" => [
        "Cojutepeque",
        "San Rafael Cedros",
        "Candelaria",
        "Monte San Juan",
        "El Carmen",
        "San Cristóbal",
        "Santa Cruz Michapa",
        "San Ramón",
        "El Rosario",
        "Santa Cruz Analquito",
        "Tenancingo"
    ],

    // Departamento de Cabañas
    "Cabañas Este" => [
        "Sensuntepeque",
        "Victoria",
        "Dolores",
        "Guacotecti",
        "San Isidro"
    ],
    "Cabañas Oeste" => [
        "Ilobasco",
        "Tejutepeque",
        "Jutiapa",
        "Cinquera"
    ],

    // Departamento de La Paz
    "La Paz Oeste" => [
        "Cuyultitán",
        "Olocuilta",
        "San Juan Talpa",
        "San Luis Talpa",
        "San Pedro Masahuat",
        "Tapalhuaca",
        "San Francisco Chinameca"
    ],
    "La Paz Centro" => [
        "El Rosario",
        "Jerusalén",
        "Mercedes La Ceiba",
        "Paraíso de Osorio",
        "San Antonio Masahuat",
        "San Emigdio",
        "San Juan Tepezontes",
        "San Luis La Herradura",
        "San Miguel Tepezontes",
        "San Pedro Nonualco",
        "Santa María Ostuma",
        "Santiago Nonualco"
    ],
    "La Paz Este" => [
        "San Juan Nonualco",
        "San Rafael Obrajuelo",
        "Zacatecoluca"
    ],

    // Departamento de La Unión
    "La Unión Norte" => [
        "Anamorós",
        "Bolívar",
        "Concepción de Oriente",
        "El Sauce",
        "Lislique",
        "Nueva Esparta",
        "Pasaquina",
        "Polorós",
        "San José La Fuente",
        "Santa Rosa de Lima"
    ],
    "La Unión Sur" => [
        "Conchagua",
        "El Carmen",
        "Intipucá",
        "La Unión",
        "Meanguera del Golfo",
        "San Alejo",
        "Yayantique",
        "Yucuaiquín"
    ],

    // Departamento de San Miguel
    "San Miguel Norte" => [
        "Ciudad Barrios",
        "Sesori",
        "Nuevo Edén de San Juan",
        "San Gerardo",
        "San Luis de La Reina",
        "Carolina",
        "San Antonio del Mosco",
        "Chapeltique"
    ],
    "San Miguel Centro" => [
        "San Miguel",
        "Comacarán",
        "Uluazapa",
        "Moncagua",
        "Quelepa",
        "Chirilagua"
    ],
    "San Miguel Oeste" => [
        "Chinameca",
        "Nueva Guadalupe",
        "Lolotique",
        "San Jorge",
        "San Rafael Oriente",
        "El Tránsito"
    ],

    // Departamento de Morazán
    "Morazán Norte" => [
        "Arambala",
        "Cacaopera",
        "Corinto",
        "El Rosario",
        "Joateca",
        "Jocoaitique",
        "Meanguera",
        "Perquín",
        "San Fernando",
        "San Isidro",
        "Torola"
    ],
    "Morazán Sur" => [
        "Chilanga",
        "Delicias de Concepción",
        "El Divisadero",
        "Gualococti",
        "Guatajiagua",
        "Jocoro",
        "Lolotiquillo",
        "Osicala",
        "San Carlos",
        "San Francisco Gotera",
        "San Simón",
        "Sensembra",
        "Sociedad",
        "Yamabal",
        "Yoloaiquín"
    ],

    // Departamento de Santa Ana
    "Santa Ana Norte" => [
        "Masahuat",
        "Metapán",
        "Santa Rosa Guachipilín",
        "Texistepeque"
    ],
    "Santa Ana Centro" => [
        "Santa Ana"
    ],
    "Santa Ana Este" => [
        "Coatepeque",
        "El Congo"
    ],
    "Santa Ana Oeste" => [
        "Candelaria de la Frontera",
        "Chalchuapa",
        "El Porvenir",
        "San Antonio Pajonal",
        "San Sebastián Salitrillo",
        "Santiago de la Frontera"
    ],

    // Departamento de Sonsonate
    "Sonsonate Norte" => [
        "Juayúa",
        "Nahuizalco",
        "Salcoatitán",
        "Santa Catarina Masahuat"
    ],
    "Sonsonate Centro" => [
        "Sonsonate",
        "Nahulingo",
        "San Antonio del Monte",
        "Santo Domingo de Guzmán",
        "Sonzacate"
    ],
    "Sonsonate Este" => [
        "Izalco",
        "Armenia",
        "Caluco",
        "Cuisnahuat",
        "San Julián",
        "Santa Isabel Ishuatán"
    ],
    "Sonsonate Oeste" => [
        "Acajutla"
    ],

    // Departamento de Usulután
    "Usulután Norte" => [
        "Alegría",
        "Berlín",
        "El Triunfo",
        "Estanzuelas",
        "Jucuapa",
        "Mercedes Umaña",
        "Nueva Granada",
        "San Buenaventura",
        "Santiago de María"
    ],
    "Usulután Este" => [
        "Usulután",
        "California",
        "Concepción Batres",
        "Ereguayquín",
        "Jucuarán",
        "Ozatlán",
        "San Dionisio",
        "Santa Elena",
        "Santa María",
        "Tecapán"
    ],
    "Usulután Oeste" => [
        "Jiquilisco",
        "Puerto El Triunfo",
        "San Agustín",
        "San Francisco Javier",
        "Santa Elena" //Existe un distrito homónimo en la región Este; este corresponde a la región Oeste según la ley.
    ],

    // Departamento de San Vicente
    "San Vicente Norte" => [
        "Apastepeque",
        "San Esteban Catarina",
        "San Ildefonso",
        "San Lorenzo",
        "San Sebastián",
        "Santa Clara",
        "Santo Domingo"
    ],
    "San Vicente Sur" => [
        "San Vicente",
        "Guadalupe",
        "San Cayetano Istepeque",
        "Tecoluca",
        "Tepetitán",
        "Verapaz"
    ]
];

$sqlMunicipio = "SELECT id_municipio
                 FROM municipios
                 WHERE nombre_municipio = :nombre";

$stmtMunicipio = $pdo->prepare($sqlMunicipio);

$sqlDistrito = "INSERT INTO distritos
                (id_municipio, nombre_distrito)
                VALUES (:id_municipio, :nombre)
                ON DUPLICATE KEY UPDATE
                nombre_distrito = VALUES(nombre_distrito)";

$stmtDistrito = $pdo->prepare($sqlDistrito);

foreach ($datos as $municipio => $listaDistritos) {

    $stmtMunicipio->execute([
        ":nombre" => $municipio
    ]);

    $idMunicipio = $stmtMunicipio->fetchColumn();

    if (!$idMunicipio) {
        throw new Exception(
            "No se encontró el municipio: " . $municipio
        );
    }

    foreach ($listaDistritos as $distrito) {

        $stmtDistrito->execute([
            ":id_municipio" => $idMunicipio,
            ":nombre" => $distrito
        ]);
    }
}
}
