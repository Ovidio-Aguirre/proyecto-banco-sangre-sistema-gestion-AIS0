<?php
    class HospitalController implements Controlador
    {
        public function __construct(
            private Hospital $modelo
        ) {}

        public function control(string $metodo, array $partes): void
        {
            $id = $partes[1] ?? null;

            // /hospitales
            if ($id === null) {
                match ($metodo) {
                    'GET'  => $this->buscarHospital(),
                    'POST' => $this->ingresarNuevoHospital(),
                    default => noPermitido(),
                };
                return;
            }

            // /hospitales/{id}
            $idHospital = validarId($id, 'ID de hospital inválido');

            if (isset($partes[2])) {
                noEncontrado();
            }

            match ($metodo) {
                'GET'    => $this->obtenerHospital($idHospital),
                'PUT'    => $this->modificarHospital($idHospital),
                'DELETE' => $this->eliminarHospital($idHospital),
                default  => noPermitido(),
            };
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== GET ==========
        //----------Funcion que devuelve el tipo de busqueda realizada----------
        private function obtenerHospital(int $id): void
        {
            $resultado = $this->modelo->buscarPorID($id);

            if (!$resultado) {
                respuesta(404, [
                    'success' => false,
                    'mensaje' => 'Hospital no encontrado'
                ]);
            }

            respuesta(200, [
                'success' => true,
                'hospital' => $resultado
            ]);
        }

        private function buscarHospital(): void {
            //Busqueda por Nombre, departamento, municipio, distrito o orden
            if(isset($_GET["nombreHospital"])||
            isset($_GET["departamentoHospital"])||
            isset($_GET["municipioHospital"])||
            isset($_GET["distritoHospital"])|| 
            isset($_GET["ordenHospital"])){

                $nombre = trim($_GET["nombreHospital"] ?? '');
                $departamento = trim($_GET["departamentoHospital"] ?? '');
                $municipio = trim($_GET["municipioHospital"] ?? '');
                $distrito = trim($_GET["distritoHospital"] ?? '');
                $orden = trim($_GET["ordenHospital"] ?? '');
                $orden = strtoupper($orden);

                validarCamposVacios([$nombre, $departamento, $municipio, $distrito, $orden]);

                //Si existe orden
                if($orden){
                    if($orden !== 'ASC' && $orden !== 'DESC'){
                        respuesta(400, ['success' => false, 'mensaje' => 'Tipo de orden inválido']);
                        exit;
                    }
                } 

                if($departamento){validarCampoEntero($departamento);}
                if($municipio){validarCampoEntero($municipio);}
                if($distrito){validarCampoEntero($distrito);}

                //Si se encuentra el campo
                $resultado = $this->modelo->buscarPorFiltros(
                    $nombre,
                    $departamento,
                    $municipio,
                    $distrito,
                    $orden
                );
            }

            //Busqueda de todos los hospitales
            else{
                $resultado = $this->modelo->listarHospitales();
            }

            // El modelo devuelve 1, 2 o 3 si el departamento, municipio o distrito no existe
            if($resultado === 1){
                respuesta(400, ['success' => false, 'mensaje' => 'Departamento fuera de rango']);
            }
            if($resultado === 2){
                respuesta(400, ['success' => false, 'mensaje' => 'Municipio fuera de rango']);
            }
            if($resultado === 3){
                respuesta(400, ['success' => false, 'mensaje' => 'Distrito fuera de rango']);
            }

            // Sin resultados no es un error: lista vacia con 200
            respuesta(200, ['success' => true, 'hospitales' => $resultado ?: []]);
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== POST ==========
        function ingresarNuevoHospital(): void {

            $datosHospital = leerJson();

            $nombre = isset($datosHospital['nombreHospital']) && is_string($datosHospital['nombreHospital']) ? trim($datosHospital['nombreHospital']) : null;
            $direccion = isset($datosHospital['direccionHospital']) && is_string($datosHospital['direccionHospital']) ? trim($datosHospital['direccionHospital']) : null;
            $distrito = $datosHospital['distritoHospital'] ?? null;

            //Si alguno de los campos ingresados esta vacio
            if ( $nombre === '' || $nombre === NULL || $direccion === '' || $direccion === NULL || $distrito === '' || $distrito === NULL ) {
                respuesta(400, ['success' => false, 'mensaje' => 'Todos los campos son obligatorios']);
                exit;
            }

            validarCampoEntero($distrito);

            //Si es correcto
            $resultado = $this->modelo->agregarNuevoHospital(
                $nombre,
                $direccion,
                $distrito
            );

            if($resultado === 0){
                respuesta(201, ['success' => true, 'mensaje' => 'Nuevo hospital ingresado exitosamente']);
            }
            else if($resultado === 3){
                respuesta(200, ['success' => true, 'mensaje' => 'Hospital reactivado']);
            }
            else if($resultado === 2){
                respuesta(409, ['success' => false, 'mensaje' => 'ID de distrito inválido']);
            }
            else{
                respuesta(409, ['success' => false, 'mensaje' => 'Este hospital ya se encuentra registrado']);
            }
            
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== PUT ==========
        function modificarHospital(int $id): void {

            $datosHospital = leerJson();

            //pasamos los datos a variables
            $nombre = isset($datosHospital['nombreHospital']) && is_string($datosHospital['nombreHospital']) ? trim($datosHospital['nombreHospital']) : null;
            $direccion = isset($datosHospital['direccionHospital']) && is_string($datosHospital['direccionHospital']) ? trim($datosHospital['direccionHospital']) : null;
            $distrito = $datosHospital['distritoHospital'] ?? null;

            validarCampoEntero($distrito);
            validarCamposVacios([$nombre]);
            validarCamposVacios([$direccion]);

            $resultado = $this->modelo->actualizarHospital(
                $id,
                $nombre,
                $direccion,
                $distrito
            );

            //Segun la respuesta mostramos el mensaje
            switch($resultado){
                case 1:
                    respuesta(404, ['success' => false, 'mensaje' => 'Hospital no existe o está inactivo']);
                    break;
                case 2:
                    respuesta(404, ['success' => false, 'mensaje' => 'ID de distrito no existe']);
                    break;
                case 3:
                    respuesta(409, ['success' => false, 'mensaje' => 'Ya existe un hospital con ese nombre en el distrito']);
                    break;
                default:
                    respuesta(200, ['success' => true, 'mensaje' => 'Hospital actualizado correctamente']);
            }
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== DELETE ==========
        function eliminarHospital(String $id): void {

            $resultado = $this->modelo->eliminar($id);

            if($resultado === 1){
                respuesta(409, ['success' => false, 'mensaje' => 'El hospital tiene solicitudes pendientes o aprobadas']);
            }
            if($resultado){
                respuesta(200, ['success' => true, 'mensaje' => $resultado]);
            }
            else{
                respuesta(404, ['success' => false, 'mensaje' => 'Hospital no encontrado o eliminado']);
            }
        }
    }
?>
