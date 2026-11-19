<?php
class UsuarioController implements Controlador
{
    public function __construct(
        private Usuario $modelo
    ){}

    public function control(string $metodo, array $partes): void
    {
        requerirRol([ROL_ADMIN]); // Solo administrador

        $id = $partes[1] ?? null;

        if (isset($partes[2])){ noEncontrado(); }

        // Ruta /usuarios
        if ($id === null){
            match ($metodo) {
                'GET'   => $this->listarUsuarios(),
                'POST'  => $this->crearUsuario(),
                default => noPermitido(),
            };
            return;
        }

        // Ruta /usuarios/{id}
        $idUsuario = validarId($id, 'ID de usuario inválido');
        match ($metodo) {
            'PATCH' => $this->actualizarUsuario($idUsuario),
            default => noPermitido(),
        };
    }

    /* --- PETICIONES --- */
    // GET /usuarios?q=&id_rol=&activo=
    private function listarUsuarios(): void
    {
        // Busqueda por nombre de usuario o correo
        $texto = null;
        if (isset($_GET['q'])) {
            $texto = is_string($_GET['q']) ? trim($_GET['q']) : '';
            if ($texto === '' || mb_strlen($texto) > 100) {
                respuesta(400, ['success' => false, 'mensaje' => 'Búsqueda inválida (1 a 100 caracteres)']);
            }
        }

        // Filtro por rol
        $idRol = isset($_GET['id_rol'])
            ? validarId($_GET['id_rol'], 'ID de rol inválido')
            : null;

        // Filtro por estado (1 activo, 0 inactivo)
        $activo = null;
        if (isset($_GET['activo'])) {
            if (!in_array($_GET['activo'], ['0', '1'], true)) {
                respuesta(400, ['success' => false, 'mensaje' => 'activo debe ser 0 o 1']);
            }
            $activo = $_GET['activo'] === '1';
        }

        $usuarios = $this->modelo->buscarUsuarios($texto, $idRol, $activo);
        respuesta(200, ['success' => true, 'usuarios' => $usuarios]);
    }

    // POST /usuarios
    private function crearUsuario(): void
    {
        $data = leerJson();
        $requeridos = ['nombre', 'apellido', 'correo', 'id_rol'];
            
        /* --- Validaciones --- */
        // Campos obligatorios vacios
        validarCamposRequeridos($data, $requeridos);

        // Validar que solo utilice letras
        $nombre   = $this->validarNombre($data['nombre'], 'nombre');
        $apellido = $this->validarNombre($data['apellido'], 'apellido');
        $correo   = is_string($data['correo']) ? trim($data['correo']) : '';


        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 100) {
            respuesta(400, ['success' => false, 'mensaje' => 'Correo inválido']);
        }
        $idRol = validarId($data['id_rol'], 'ID de rol inválido');

        $res = $this->modelo->crearUsuario($nombre, $apellido, $correo, $idRol);

        switch ($res['estado']) {
            case 'rol_no_existe':
                respuesta(404, ['success' => false, 'mensaje' => 'Rol no encontrado o no permitido']);
            case 'nombre_invalido':
                respuesta(400, ['success' => false, 'mensaje' => 'Nombre o apellido sin caracteres válidos']);
            case 'correo_duplicado':
                respuesta(409, ['success' => false, 'mensaje' => 'El correo ya está registrado']);
            case 'usuario_no_generado':
                respuesta(500, ['success' => false, 'mensaje' => 'No se pudo generar un nombre de usuario único']);
            default:
                respuesta(201, [
                    'success'             => true,
                    'id_usuario'          => $res['id_usuario'],
                    'nombre_usuario'      => $res['nombre_usuario'],
                    'contrasena_temporal' => $res['contrasena_temporal'],
                    'mensaje'             => 'Usuario creado, debe cambiar su contraseña en el primer inicio de sesión'
                ]);
        }
    }

    // PATCH /usuarios/{id}
    private function actualizarUsuario(int $idUsuario): void
    {
        $data = leerJson();
        $cambios = [];

        if (array_key_exists('correo', $data)) {
            $correo = is_string($data['correo']) ? trim($data['correo']) : '';
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 100) {
                respuesta(400, ['success' => false, 'mensaje' => 'Correo inválido']);
            }
            $cambios['correo'] = $correo;
        }
        if (array_key_exists('id_rol', $data)) {
            $cambios['id_rol'] = validarId($data['id_rol'], 'ID de rol inválido');
        }
        if (array_key_exists('activo', $data)) {
            if (!is_bool($data['activo'])) {
                respuesta(400, ['success' => false, 'mensaje' => 'activo debe ser true o false']);
            }
            $cambios['activo'] = $data['activo'];
        }

        if (!$cambios) {
            respuesta(400, ['success' => false, 'mensaje' => 'No hay campos para actualizar']);
        }

        // Un administrador no puede desactivarse ni cambiarse el rol a sí mismo
        if ($idUsuario === usuarioActual()
            && (isset($cambios['id_rol']) || ($cambios['activo'] ?? true) === false)) {
            respuesta(409, ['success' => false, 'mensaje' => 'No puede desactivar su cuenta ni cambiar su propio rol']);
        }

        $res = $this->modelo->actualizarUsuario($idUsuario, $cambios);

        switch ($res) {
            case 'usuario_no_existe':
                respuesta(404, ['success' => false, 'mensaje' => 'Usuario no encontrado']);
            case 'rol_no_existe':
                respuesta(404, ['success' => false, 'mensaje' => 'Rol no encontrado o no permitido']);
            case 'correo_duplicado':
                respuesta(409, ['success' => false, 'mensaje' => 'El correo ya está registrado']);
            default:
                respuesta(200, ['success' => true, 'mensaje' => 'Usuario actualizado']);
        }
    }

    /* --- UTILIDADES --- */
    // Validaciones
    private function validarNombre(mixed $valor, string $campo): string
    {
        $valor = is_string($valor) ? trim(preg_replace('/\s+/', ' ', $valor)) : '';
        if (mb_strlen($valor) < 2 || mb_strlen($valor) > 50 || !preg_match('/^[\p{L} ]+$/u', $valor)) {
            respuesta(400, ['success' => false, 'mensaje' => "$campo inválido, use solo letras (2 a 50 caracteres)"]);
        }
        return $valor;
    }
}