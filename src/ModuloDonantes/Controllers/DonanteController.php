<?php
class DonanteController implements Controlador
{
    public function __construct(
        private Donante $modelo,
        private EvaluacionController $evaluaciones
    ){}

    public function control(string $metodo, array $partes): void
    {
        // Parametros de las peticiones
        $id = $partes[1] ?? null;
        $sub = $partes[2] ?? null;

        // Ruta /donantes
        if ($id === null){
            match ($metodo) {
                'GET'   => $this->listarTodos(),
                'POST'  => $this->crearDonante(),
                default => noPermitido(),
            };
            return;
        }

        $idDonante = validarId($id, 'ID de donante inválido');

        // Ruta /donantes/{id}/evaluaciones
        if ($sub === 'evaluaciones'){
            if (isset($partes[3])){
                noEncontrado();
            }
            // Manda el {id} a EvaluacionController
            $this->evaluaciones->control($metodo,$idDonante);
            return;
        }

        if ($sub !== null){ noEncontrado(); }

        // Ruta /donantes/{id}
        match ($metodo) {
            'GET'   => $this->obtenerDonante($idDonante),
            'PUT'   => $this->actualizarDonante($idDonante),
            'PATCH' => $this->cambiarEstado($idDonante),
            default => noPermitido(),
        };
    }

    /* --- PETICIONES --- */
    // GET /donantes
    private function listarTodos(): void 
    {
        /* -- Validacion de filtros -- */
        // Busqueda por texto
        $busqueda = null;   
        if (isset($_GET['busqueda'])) {
            $busqueda =  is_string($_GET['busqueda']) ? trim($_GET['busqueda']) : ''; 
            
            if ($busqueda === '') {
                respuesta(400, ['success' => false, 'mensaje' => 'La búsqueda no puede estar vacía']);
            }
        }

        // Filtro por tipo sanguineo
        $idTipoSangre = null;
        if (isset($_GET['id_tipo_sangre'])) {
            $idTipoSangre = validarId($_GET['id_tipo_sangre'], 'Tipo de sangre inválido');
        }

        // Ordenar por apellido ASC/DESC
        $ordenDesc = false;
        if (isset($_GET['orden'])) {
            $orden = is_string($_GET['orden']) ? strtolower($_GET['orden']) : '';
            if (!in_array($orden, ['asc', 'desc'], true)) {
                respuesta(400, ['success' => false, 'mensaje' => 'Orden debe ser asc o desc']);
            }
            $ordenDesc = ($orden === 'desc');
        }

        /* Resultado: Listar donantes que coincidan */
        $donantes = $this->modelo->buscarDonantes($busqueda, $idTipoSangre, $ordenDesc); 
        respuesta(200, ['success' => true, 'donantes' => $donantes]);
    }

    // GET /donantes/{id}
    private function obtenerDonante(int $idDonante): void 
    {
        $donante = $this->modelo->obtenerDonantePorId($idDonante);
        if ($donante === null) {
            respuesta(404, ['success' => false, 'mensaje' => 'Donante no encontrado']);
        }
        respuesta(200, ['success' => true, 'donante' => $donante]);
    }

    // POST /donantes
    private function crearDonante(): void  
    {
        $data = $this->validarDonante(leerJson());

        //Insertar datos
        $res = $this->modelo->crearDonante(
            $data['id_tipo_sangre'],
            $data['dui'],
            $data['nombre'],
            $data['apellido'],
            $data['contacto'],
            $data['fecha_nacimiento'],
            $data['sexo']
        );

        switch ($res['estado']) {
            case 'duplicado':
                respuesta(409, ['success' => false, 'mensaje' => 'El DUI ya está registrado']);
            case 'conflicto_medico':
                respuesta(409, ['success' => false, 'mensaje' => 'El DUI pertenece a donante inactivo y sus datos no coinciden. Verifique la informacion.']);
            case 'reactivado':
                respuesta(200, ['success' => true, 'id_donante' => $res['id_donante'], 'mensaje' => 'Donante reactivado']);
            default:
                respuesta(201, ['success' => true, 'id_donante' => $res['id_donante']]);
        }
    }

