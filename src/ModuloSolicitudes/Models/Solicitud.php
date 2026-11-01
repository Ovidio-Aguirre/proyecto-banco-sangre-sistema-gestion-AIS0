<?php 
    class Solicitud {
        private mysqli $conn;
        
        public function __construct($dbConnection){
            $this->conn = $dbConnection;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== GET ==========

        public function listarSolicitudes(){
            $sql = "SELECT * FROM solicitudes WHERE estado != 0";
            $solicitudes = $this->conn->query($sql);
            return $solicitudes->fetch_all(MYSQLI_ASSOC);
        }

        //---Busqueda por ID---
        public function buscarPorID($id){
            $sql = ("SELECT * FROM solicitudes WHERE stado != 0 AND id_solictud = ?");
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            
            if($stmt->execute()){
                return $stmt->get_result()->fetch_assoc(); 
            }
        }

        //---Busqueda mediante filtros---
        public function buscarPorFiltros($paciente, $hospital, $estado){
            $parametros = [];
            $tipoParametro = "";
            $select = "";
            $inner = [];
            $where = "";

            //buscamos por nombre o apellido
            if ($paciente !== ''){
                $select .= " ,p.nombre_paciente, p.apellido_paciente";
                $inner[] = " INNER JOIN pacientes p ON s.id_paciente = p.id_paciente";
                $where .= " AND (p.nombre_paciente LIKE ? OR p.apellido_paciente LIKE ?)";
                $parametros[] = "%".$paciente."%";
                $parametros[] = "%".$paciente."%";
                $tipoParametro .= "s";
                $tipoParametro .= "s";
            }
            //por hospital
            if ($hospital !== ''){
                $select .= " ,h.nombre_hospital";
                $inner[] = " INNER JOIN hospitales h ON s.id_hospital  = h.id_hospital";
                $where .= " AND (h.nombre_hospital LIKE ?)";
                $parametros[] = "%".$hospital."%";
                $tipoParametro .= "s";
            }
            
            $sql = "SELECT s.*";
            //Si se ingresaron alguno de los datos de paciente o hospital
            if($select !== ''){
                $sql .= $select . " FROM solicitudes s ";
                $sql .= implode(" ", $inner);
                $sql .= " WHERE s.estado > 0 ";
                $sql .= $where;
            }
            else{
                $sql .= " FROM solicitudes s WHERE estado > 0";
            }

            //por estado
            if ($estado !== ''){
                $sql .= " AND estado = ?";
                $parametros[] = $estado;
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

        public function buscarPorIDPacienteHospital($pacienteID, $hospitalID){
            $sql = ("SELECT *  FROM solicitudes WHERE estado > 0");
            $parametros = [];
            $tipoParametro = "";

            //buscamos por paciente
            if ($pacienteID !== ''){
                $sql .= " AND id_paciente = ?";
                $parametros[] = $pacienteID;
                $tipoParametro .= "i";
            }
            //por hospital
            if ($hospitalID !== ''){
                $sql .= " AND id_hospital = ?";
                $parametros[] = $hospitalID;
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
        //--Agregar una nueva solicitud---
        public function agregarNuevaSolicitud($idPaciente, $idHospital, $fecha, $unidades, $justificacion){
            $estado = 1;
            $validacion = $this->validacionSolicitudes($idPaciente, $idHospital);
            //Si no existe podemos ingresar uno nuevo
            if($validacion !== 0){
               return $validacion;
            }
            
            $sql = "INSERT INTO solicitudes 
            (id_paciente, id_hospital, fecha_solicitud, unidades_solicitadas, justificacion, estado) VALUES (?,?,?,?,?,?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iisisi", $idPaciente, $idHospital, $fecha, $unidades, $justificacion, $estado); 
            $stmt->execute();
            return 0;
            
        }

        //Funcion que valida si ya esta un hopital con el mismo nombre en el mismo municiopio en la BD
        public function validacionSolicitudes($paciente, $hospital){

            $sql = "SELECT id_paciente FROM pacientes WHERE id_paciente = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $paciente); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return 1;
            }
            else{
                $sql = "SELECT id_hospital FROM hospitales WHERE id_hospital = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $hospital); 
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    return 2;
                }
            }
            return 0;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== PUT ==========

        //--- Modificar una solicitud ---
        public function actualizarSolicitud($id,$idPaciente, $idHospital, $unidades, $justificacion, $estado){
            $validacion = $this->validacionesPUT($id, $idPaciente, $idHospital);
            
            if($validacion !== 0){
                return $validacion;
            }

            $sql = "UPDATE solicitudes SET id_paciente = ?, id_hospital = ?, unidades_solicitadas = ?, justificacion = ?, estado = ? WHERE id_solicitud = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iiisii", $idPaciente, $idHospital, $unidades, $justificacion, $estado, $id); 
            $stmt->execute();

            return 0;
        }

        //validaciones
        public function validacionesPUT($id, $paciente, $hospital){
            //Si no existe la id 
            $sql = "SELECT * FROM hospitales WHERE id_hospital= ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $hospital); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                return 1;
            }
            else{
                $sql = "SELECT * FROM pacientes WHERE id_paciente = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $paciente); 
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    return 2;
                }
                else{
                    $sql = "SELECT * FROM solicitudes WHERE id_solicitud = ?";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->bind_param("i", $id); 
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows === 0) {
                        return 3;
                    }
                }
            }
            return 0;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== DELETE ==========
        public function eliminarSolicitud($id){
            //Si la id no existe o fue elimnada de la DB
            if($this->validacionEliminar($id) === false){
                return false;
            }
            $estado = 0;

            $sql = "UPDATE solicitudes SET estado = ? WHERE id_solicitud = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $estado, $id); 
            if(!$stmt->execute()){
                return false;
            }
            return 'Solicitud eliminada correctamente';
            
        }

        public function validacionEliminar($id){
            //Solo busca los activos
            $sql = "SELECT * FROM solicitudes WHERE id_solicitud = ? AND estado != 0";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                return false;
            }
            else{return true;}
        }
    }
?>