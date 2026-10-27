<nav class="user-options">
    <a href="#">Ayuda</a>
    <a href="index.php?page=perfil" class="<?= ($currentPage ?? '') === 'perfil' ? 'active' : '' ?>">Mi perfil</a>
    <a href="#">Cerrar sesion</a>
</nav>

<header class="topbar">
    <a href="/bancoSangre/index.php?page=dashboard" class="topbar-title">
        <span class="icon-gota">&#127778;</span>
        <p>BANCO DE<br>SANGRE</p>
    </a>

    <div class="topbar-user">
        <div class="user-info">
            <p class="user-name">Usuario</p>
            <p class="user-role">Rol</p>
        </div>

        <div class="user-avatar">
            <img src="/banco_sangre/public/img/user.png"
                 alt="Foto">
        </div>
    </div>
</header>