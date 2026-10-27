<?php
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
        'view'  => '/src/ModuloUsuarios/Views/usuarios.html'
    ],
    'perfil' => [
        'title' => 'Perfil',
        'view'  => '/src/ModuloUsuarios/Views/perfil.html'
    ],
    
];

if (!isset($pages[$page])) {
    $page = 'dashboard';
}

$pageTitle = $pages[$page]['title'];
$currentPage = $page;
$contentView = __DIR__ . $pages[$page]['view'];

    require_once __DIR__ . '/src/Shared/Views/layout.php';
?>