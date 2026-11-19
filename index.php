<?php
require_once __DIR__ . '/src/Shared/helpers/utilidades.php'; // ROL_ADMIN, ROL_MEDICO

// Misma cookie de sesión que la API
session_set_cookie_params([
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$page = $_GET['page'] ?? 'dashboard';

$pages = [
    'dashboard'  => [
        'title' => 'Dashboard',
        'view'  => '/src/ModuloReportes/Views/dashboard.html'
    ],
    'donantes'   => [
        'title' => 'Donantes',
        'view'  => '/src/ModuloDonantes/Views/donantes.html'
    ],
    'donaciones' => [
        'title' => 'Donaciones',
        'view'  => '/src/ModuloDonaciones/Views/donaciones.html'
    ],
    'inventario' => [
        'title' => 'Inventario',
        'view'  => '/src/ModuloDonaciones/Views/inventario.html'
    ],
    'solicitudes' => [
        'title' => 'Solicitudes',
        'view'  => '/src/ModuloSolicitudes/Views/solicitudes.html'
    ],
    'hospitales' => [
        'title' => 'Hospitales',
        'view'  => '/src/ModuloHospitales/Views/hospitales.html'
    ],
    'reportes' => [
        'title' => 'Reportes',
        'view'  => '/src/ModuloReportes/Views/reportes.html'
    ],
    'usuarios' => [
        'title' => 'Usuarios',
        'view'  => '/src/ModuloUsuarios/Views/usuarios.html',
        'roles' => [ROL_ADMIN]
    ],
    'perfil' => [
        'title' => 'Perfil',
        'view'  => '/src/ModuloUsuarios/Views/perfil.html'
    ],
    'login' => [
        'title' => 'Login',
        'view'  => '/src/ModuloUsuarios/Views/login.html'
    ],
];

if (!isset($pages[$page])) {
    $page = 'dashboard';
}

// Guardia de vistas (la seguridad real sigue estando en la API)
$logueado = isset($_SESSION['id_usuario']);

if ($page === 'login' && $logueado) {
    header('Location: index.php?page=dashboard'); exit;
}
if ($page !== 'login' && !$logueado) {
    header('Location: index.php?page=login'); exit;
}
// Contraseña temporal: solo puede ver el perfil
if (!empty($_SESSION['debe_cambiar']) && $page !== 'perfil') {
    header('Location: index.php?page=perfil'); exit;
}
// Vista restringida por rol
if (isset($pages[$page]['roles'])
    && !in_array($_SESSION['rol'] ?? '', $pages[$page]['roles'], true)) {
    header('Location: index.php?page=dashboard'); exit;
}

$pageTitle = $pages[$page]['title'];
$currentPage = $page;
$contentView = __DIR__ . $pages[$page]['view'];

require_once __DIR__ . '/src/Shared/Views/layout.php';