    // PUT /donantes/{id}
    private function actualizarDonante(int $idDonante): void 
    {
        $data = $this->validarDonante(leerJson());

        //Insertar datos actualizados
        $res = $this->modelo->actualizarDonante(
            $idDonante,
            $data['id_tipo_sangre'],
            $data['dui'],
            $data['nombre'],
            $data['apellido'],
            $data['contacto'],
            $data['fecha_nacimiento'],
            $data['sexo']
        );
        
        if ($res === false){
            respuesta(404, ['success' => false, 'mensaje' => 'Donante no encontrado o inactivo.']);
        }
        if ($res === null){
            respuesta(409, ['success' => false, 'mensaje' => 'El DUI pertenece a otro donante.']);
        }
        respuesta(200, ['success' => true, 'donante' => $this->modelo->obtenerDonantePorId($idDonante), 'mensaje' => 'Registro actualizado.']);
    }
    
    // PATCH /donantes/{id}     body: {'activo': true|false}
    private function cambiarEstado(int $idDonante) : void
    {
        $data = leerJson();
        
        //Validar el campo activo
        if (!array_key_exists('activo', $data)) {
            respuesta(400, ['success' => false, 'mensaje' => 'Falta el campo activo']);
        }

        $activo = filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($activo === null) {
            respuesta(400, ['success' => false, 'mensaje' => 'Estado activo debe ser true o false']);
        }

        if (!$this->modelo->cambiarEstadoDonante($idDonante, $activo)) {
            respuesta(404, ['success' => false, 'mensaje' => 'Donante no encontrado']);
        }

        respuesta(200, ['success' => true, 'mensaje' => $activo ? 'Donante activo' : 'Donante inactivo']);
    }

    /* --- UTILIDADES --- */
    // Valida los parametros del donante
    private function validarDonante(array $data): array
    {
        $requeridos = ['id_tipo_sangre', 'dui', 'nombre', 'apellido', 'contacto', 'fecha_nacimiento', 'sexo'];
            
        /* --- Validaciones --- */
        // Campos obligatorios vacios
        $vacios = [];
        foreach ($requeridos as $campo){
            if (!isset($data[$campo]) || !is_scalar($data[$campo]) || trim((string)$data[$campo]) === ''){
                $vacios[] = $campo;
            }
        }

        if ($vacios){
            respuesta(400, ['success' => false, 'mensaje' => 'Campos vacios', 'campos' => $vacios]);
        }
        
        // Formatos
        $idTipoSangre = filter_var($data['id_tipo_sangre'], FILTER_VALIDATE_INT);
        if ($idTipoSangre === false || $idTipoSangre <=0){
                respuesta(400, ['success' => false, 'mensaje' => 'Tipo de sangre inválido']);
        }

        $dui = trim((string)$data['dui']);
        if (!preg_match('/^\d{8}-\d$/', $dui)) {
            respuesta(400, ['success' => false, 'mensaje' => 'DUI inválido, use ########-#']);
        }

        $contacto = trim((string)$data['contacto']);
        if (!preg_match('/^\d{4}-\d{4}$/', $contacto)) {
            respuesta(400, ['success' => false, 'mensaje' => 'Numero inválido, use ####-####']);
        }

        if (!in_array($data['sexo'], ['M', 'F'], true)) {
            respuesta(400, ['success' => false, 'mensaje' => 'Sexo debe ser M o F']);
        }

        $fechaNacimiento = (string)$data['fecha_nacimiento'];
        $fecha = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
        if (!$fecha || $fecha->format('Y-m-d') !== $fechaNacimiento || $fecha > new DateTime('today')) {
            respuesta(400, ['success' => false, 'mensaje' => 'Fecha inválida, utilice formato YYYY-MM-DD']);
        }

        return [
            'id_tipo_sangre'    => $idTipoSangre,
            'dui'               => $dui,
            'nombre'            => trim((string)$data['nombre']),
            'apellido'          => trim((string)$data['apellido']),
            'contacto'          => $contacto,
            'fecha_nacimiento'  => $fechaNacimiento,
            'sexo'              => $data['sexo'],
        ]; 
    }
}

