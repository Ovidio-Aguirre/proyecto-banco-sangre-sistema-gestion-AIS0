<?php
$baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Banco de Sangre') ?></title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/global.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/components.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/dashboard.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/pages.css">

     <?php if ($currentPage === 'dashboard'): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2" defer></script>
        <script src="<?= $baseUrl ?>/public/js/dashboard.js" defer></script>
    <?php endif; ?>
</head>
<body>
    <?php require __DIR__ . '/components/topbar.php'; ?>

    <?php if ($currentPage !== 'perfil'): ?>
        <?php require __DIR__ . '/components/nav.php'; ?>
    <?php endif; ?>
    
    <main class="main-content">
        <?php require $contentView; ?>
    </main>

    <script src="<?= $baseUrl ?>/public/js/pages.js"></script>

    <?php if (in_array($currentPage, ['donantes', 'hospitales', 'solicitudes'])): ?>
        <script src="<?= $baseUrl ?>/public/js/api.js"></script>
        <?php if ($currentPage === 'hospitales'): ?>
            <script src="<?= $baseUrl ?>/public/js/geo.js"></script>
        <?php endif; ?>
        <script src="<?= $baseUrl ?>/public/js/<?= $currentPage ?>.js"></script>
    <?php endif; ?>
</body>
</html>