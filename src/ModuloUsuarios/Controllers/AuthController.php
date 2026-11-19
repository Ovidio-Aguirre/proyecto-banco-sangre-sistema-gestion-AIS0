<?php
class AuthController implements Controlador
{
    public function __construct(
        private Usuario $modelo
    ){}

    public function control(string $metodo, array $partes): void
    {
        $sub = $partes[1] ?? null;

        if (isset($partes[2])){ noEncontrado(); }

        // Ruta /auth
        match ($sub) {
            'login'              => $metodo === 'POST' ? $this->login() : noPermitido(),
            'logout'             => $metodo === 'POST' ? $this->logout() : noPermitido(),
            'perfil'             => $metodo === 'GET' ? $this->perfil() : noPermitido(),
            'cambiar-contrasena' => $metodo === 'POST' ? $this->cambiarContrasena() : noPermitido(),
            default              => noEncontrado(),
        };
    }

    /* --- PETICIONES --- */
    // POST /auth/login
    private function login(): void
    {
        $data = leerJson();
            
        /* --- Validaciones --- */
        // Campos obligatorios vacios
        validarCamposRequeridos($data, ['usuario', 'contrasena']);

        // Validar formato string
        if (!is_string($data['usuario']) || !is_string($data['contrasena'])) {
            respuesta(400, ['success' => false, 'mensaje' => 'Usuario y contrasena deben ser texto']);
        }

        $usuario = $this->modelo->consultarUsuario(trim($data['usuario']));

        // Validar coincidencia de contrasena en la base 
        if ($usuario === null || !password_verify($data['contrasena'], $usuario['contrasena'])) {
            respuesta(401, ['success' => false, 'mensaje' => 'Credenciales inválidas']);
        }

        session_regenerate_id(true); // Evitar fijación de sesión
        $_SESSION['id_usuario']   = (int)$usuario['id_usuario'];
        $_SESSION['nombre_completo'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
        $_SESSION['rol']          = $usuario['rol'];
        $_SESSION['debe_cambiar'] = (int)$usuario['debe_cambiar_contrasena'] === 1;

        respuesta(200, [
            'success' => true,
            'usuario' => [
                'id_usuario'              => (int)$usuario['id_usuario'],
                'nombre_usuario'          => $usuario['nombre_usuario'],
                'nombre'                  => $usuario['nombre'],
                'apellido'                => $usuario['apellido'],
                'rol'                     => $usuario['rol'],
                'debe_cambiar_contrasena' => $_SESSION['debe_cambiar'],
            ],
            'mensaje' => 'Sesión iniciada'
        ]);
    }

    // POST /auth/logout
    private function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        respuesta(200, ['success' => true, 'mensaje' => 'Sesión cerrada']);
    }

    // GET /auth/perfil
    private function perfil(): void
    {
        $usuario = $this->modelo->obtenerUsuarioPorId(usuarioActual());
        if ($usuario === null) {
            // Usuario desactivado o eliminado: invalida la sesión
            $_SESSION = [];
            session_destroy();
            respuesta(401, ['success' => false, 'mensaje' => 'Sesión inválida']);
        }
        respuesta(200, ['success' => true, 'usuario' => $usuario]);
    }

    // POST /auth/cambiar-contrasena
    private function cambiarContrasena(): void
    {
        $data = leerJson();

        /* --- Validaciones --- */
        // Campos obligatorios vacios
        validarCamposRequeridos($data, ['contrasena_actual', 'contrasena_nueva']);

        // Validar formato string
        if (!is_string($data['contrasena_actual']) || !is_string($data['contrasena_nueva'])) {
            respuesta(400, ['success' => false, 'mensaje' => 'Las contrasenas deben ser texto']);
        }
        if (strlen($data['contrasena_nueva']) < 8) {
            respuesta(400, ['success' => false, 'mensaje' => 'La contraseña nueva debe tener al menos 8 caracteres']);
        }
        if ($data['contrasena_nueva'] === $data['contrasena_actual']) {
            respuesta(400, ['success' => false, 'mensaje' => 'La contraseña nueva debe ser distinta a la actual']);
        }

        $idUsuario = usuarioActual();
        $hash = $this->modelo->obtenerHash($idUsuario);

        if ($hash === null || !password_verify($data['contrasena_actual'], $hash)) {
            respuesta(400, ['success' => false, 'mensaje' => 'La contraseña actual es incorrecta']);
        }

        $this->modelo->cambiarContrasena($idUsuario, password_hash($data['contrasena_nueva'], PASSWORD_DEFAULT));

        $_SESSION['debe_cambiar'] = false;
        session_regenerate_id(true);

        respuesta(200, ['success' => true, 'mensaje' => 'Contraseña actualizada']);
    }
}