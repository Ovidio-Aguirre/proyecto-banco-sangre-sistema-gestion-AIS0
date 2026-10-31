<?php 
    class Paciente {
        private mysqli $conn;
        
        public function __construct($dbConnection){
            $this->conn = $dbConnection;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== GET ==========

        public function listarPacientes(){
            $sql = "SELECT * FROM pacientes";
            $pacientes = $this->conn->query($sql);
            return $pacientes->fetch_all(MYSQLI_ASSOC);
        }

        //---Busqueda por ID---
        public function buscarPorID($id){
            $sql = ("SELECT * FROM pacientes WHERE id_paciente = ?");
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            
            if($stmt->execute()){
                return $stmt->get_result()->fetch_assoc(); 
            }
        }

        //---Busqueda mediante filtros---
        public function buscarPorFiltros($busqueda, $tipo, $sexo){
            $sql = ("SELECT *  FROM  pacientes WHERE 1=1 ");
            $parametros = [];
            $tipoParametro = "";

            //buscamos por nombre o apellido
            if ($busqueda !== ''){
                $sql .= " AND nombre_paciente LIKE ? OR apellido_paciente LIKE ?";
                $parametros[] = "%".$busqueda."%";
                $parametros[] = "%".$busqueda."%";
                $tipoParametro .= "s";
                $tipoParametro .= "s";
            }
            //por departamento
            if ($tipo !== ''){
                $sql .= " AND id_tipo_sangre = ?";
                $parametros[] = $tipo;
                $tipoParametro .= "i";
            }
            //por municipio
            if ($sexo !== ''){
                $sql .= " AND sexo = ?";
                $parametros[] = $sexo;
                $tipoParametro .= "s";
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
        //--Agregar nuevo registro de paciente---
        public function agregarNuevoPaciente($nombre, $apellido, $tipo, $fecha, $sexo){
            $validacion = $this->validacionDuplicadoPaciente($nombre, $tipo, $apellido, $fecha);
            //Si no existe podemos ingresar uno nuevo
            if($validacion !== 0){
                return $validacion;
            }
            
            $sql = "INSERT INTO pacientes 
            (nombre_paciente, apellido_paciente, id_tipo_sangre, fecha_nacimiento, sexo) VALUES (?,?,?,?,?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssiss", $nombre, $apellido, $tipo, $fecha, $sexo); 
            $stmt->execute();
            return 0;
            
        }

        //Funcion que valida si ya esta un hopital con el mismo nombre en el mismo municiopio en la BD
        public function validacionDuplicadoPaciente($nombre, $tipo, $apellido, $fecha){

            $sql = "SELECT id_paciente FROM pacientes WHERE nombre_paciente = ? AND apellido_paciente = ? AND fecha_nacimiento = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sss", $nombre, $apellido, $fecha); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return 1;
            }
            else{
                $sql = "SELECT * FROM tipo_sangre WHERE id_tipo_sangre = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $tipo); 
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    return 2;
                }
            }
            return 0;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== PUT ==========

        //--- Modificar un paciente ---
        public function actualizarPaciente($id, $nombre, $apellido, $tipo, $fecha, $sexo){
            $validacion = $this->validacionesPUT($id, $tipo);
            
            if($validacion !== 0){
                return $validacion;
            }

            $sql = "UPDATE pacientes SET nombre_paciente = ?, apellido_paciente = ?, id_tipo_sangre = ?, fecha_nacimiento = ?, sexo = ? WHERE id_paciente = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssissi", $nombre, $apellido, $tipo, $fecha, $sexo, $id); 
            $stmt->execute();

            return 0;
        }

        //validaciones
        public function validacionesPUT($id, $tipo){
            //Si no existe la id 
            $sql = "SELECT * FROM pacientes WHERE id_paciente = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows <= 0) {
                return 1;
            }
            else{
                $sql = "SELECT * FROM tipo_sangre WHERE id_tipo_sangre = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $tipo); 
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    return 2;
                }
            }
            return 0;
        }
    }
?>