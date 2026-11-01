<?php

class Donante{
    private mysqli $conn;

    public function __construct(mysqli $dbConnection){
        $this-> conn = $dbConnection;
    }

    /**
     * Crear un nuevo donante o actualizarlo si estaba inactivo
     * @return array ['estado' => creado|duplicado|reactivado|conflicto_medico, 'id_donante' (en creado/reactivado)]
     */
    public function crearDonante(
        int $idTipoSangre,
        string $dui,
        string $nombre,
        string $apellido,
        string $contacto, 
        string $fechaNacimiento, //"YYYY-MM-DD"
        string $sexo //"M" o "F"
        ): array {
            //Verificar duplicidad de DUI
            $stmt = $this->conn->prepare(
                "SELECT id_donante, activo, id_tipo_sangre, fecha_nacimiento, sexo 
                FROM donantes WHERE dui = ?");
            $stmt->bind_param("s",$dui);
            $stmt->execute();
            $existe = $stmt->get_result()->fetch_assoc();

            if ($existe){
                //Registro activo duplicado: terminar proceso
                if ((int)$existe['activo'] === 1) {
                    return ['estado' => 'duplicado'];
                }

                // --- Registro inactivo ---
                // Validar coincidencia de datos medicos
                if ((int)$existe['id_tipo_sangre'] !== $idTipoSangre || $existe['fecha_nacimiento'] !== $fechaNacimiento || $existe['sexo'] !== $sexo){
                    return ['estado' => 'conflicto_medico'];
                }

                // Reactivar y actualizar datos personales
                $idDonante = (int)$existe['id_donante'];
                $stmt = $this->conn->prepare(
                    "UPDATE donantes 
                    SET  nombre = ?, apellido = ?, contacto = ?, activo = 1
                    WHERE id_donante=?"
                );
                $stmt->bind_param("sssi", $nombre, $apellido, $contacto, $idDonante);
                $stmt->execute();

                return ['estado' => 'reactivado', 'id_donante' => $idDonante];

            }

            //DUI nuevo: insertar registro
            $stmt = $this->conn->prepare(
                "INSERT INTO donantes
                (id_tipo_sangre, dui, nombre, apellido, contacto, fecha_nacimiento, sexo)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("issssss", $idTipoSangre, $dui, $nombre, $apellido, $contacto, $fechaNacimiento, $sexo);
            $stmt->execute();

            return ['estado' => 'creado','id_donante' => $this->conn->insert_id];
        }

    /**
     * Consultar donante a partir del id
     * @return array|null datos del donante
     *                    null: el donante no existe o esta inactivo
     */
    public function obtenerDonantePorId(int $idDonante): ?array{
        $stmt = $this->conn->prepare(
                "SELECT id_donante, id_tipo_sangre, dui, nombre, apellido, contacto, fecha_nacimiento, sexo 
                FROM donantes 
                WHERE activo = 1 AND id_donante = ?");
        $stmt->bind_param("i",$idDonante);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Lista donantes activos con opcion de filtros combinables
     * @param string|null $busqueda DUI|nombre|apellido
     * @param int|null    $idTipoSangre tabla de tipos sanguineos
     * @param bool $ordenDesc true: orden descendente, false: asc (por defecto)
     * @return array lista de coincidencias con filtros,
     *               sin filtro devuelve todos los donantes
     */
    public function buscarDonantes(?string $busqueda=null, ?int $idTipoSangre = null, bool $ordenDesc = false): array{
        $sql = "SELECT id_donante, id_tipo_sangre, dui, nombre, apellido, contacto, fecha_nacimiento, sexo 
        FROM donantes 
        WHERE activo = 1"; 
        $tipos = "";
        $params = [];

        if ($busqueda !== null){
            $busqueda = addcslashes($busqueda, '%_\\'); //Evitar caracteres especiales
            $termino = "%".$busqueda."%"; //Contiene la busqueda en cualquier posicion

            $sql .= " AND (dui LIKE ? OR nombre LIKE ? OR apellido LIKE ?)";
            $tipos .= "sss";
            array_push($params, $termino, $termino, $termino);
        }
        
        if ($idTipoSangre !== null){
            $sql .= " AND id_tipo_sangre = ?";
            $tipos .= "i";
            $params[] = $idTipoSangre;
        }

        $orden = $ordenDesc ? "DESC" : "ASC";
        $sql .= " ORDER BY apellido $orden, nombre $orden, id_donante $orden";

        $stmt = $this->conn->prepare($sql);
        if ($params){
            $stmt->bind_param($tipos, ...$params);
        }
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Actualizar informacion de donantes activos
     * @return bool|null true: se actualizo, 
     *                   false: donante inactivo o inexistente,
     *                   null: el DUI pertenece a otro donante
     */
    public function actualizarDonante(
        int $idDonante, 
        int $idTipoSangre,
        string $dui,
        string $nombre,
        string $apellido,
        string $contacto, 
        string $fechaNacimiento, //"YYYY-MM-DD"
        string $sexo //"M" o "F"
        ): ?bool {

            //Validar si el donante existe y esta activo
            if (!$this->donanteExiste($idDonante, true)){
                return false;
            }

            //Comprobar que el DUI no pertenezca a otro donante
            $stmt = $this->conn->prepare(
                "SELECT id_donante 
                FROM donantes 
                WHERE dui = ? 
                AND id_donante <> ?");
            $stmt->bind_param("si", $dui, $idDonante);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return null;
            }

            $stmt = $this->conn->prepare(
                "UPDATE donantes 
                SET id_tipo_sangre = ?, dui = ?, nombre = ?, apellido = ?, contacto = ?, fecha_nacimiento = ?, sexo = ?
                WHERE id_donante=?"
            );
            $stmt->bind_param("issssssi", $idTipoSangre, $dui, $nombre, $apellido, $contacto, $fechaNacimiento, $sexo, $idDonante);
            $stmt->execute();

            return true;
        }

    /**
     * Valida si existe un donante con ese ID
     * @param bool $soloActivos true: ignora donantes inactivos
     * @return bool true: el donante existe, 
     *              false: no existe
     */
    private function donanteExiste(int $idDonante, bool $soloActivos = false) : bool{
        $sql = "SELECT 1 FROM donantes WHERE id_donante = ?";
        if ($soloActivos){
            $sql .= " AND activo=1";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $idDonante);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Cambiar estado del registro de un donante
     * @return bool true: Se actualizo el estado, 
     *              false: El donante no existe
     */
    public function cambiarEstadoDonante(int $idDonante, bool $activo): bool {
        //Validar existencia del donante
        if (!$this->donanteExiste($idDonante)){
            return false;
        }

        $valor = (int)$activo; // convertir true -> 1, false -> 0

        $stmt = $this->conn->prepare(
            "UPDATE donantes SET activo = ? WHERE id_donante=?"
        );
        $stmt->bind_param("ii", $valor, $idDonante);
        $stmt->execute();

        return true;
    }

}
