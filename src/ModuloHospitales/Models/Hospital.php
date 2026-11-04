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
            $sql = "SELECT id_hospital, id_distrito, nombre_hospital, direccion FROM hospitales WHERE activo = 1";
            $hospitales = $this->conn->query($sql);
            return $hospitales->fetch_all(MYSQLI_ASSOC);
        }

        //---Busqueda por ID---
        public function buscarPorID($id){
            $sql = ("SELECT id_hospital, id_distrito, nombre_hospital, direccion FROM  hospitales WHERE id_hospital = ? AND activo=1");
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            
            if($stmt->execute()){
                return $stmt->get_result()->fetch_assoc(); 
            }
        }

        //---Busqueda mediante filtros---
        public function buscarPorFiltros($nombre, $departamento, $municipio, $distrito, $orden){

            $parametros = [];
            $tipoParametro = "";
            $select = "";
            $inner = [];
            $where = "";

            //por distrito
            if ($distrito !== ''){
                if(!$this->distritoExiste($distrito)){return 3;}
                $select .= " ,d.id_distrito";
                $inner[] = " INNER JOIN distritos d ON d.id_distrito = h.id_distrito";
                $where .= " AND (h.id_distrito = ?)";
                $parametros[] = $distrito;
                $tipoParametro .= "i";
            }
            
            //por municipio
            if ($municipio !== ''){
                if(!$this->municipioExiste($municipio)){return 2;}
                //si distrito no se ha seleccionado
                if($distrito === ''){
                    $inner[] = " INNER JOIN distritos d ON d.id_distrito = h.id_distrito";
                }

                $select .= " ,m.id_municipio";
                $inner[] = " INNER JOIN municipios m ON m.id_municipio = d.id_municipio";
                $where .= " AND (d.id_municipio = ?)";
                $parametros[] = $municipio;
                $tipoParametro .= "i";
            }

            //por departamento
            if ($departamento !== ''){
                if(!$this->departamentoExiste($departamento)){return 1;}
                //si municipio no se ha seleccionado
                if($municipio === ''){
                    //si distrito no se ha seleccionado
                    if($distrito === ''){
                        $inner[] = " INNER JOIN distritos d ON d.id_distrito = h.id_distrito";
                    }
                    $inner[] = " INNER JOIN municipios m ON m.id_municipio = d.id_municipio";
                }
                $select .= " ,e.id_departamento";
                $inner[] = " INNER JOIN departamentos e ON e.id_departamento = m.id_departamento";
                $where .= " AND (m.id_departamento = ?)";
                $parametros[] = $departamento;
                $tipoParametro .= "i";
            }
            
            $sql = "SELECT h.id_hospital, h.id_distrito, h.nombre_hospital, h.direccion";

            //Si se ingreso un distrito, municipio o departamento
            if($select !== ''){
                $sql .= $select . " FROM hospitales h ";
                $sql .= implode(" ", $inner);
                $sql .= " WHERE h.activo = 1";
                $sql .= $where;
            }
            else{
                $sql .= " FROM hospitales h WHERE activo = 1 ";
            }

            //buscamos por nombre
            if ($nombre !== ''){
                $sql .= " AND h.nombre_hospital LIKE ?";
                $parametros[] = "%".$nombre."%";
                $tipoParametro .= "s";
            }

            //ordenar de forma
            if ($orden === 'ASC'){
                $sql .= " ORDER BY h.nombre_hospital ASC";
            }
            if($orden === 'DESC'){
                $sql .= " ORDER BY h.nombre_hospital DESC";
            }

            //ejecutamos
            $stmt = $this->conn->prepare($sql);
            if (!empty($parametros)) {
                $stmt->bind_param($tipoParametro, ...$parametros);
            }
            $stmt->execute();

            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        //validaciones
        function departamentoExiste($id){
            $sql = "SELECT * FROM departamentos WHERE id_departamento = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                return false;
            }
            return true;
        }

        function municipioExiste($id){
            $sql = "SELECT * FROM municipios WHERE id_municipio = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                return false;
            }
            return true;
        }

        function distritoExiste($id){
            $sql = "SELECT * FROM distritos WHERE id_distrito = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                return false;
            }
            return true;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== POST ==========

        //--Agregar nuevo hospital---
        public function agregarNuevoHospital($nombre, $direccion, $distrito){
            $validacion = $this->validacionDuplicadoHospital($nombre, $distrito);
            //Si no existe podemos ingresar uno nuevo
            if($validacion !== 0){
                return $validacion;
            }
            $sql = "INSERT INTO hospitales 
            (nombre_hospital, direccion, id_distrito) VALUES (?,?,?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssi", $nombre, $direccion, $distrito); 
            $stmt->execute();

            return 0;
        }

        //Funcion que valida si ya esta un hopital con el mismo nombre en el mismo municipio en la BD
        public function validacionDuplicadoHospital($nombre, $distrito){
        //si ya existe el hospital
            $sql = "SELECT id_hospital FROM hospitales WHERE nombre_hospital = ? AND id_distrito = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("si", $nombre, $distrito); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return 1;
            }
            //Si el distrito es valido
            $sql = "SELECT id_distrito FROM distritos WHERE id_distrito = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $distrito); 
            $stmt->execute();
            if($stmt->get_result()->num_rows === 0){
                return 2;
            }
            return 0;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== PUT ==========

        //--- Modificar un hospital ---
        public function actualizarHospital($id, $nombre, $direccion, $distrito){
            $validacion = $this->validacionesPUT($id,$distrito);
            
            if($validacion !== 0){
                return $validacion;
            }
            $sql = "UPDATE hospitales SET nombre_hospital = ?, direccion = ?, id_distrito = ? WHERE id_hospital = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssii", $nombre, $direccion, $distrito, $id); 
            $stmt->execute();

            return 0;
        }

        //validaciones
        public function validacionesPUT($id, $distrito){
            //Si no existe la id 
            $sql = "SELECT * FROM hospitales WHERE id_hospital = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows <= 0) {
                return 1;
            }
            //si no existe el distrito
            $sql = "SELECT * FROM distritos WHERE id_distrito = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $distrito); 
            $stmt->execute();
            if ($stmt->get_result()->num_rows <= 0) {
                return 2;
            }
            return 0;
        }

        //----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
        //========== DELETE ==========

        public function eliminar($id){
            //Si la id no existe o fue ilimnada de la DB
            if($this->validacionEliminar($id) === false){
                return false;
            }

            $sql = "UPDATE hospitales SET activo = 0  WHERE id_hospital = ?";
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
