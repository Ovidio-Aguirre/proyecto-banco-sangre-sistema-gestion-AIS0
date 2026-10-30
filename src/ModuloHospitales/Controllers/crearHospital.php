<?php
//Conexión a la Base de Datos
header('Content-Type: application/json');
require_once __DIR__ . "/../../../app/config/database.php";

//metodos POST -Requiere cambio de nombre acorde al formulario-
if ($_SERVER["REQUEST_METHOD"] == "POST"){

    $nombre = $_POST['nombreHospital'];
    $direccion = $_POST['direccionHospital'];
    $departamento = $_POST['departamentoHospital'];
    $distrito = $_POST['distritoHospital'];
    $municipio = $_POST['municipioHospital'];

    //----------validaciones----------

    //validación nombre
    if(empty($nombre)){
        echo json_encode(['success' => false, 'mensaje' => 'El nombre del hospital es requerido']);
        exit;
    }

    //validación dirección
    if(empty($direccion)){
        echo json_encode(['success' => false, 'mensaje' => 'La dirección del hospital es requerida']);
        exit;
    }

    //validación departamento
    if($departamento <= 0){
        echo json_encode(['success' => false, 'mensaje' => 'No se ha seleccionado un departamento']);
        exit;
    }

    //validación distrito
    if($distrito <= 0){
        echo json_encode(['success' => false, 'mensaje' => 'No se ha seleccionado un distrito']);
        exit;
    }

    //validación distrito
    if($municipio <= 0){
        echo json_encode(['success' => false, 'mensaje' => 'No se ha seleccionado un municipio']);
        exit;
    }

    //verificar duplicados de hospitales
    //Verifica si el nombre es el mismo y se encuantra en el mismo municipio
    //se hace de esta manera por si hay más de un hospital con el mismo nombre
    //en un mismo departamento pero no por municipio o distrito
    $stmt = $conn->prepare("SELECT id_hospital FROM hospitales WHERE nombre_hospital = ? AND id_municipio = ?");
    $stmt->bind_param("si", $nombre, $municipio);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'mensaje' => 'Ya hay un hospital registrado con este nombre']);
        exit;
    }

    //-----------Insertar datos----------

    /*Pruebas: ID departamentos 
    1:Ahuachapán        2:Santa Ana     3:Sonsonate         4:La Libertad
    5:Chalatenango      6:Cuscatlán     7:San Salvador
    8:La Paz            9:Cabañas       10:San Vicente      11:Usulután
    12:San Miguel       13:Morazán      14:La Unión*/

    /*Pruebas: ID municipios 
    1-3:Ahuachapán         4-7:Santa Ana        8-11:Sonsonate     
    12-17:La Libertad      18-20:Chalatenango   21-22:Cuscatlán     
    23-27:San Salvador     28-30:La Paz         31-32:Cabañas   
    33-34:San Vicente      35-37:Usulután       38-40:San Miguel   
    41-42:Morazán          43-44:La Unión
    (orden: Norte, Centro, Oeste, Este, Costa, Sur)*/

    /*Pruebas: ID municipios 
    1-4:Ahuachapán Norte        5-8:Ahuachapán Centro        9-12:Ahuachapán Sur 
    13-16:Santa Ana Norte       17:Santa Ana Centro          18-19:Santa Este
    20-25:Santa Ana Oeste       26-29:Sonsonate Norte        30-34:Sonsonate Centro
    35-40:Sonsonate Este        41:Sonsonate Oeste           42-44:La Libertad Norte
    45-46:La Libertad Centro    47-51:La Libertad Oeste      52-56:La Libertad Este
    57-61:La Libertad Costa     62-63:La Libertad Sur        64-66:Chalatenango Norte
    67-76:Chalatenango Centro   77-96:Chalatenango Sur       97-101:Cuscatlán Norte
    102-112:Cuscatlán Sur       113-115:San Salvador Norte   116-117:San Salvador Oeste
    118-121:San Salvador Este   122-126:San Salvador Centro  127-131:San Salvador Sur
    132-138:La Paz Oeste        139-150:La Paz Centro        151-153:La Paz Este 
    154-158:Cabañas Este        159-162:Cabañas Oeste        163-169:San Vicente Norte
    170-175:San Vicente Sur     176-184:Usulután Norte       185-194:Usulután Este
    195-198:Usulután Oeste      199-206:San Miguel Norte     207-212:San Miguel Centro 
    213-218:San Miguel  Oeste   219-229:Morazán Norte        230-244:Morazán Sur
    245-254:La Unión Norte      255-262:La Unión Sur */

    $stmt = $conn->prepare(
        "INSERT INTO hospitales (nombre_hospital, direccion, id_departamento, id_municipio, id_distrito)
        VALUES (?,?,?,?,?)"
    );
    $stmt->bind_param("ssiii", $nombre, $direccion, $departamento, $municipio, $distrito);

    //ejecutamos
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    }
    else {
        echo json_encode(['success' => false, 'mensaje' => 'Error al crear hospital']);
    }

}
else {
    echo json_encode(['success' => false, 'mensaje' => 'Método no válido']);
}
?>
