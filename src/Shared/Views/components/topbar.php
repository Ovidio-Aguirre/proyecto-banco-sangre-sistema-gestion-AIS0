<?php
$nombreCompleto = $_SESSION['nombre_completo'] ?? 'Usuario';
$rolActual      = $_SESSION['rol'] ?? '';
?>

<nav class="user-options">
    <a href="#">Ayuda</a>
    <a href="<?= $baseUrl ?>/index.php?page=perfil" class="<?= ($currentPage ?? '') === 'perfil' ? 'active' : '' ?>">Mi perfil</a>
    <a href="#" data-logout>Cerrar sesion</a>
</nav>

<header class="topbar">
    <a href="<?= $baseUrl ?>/index.php?page=dashboard" class="topbar-title">
        <span class="icon-gota">&#127778;</span>
        <p>BANCO DE<br>SANGRE</p>
    </a>

    <div class="topbar-user">
        <div class="user-info">
            <p class="user-name"><?= htmlspecialchars($nombreCompleto) ?></p>
            <p class="user-role"><?= htmlspecialchars($rolActual) ?></p>
        </div>
    </div>
</header>