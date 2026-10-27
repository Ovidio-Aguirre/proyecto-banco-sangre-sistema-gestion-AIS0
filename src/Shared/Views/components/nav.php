<nav class="navbar">
    <a href="index.php?page=dashboard" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>

    <a href="index.php?page=donantes" class="<?= ($currentPage ?? '') === 'donantes' ? 'active' : '' ?>">Donantes</a>

    <a href="index.php?page=donaciones" class="<?= ($currentPage ?? '') === 'donaciones' ? 'active' : '' ?>">Donaciones</a>

    <a href="index.php?page=inventario" class="<?= ($currentPage ?? '') === 'inventario' ? 'active' : '' ?>">Inventario</a>

    <a href="index.php?page=solicitudes" class="<?= ($currentPage ?? '') === 'solicitudes' ? 'active' : '' ?>">Solicitudes</a>

    <a href="index.php?page=hospitales" class="<?= ($currentPage ?? '') === 'hospitales' ? 'active' : '' ?>">Hospitales</a>

    <a href="index.php?page=reportes" class="<?= ($currentPage ?? '') === 'reportes' ? 'active' : '' ?>">Reportes</a>

    <a href="index.php?page=usuarios" class="<?= ($currentPage ?? '') === 'usuarios' ? 'active' : '' ?>">Usuarios</a>
</nav>