<?php 
    class Hospital {
        private mysqli $conn;
        
        public function __construct($dbConnection){
            $this->conn = $dbConnection;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== GET ==========
        //---Lista de todos los hospitales---
        public function listarHospitales(){
            $sql = "SELECT id_hospital, id_departamento, id_municipio, id_distrito, nombre_hospital, direccion FROM hospitales WHERE activo = 1";
            $hospitales = $this->conn->query($sql);
            return $hospitales->fetch_all(MYSQLI_ASSOC);
        }

        //---Busqueda por ID---
        public function buscarPorID($id){
            $sql = ("SELECT id_hospital, id_departamento, id_municipio, id_distrito, nombre_hospital, direccion FROM  hospitales WHERE id_hospital = ? AND activo=1");
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            
            if($stmt->execute()){
                return $stmt->get_result()->fetch_assoc(); 
            }
        }

        //---Busqueda mediante filtros---
        public function buscarPorFiltros($nombre, $departamento, $municipio, $distrito){
            $sql = ("SELECT id_hospital, id_departamento, id_municipio, id_distrito, nombre_hospital, direccion  FROM  hospitales WHERE activo=1 ");
            $parametros = [];
            $tipoParametro = "";

            //buscamos por nombre
            if ($nombre !== ''){
                $sql .= " AND nombre_hospital LIKE ?";
                $parametros[] = "%".$nombre."%";
                $tipoParametro .= "s";
            }
            //por departamento
            if ($departamento !== ''){
                $sql .= " AND id_departamento = ?";
                $parametros[] = $departamento;
                $tipoParametro .= "i";
            }
            //por municipio
            if ($municipio !== ''){
                $sql .= " AND id_municipio = ?";
                $parametros[] = $municipio;
                $tipoParametro .= "i";
            }
            //por distrito
            if ($distrito !== ''){
                $sql .= " AND id_distrito = ?";
                $parametros[] = $distrito;
                $tipoParametro .= "i";
            }

            //ejecutamos
            $stmt = $this->conn->prepare($sql);
            if (!empty($parametros)) {
                $stmt->bind_param($tipoParametro, ...$parametros);
            }
            $stmt->execute();

            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== POST ==========

        //--Agregar nuevo hospital---
        public function agregarNuevoHospital($nombre, $direccion, $departamento, $municipio, $distrito){
            //Si no existe podemos ingresar uno nuevo
            if($this->validacionDuplicadoHospital($nombre, $distrito)){
                $sql = "INSERT INTO hospitales 
                (nombre_hospital, direccion, id_departamento, 
                id_municipio, id_distrito) VALUES (?,?,?,?,?)";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("ssiii", $nombre, $direccion, $departamento, $municipio, $distrito); 
                $stmt->execute();

                return true;
            }
            else{
                return false;
            }
        }

        //Funcion que valida si ya esta un hopital con el mismo nombre en el mismo municiopio en la BD
        public function validacionDuplicadoHospital($nombre, $distrito){
            $sql = "SELECT id_hospital FROM hospitales WHERE nombre_hospital = ? AND id_distrito = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("si", $nombre, $distrito); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return false;
            }
            else{ return true; }
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== PUT ==========

        //--- Modificar un hospital ---
        public function actualizarHospital($id, $nombre, $direccion, $departamento, $municipio, $distrito){
            $validacion = $this->validacionesPUT($id,$departamento,$municipio,$distrito);
            
            if($validacion === 0){
                $sql = "UPDATE hospitales SET nombre_hospital = ?, direccion = ?, id_departamento = ?, id_municipio = ?,
                id_distrito = ? WHERE id_hospital = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("ssiiii", $nombre, $direccion, $departamento, $municipio, $distrito, $id); 
                $stmt->execute();

                return 0;
            }
            else if($validacion === 1) {return 1;}
            else if($validacion === 2) {return 2;}
            else if($validacion === 3) {return 3;}
            else{return 4;}
        }

        //validaciones
        public function validacionesPUT($id, $departamento, $municipio, $distrito){
            //Si no existe la id 
            $sql = "SELECT * FROM hospitales WHERE id_hospital = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows <= 0) {
                return 4;
            }
            else{
                //Si no existe el departamento
                $sql = "SELECT * FROM departamentos WHERE id_departamento = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $departamento); 
                $stmt->execute();
                if ($stmt->get_result()->num_rows <= 0) {
                    return 1;
                }
                else{
                    //si no existe el municipio
                    $sql = "SELECT * FROM municipios WHERE id_municipio = ?";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->bind_param("i", $municipio); 
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows <= 0) {
                        return 2;
                    }
                    else{
                        //si no existe el distrito
                        $sql = "SELECT * FROM distritos WHERE id_distrito = ?";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->bind_param("i", $distrito); 
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows <= 0) {
                            return 3;
                        }
                    }
                }
            }
            return 0;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== DELETE ==========

        public function eliminar($id){
            //Si la id no existe o fue ilimnada de la DB
            if($this->validacionEliminar($id) === false){
                return ;
            }

            $sql = "UPDATE hospitales SET activo = 0  WHERE id_hospital = ? AND activo = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();

            return 'Hospital eliminado exitosamente';
        }

        public function validacionEliminar($id){
            //Solo busca los activos
            $sql = "SELECT * FROM hospitales WHERE id_hospital = ? AND activo = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows <= 0) {
                return false;
            }
            else{return true;}
        }
    }
?>