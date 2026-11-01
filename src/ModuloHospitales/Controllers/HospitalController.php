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
                'Hospital' => $resultado
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

                validacionCamposVacios([$nombre, $departamento, $municipio, $distrito, $orden]);

                //Si existe orden
                if($orden){
                    if($orden !== 'ASC' && $orden !== 'DESC'){
                        respuesta(400, ['success' => false, 'error' => 'Tipo de orden invalido']);
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
            
            if($resultado){
                respuesta(200, ['success' => true, 'Hospitales' => $resultado]);
            }
            else{
                respuesta(400, ['success' => false, 'error' => 'No se encontro ningun hopital o se encuentra eliminado']);
            }
            
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== POST ==========
        function ingresarNuevoHospital(): void {

            $datosHospital = leerJson();

            $nombre = $datosHospital['nombreHospital'] ?? null;
            $direccion = $datosHospital['direccionHospital'] ?? null;
            $distrito = $datosHospital['distritoHospital'] ?? null;

            //Si alguno de los campos ingresados esta vacio
            if ( $nombre === '' || $nombre === NULL || $direccion === '' || $direccion === NULL || $distrito === ''|| $direccion === NULL ) {
                respuesta(400, ['success' => false, 'Mensaje:' => 'Todos los campos son abligatorios']);
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
                respuesta(201, ['success' => true, 'Mensaje:' => 'Nuevo Hospital ingresado Exitosamente']);
            }
            else if($resultado === 2){
                respuesta(409, ['success' => false, 'Mensaje:' => 'ID de distrito invalida']);
            }
            else{
                respuesta(409, ['success' => false, 'Mensaje:' => 'Este Hospital ya se encuentra registrado']);
            }
            
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== PUT ==========
        function modificarHospital(String $id): void {

            $datosHospital = leerJson();

            //pasamos los datos a variables
            $nombre = $datosHospital['nombreHospital'] ?? null;
            $direccion = $datosHospital['direccionHospital'] ?? null;
            $distrito = $datosHospital['distritoHospital'] ?? null;

            validarCampoEntero($distrito);
            validacionCamposVacios([$nombre]);
            validacionCamposVacios([$direccion]);

            $resultado = $this->modelo->actualizarHospital(
                $id,
                $nombre,
                $direccion,
                $distrito
            );

            //Segun la respuesta mostramos el mensaje
            switch($resultado){
                case 1:
                    respuesta(404, ['success' => false, 'Mensaje:' => 'ID no existe']);
                    break;
                case 2:
                    respuesta(404, ['success' => false, 'Mensaje:' => 'ID municipio no existe']);
                    break;
                default:
                    respuesta(200, ['success' => true, 'Mensaje:' => 'Hospital Actualizado correctamente']);
            }
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== DELETE ==========
        function eliminarHospital(String $id): void {

            $resultado = $this->modelo->eliminar($id);

            if($resultado){
                respuesta(200, ['success' => true, 'Mensaje:' => $resultado]);
            }
            else{
                respuesta(404, ['success' => false, 'Mensaje:' => 'Hospital no encontrado o eliminado']);
            }
        }
    }
?>