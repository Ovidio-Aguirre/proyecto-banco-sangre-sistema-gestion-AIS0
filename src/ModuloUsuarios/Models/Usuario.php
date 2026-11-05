<?php

class Usuario{
    private mysqli $conn;
    private const MAX_CORRELATIVO = 20; // Limite al generar nombres de usuario iguales (base, base2, base3...)
    private const ALFABETO = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Caracteres utilizables en la contrasena temporal

    public function __construct(mysqli $dbConnection){
        $this-> conn = $dbConnection;
    }

    /**
     * Consultar usuario activo
     * @return array|null Datos del usuario, 
     *                    null: no existe o inactivo
     */
    public function consultarUsuario(string $identificador): ? array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id_usuario, u.nombre_usuario, u.nombre, u.apellido, u.correo,
                    u.contrasena, u.debe_cambiar_contrasena, r.rol AS rol
            FROM usuarios u
            JOIN roles r ON r.id_rol = u.id_rol
            WHERE (u.nombre_usuario = ? OR u.correo = ?) AND u.activo = 1"
        );
        $stmt->bind_param("ss", $identificador, $identificador);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Obtener datos publicos del usuario
     * @return array|null Datos del usuario, 
     *                    null: no existe o inactivo
     */
    public function obtenerUsuarioPorId(int $idUsuario): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id_usuario, u.nombre_usuario, u.nombre, u.apellido, u.correo,
                    u.debe_cambiar_contrasena, r.rol AS rol
            FROM usuarios u
            JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.id_usuario = ? AND u.activo = 1"
        );
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Lista de usuarios con filtros combinables
     * @param string|null $busqueda nombre_usuario|correo
     * @param int|null    $idRol tabla de roles
     * @param bool|null   $activo  null: todos
     * @return array lista de coincidencias con filtros,
     *               sin filtro devuelve todos los usuarios
     */
    public function buscarUsuarios(
        ?string $busqueda = null,
        ?int $idRol = null,
        ?bool $activo = null
    ): array
    {
        $sql = "SELECT u.id_usuario, u.id_rol, u.nombre, u.apellido, u.nombre_usuario,
                    u.correo, u.activo, r.rol AS rol
                FROM usuarios u
                JOIN roles r ON r.id_rol = u.id_rol
                WHERE 1 = 1";
        $tipos  = "";
        $params = [];

        if ($busqueda !== null) {
            $sql .= " AND (u.nombre_usuario LIKE ? OR u.correo LIKE ?)";
            $patron = '%' . addcslashes($busqueda, '%_\\') . '%';
            $tipos .= "ss";
            array_push($params, $patron, $patron);
        }

        if ($idRol !== null) {
            $sql .= " AND u.id_rol = ?";
            $tipos .= "i";
            $params[] = $idRol;
        }

        if ($activo !== null) {
            $sql .= " AND u.activo = ?";
            $tipos .= "i";
            $params[] = (int)$activo;
        }

        $sql .= " ORDER BY u.apellido ASC, u.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        if ($params) {
            $stmt->bind_param($tipos, ...$params);
        }
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Actualizar datos permitidos del usuario
     * @param array $cambios ['correo' => string, 'id_rol' => int, 'activo' => bool]
     * @return string actualizado|usuario_no_existe|rol_no_existe|correo_duplicado
     */
    public function actualizarUsuario(int $idUsuario, array $cambios): string 
    {
        $stmt = $this->conn->prepare("SELECT 1 FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            return 'usuario_no_existe';
        }

        // Validar el rol
        if (isset($cambios['id_rol'])) {
            $stmt = $this->conn->prepare("SELECT rol FROM roles WHERE id_rol = ?");
            $stmt->bind_param("i", $cambios['id_rol']);
            $stmt->execute();
            $rol = $stmt->get_result()->fetch_assoc();
            if ($rol === null || !in_array($rol['rol'], [ROL_ADMIN, ROL_MEDICO], true)) {
                return 'rol_no_existe';
            }
        }

        $sets   = [];
        $tipos  = "";
        $params = [];

        if (isset($cambios['correo'])) {
            $sets[] = "correo = ?";
            $tipos .= "s";
            $params[] = $cambios['correo'];
        }
        if (isset($cambios['id_rol'])) {
            $sets[] = "id_rol = ?";
            $tipos .= "i";
            $params[] = $cambios['id_rol'];
        }
        if (isset($cambios['activo'])) {
            $sets[] = "activo = ?";
            $tipos .= "i";
            $params[] = (int)$cambios['activo'];
        }

        $tipos .= "i";
        $params[] = $idUsuario;

        try {
            $stmt = $this->conn->prepare(
                "UPDATE usuarios SET " . implode(", ", $sets) . " WHERE id_usuario = ?"
            );
            $stmt->bind_param($tipos, ...$params);
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                return 'correo_duplicado';
            }
            throw $e;
        }

        return 'actualizado';
    }

    /** 
     * Obtener el hash de contraseña de usuario activo
     * @return string|null Devuelve hash contrasena, 
     *                     null: no existe o inactivo
     */ 
    public function obtenerHash(int $idUsuario): ?string
    {
        $stmt = $this->conn->prepare(
            "SELECT contrasena FROM usuarios WHERE id_usuario = ? AND activo = 1"
        );
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        return $fila['contrasena'] ?? null;
    }

    /**
     * Actualizar contrasena temporal
     * @return void Inserta la nueva contrasena hasheada
     */
    public function cambiarContrasena(int $idUsuario, string $hash): void 
    {
        $stmt = $this->conn->prepare(
            "UPDATE usuarios SET contrasena = ?, debe_cambiar_contrasena = 0
            WHERE id_usuario = ?"
        );
        $stmt->bind_param("si", $hash, $idUsuario);
        $stmt->execute();
    }

    /**
     * Registrar nuevo usuario
     * @return array ['estado'=> creado|rol_no_existe|nombre_invalido
     *               |correo_duplicado|usuario_no_generado,
     *               'id_usuario', 'nombre_usuario', 'contrasena_temporal']
     */
    public function crearUsuario(
        string $nombre,
        string $apellido,
        string $correo,
        int $idRol
    ): array 
    {
        /* --- Validaciones --- */
        // Rol existe
        $stmt = $this->conn->prepare("SELECT rol FROM roles WHERE id_rol = ?");
        $stmt->bind_param("i", $idRol);
        $stmt->execute();
        $rol = $stmt->get_result()->fetch_assoc();

        // Rol esta en los valores permitidos
        if ($rol === null || !in_array($rol['rol'], [ROL_ADMIN, ROL_MEDICO], true)) {
            return ['estado' => 'rol_no_existe'];
        }

        /* --- Generar creedenciales --- */
        // Crear nombre de usuario
        $base = $this->generarBase($nombre, $apellido);
        if ($base === null) {
            return ['estado' => 'nombre_invalido'];
        }

        // Crear contrasena temporal
        $temporal = $this->generarContrasenaTemporal();
        $hash     = password_hash($temporal, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios
            (id_rol, nombre, apellido, nombre_usuario, contrasena, correo, debe_cambiar_contrasena)
            VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $nombreUsuario = '';
        $stmt->bind_param("isssss", $idRol, $nombre, $apellido, $nombreUsuario, $hash, $correo);

        /**
         * Valida que las creedenciales sean unicas
         * Si se repite el nombre de usuario, intenta con el siguiente correlativo
         */
        for ($i = 1; $i <= self::MAX_CORRELATIVO; $i++) {
            $nombreUsuario = $i === 1 ? $base : $base . $i;
            try {
                $stmt->execute();
                return [
                    'estado'              => 'creado',
                    'id_usuario'          => $this->conn->insert_id,
                    'nombre_usuario'      => $nombreUsuario,
                    'contrasena_temporal' => $temporal,
                ];
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062) {
                    throw $e;
                }
                // Correo repetido: reintentar no lo arregla
                if (str_contains($e->getMessage(), 'uq_usuarios_correo')) {
                    return ['estado' => 'correo_duplicado'];
                }
            }
        }
        return ['estado' => 'usuario_no_generado'];
    }

    /** 
     * Generar la base del nombre de usuario: inicial nombre + apellido
     * @return string|null Base del nombre,
     *                     null: no queda ningún carácter válido
    */
    private function generarBase(string $nombre, string $apellido): ?string
    {
        $limpiar = fn(string $t): string => preg_replace(
            '/[^a-z0-9]/',
            '',
            strtr(mb_strtolower($t, 'UTF-8'), [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                'ü' => 'u', 'ñ' => 'n',
            ])
        ) ?? '';

        $inicial = substr($limpiar($nombre), 0, 1);
        $ape     = substr($limpiar($apellido), 0, 40);

        return ($inicial === '' || $ape === '') ? null : $inicial . $ape;
    }

    /**
     * Generar contraseña aleatoria de 10 caracteres
     * @return string Contrasena temporal
     */
    private function generarContrasenaTemporal(): string
    {
        $contrasena = '';
        $max = strlen(self::ALFABETO) - 1;
        for ($i = 0; $i < 10; $i++) {
            $contrasena .= self::ALFABETO[random_int(0, $max)];
        }
        return $contrasena;
    }
}