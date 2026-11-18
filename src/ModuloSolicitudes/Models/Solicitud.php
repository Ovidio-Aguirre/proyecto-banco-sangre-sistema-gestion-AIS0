<?php 
    class Solicitud {
        private mysqli $conn;
        
        public function __construct($dbConnection){
            $this->conn = $dbConnection;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== GET ==========

        public function listarSolicitudes(){
            $sql = "SELECT s.*, p.nombre_paciente, p.apellido_paciente, p.id_tipo_sangre, h.nombre_hospital,
                    (SELECT COUNT(*) FROM donaciones d
                      WHERE d.id_solicitud = s.id_solicitud
                        AND d.estado IN ('EN_EVALUACION','DISPONIBLE','RESERVADA','UTILIZADA')
                        AND (d.estado = 'UTILIZADA' OR d.fecha_caducidad >= CURDATE())
                    ) AS unidades_asignadas
                    FROM solicitudes s
                    LEFT JOIN pacientes p ON p.id_paciente = s.id_paciente
                    LEFT JOIN hospitales h ON h.id_hospital = s.id_hospital
                    WHERE s.estado != 'INHABILITADA'";
            $solicitudes = $this->conn->query($sql);
            return $solicitudes->fetch_all(MYSQLI_ASSOC);
        }

        //---Busqueda por ID---
        public function buscarPorID($id){
            $sql = ("SELECT * FROM solicitudes WHERE estado != 'INHABILITADA' AND id_solicitud = ?");
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            
            if($stmt->execute()){
                return $stmt->get_result()->fetch_assoc(); 
            }
        }

        //---Busqueda mediante filtros---
        public function buscarPorFiltros($paciente, $hospital, $estado, $orden){
            $parametros = [];
            $tipoParametro = "";
            $select = "";
            $inner = [];
            $where = "";
            $ordenPor = "";

            if ($paciente !== '' || $orden !== '') {
                $select .= ", p.nombre_paciente, p.apellido_paciente";
                $inner[] = "INNER JOIN pacientes p ON s.id_paciente = p.id_paciente";
            }

            //buscamos por nombre o apellido
            if ($paciente !== ''){
                $where .= " AND (p.nombre_paciente LIKE ? OR p.apellido_paciente LIKE ?)";
                $parametros[] = "%".$paciente."%";
                $parametros[] = "%".$paciente."%";
                $tipoParametro .= "ss";
            }

            //por hospital
            if ($hospital !== ''){
                $where .= " AND (h.nombre_hospital LIKE ?)";
                $parametros[] = "%".$hospital."%";
                $tipoParametro .= "s";
            }

            //por estado
            if ($estado !== ''){
                $where .= " AND s.estado = ?";
                $parametros[] = $estado;
                $tipoParametro .= "s";
            }

            if ($orden === 'ASC') {
                $ordenPor = " ORDER BY p.apellido_paciente ASC";
            } elseif ($orden === 'DESC') {
                $ordenPor = " ORDER BY p.apellido_paciente DESC";
            }
            
            $sql = "SELECT s.*, p.nombre_paciente, p.apellido_paciente, p.id_tipo_sangre, h.nombre_hospital,
                    (SELECT COUNT(*) FROM donaciones d
                      WHERE d.id_solicitud = s.id_solicitud
                        AND d.estado IN ('EN_EVALUACION','DISPONIBLE','RESERVADA','UTILIZADA')
                        AND (d.estado = 'UTILIZADA' OR d.fecha_caducidad >= CURDATE())
                    ) AS unidades_asignadas
                    FROM solicitudes s
                    LEFT JOIN pacientes p ON p.id_paciente = s.id_paciente
                    LEFT JOIN hospitales h ON h.id_hospital = s.id_hospital
                    WHERE s.estado != 'INHABILITADA'";
            $sql .= $where . $ordenPor;

            //ejecutamos
            $stmt = $this->conn->prepare($sql);
            if (!empty($parametros)) {
                $stmt->bind_param($tipoParametro, ...$parametros);
            }
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        public function buscarPorIDPacienteHospital($pacienteID, $hospitalID){
            $sql = ("SELECT *  FROM solicitudes WHERE estado != 'INHABILITADA'");
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
            $estado = 'PENDIENTE';
            $validacion = $this->validacionSolicitudes($idPaciente, $idHospital);
            //Si no existe podemos ingresar uno nuevo
            if($validacion !== 0){
               return $validacion;
            }
            
            $sql = "INSERT INTO solicitudes 
            (id_paciente, id_hospital, fecha_solicitud, unidades_solicitadas, justificacion, estado) VALUES (?,?,?,?,?,?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iisiss", $idPaciente, $idHospital, $fecha, $unidades, $justificacion, $estado); 
            $stmt->execute();
            return 0;
            
        }

        //Funcion que valida si ya esta un hopital con el mismo nombre en el mismo municiopio en la BD
        public function validacionSolicitudes($paciente, $hospital){

            $sql = "SELECT id_paciente FROM pacientes WHERE id_paciente = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $paciente); 
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

        //--- Aprobar/Rechazar una solicitud ---
        public function resolverSolicitud($id, $estado, $motivo = null){
            if (!in_array($estado, ['APROBADA', 'RECHAZADA'], true)) {
                return 3;
            }

            $sql = "SELECT estado FROM solicitudes WHERE id_solicitud = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();

            if ($fila === null) {
                return 1;
            }
            if ($fila['estado'] !== 'PENDIENTE') {
                return 2;
            }

            $motivo = ($estado === 'RECHAZADA') ? $motivo : null;

            $sql = "UPDATE solicitudes SET estado = ?, motivo_rechazo = ? WHERE id_solicitud = ? AND estado = 'PENDIENTE'";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssi", $estado, $motivo, $id);
            $stmt->execute();

            return 0;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== PATCH ==========
        function entregarSolicitud($estado, $id){
            $validacion = $this->validacionesPATCH($id);
            if ($validacion !== 0){
                return $validacion;
            }

            $this->conn->begin_transaction();
            try {
                // Comparar unidades solicitadas
                $sql = "SELECT s.unidades_solicitadas,
                               (SELECT COUNT(*) FROM donaciones d
                                 WHERE d.id_solicitud = s.id_solicitud
                                   AND d.estado = 'RESERVADA'
                                   AND d.fecha_caducidad >= CURDATE()) AS reservadas
                        FROM solicitudes s
                        WHERE s.id_solicitud = ?
                        FOR UPDATE";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $fila = $stmt->get_result()->fetch_assoc();

                if ((int)$fila['reservadas'] < (int)$fila['unidades_solicitadas']) {
                    $this->conn->rollback();
                    return 3; // unidades asignadas incompletas
                }

                // Las unidades pasan a UTILIZADA al entregar
                $sql = "UPDATE donaciones SET estado = 'UTILIZADA'
                        WHERE id_solicitud = ? AND estado = 'RESERVADA'";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();

                $fecha = (new DateTime())->format('Y-m-d');
                $sql = "UPDATE solicitudes
                        SET estado = ?, fecha_entrega = ?
                        WHERE id_solicitud = ?
                        AND estado = 'APROBADA'";

                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("ssi", $estado, $fecha, $id);
                $ok = $stmt->execute();

                $this->conn->commit();
                return $ok;
            } catch (Throwable $e) {
                $this->conn->rollback();
                throw $e;
            }
        }

        //validaciones
        function validacionesPATCH($id){
            $stmt = $this->conn->prepare("SELECT estado FROM solicitudes WHERE id_solicitud = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();

            if ($fila !== null && $fila['estado'] === 'ENTREGADA'){
                return 2; // ya entregada
            }
            if ($fila === null || $fila['estado'] !== 'APROBADA'){
                return 1; // no existe o no está aprobada
            }
            return 0;
        }
    }
?>