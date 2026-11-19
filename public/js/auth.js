/* --- MÓDULO AUTENTICACIÓN (login, perfil, cierre de sesión) --- */

/* --- LOGIN --- */
(function () {
    const form = document.querySelector('#login form');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();

        const res = await api('auth/login', {
            metodo: 'POST',
            cuerpo: {
                usuario: form.usuario.value.trim(),
                contrasena: form.contrasena.value
            }
        });
        if (!res.ok) return alert(mensajeError(res));

        // Con contraseña temporal se envía directo al cambio de contraseña
        window.location.href = res.data.usuario.debe_cambiar_contrasena
            ? PAGINA_CAMBIO
            : PAGINA_INICIO;
    });
})();

/* --- PERFIL --- */
(function () {
    const raiz = document.getElementById('perfil');
    if (!raiz) return;

    const formDatos = raiz.querySelector('#datos form');
    const formClave = raiz.querySelector('#password form');
    const contenedor = raiz.querySelector('.page-body');
    let debeCambiar = false;

    async function cargarPerfil() {
        const res = await api('auth/perfil');
        if (!res.ok) return alert(mensajeError(res));

        const u = res.data.usuario;
        debeCambiar = Number(u.debe_cambiar_contrasena) === 1;
        formDatos.nombre_usuario.value = u.nombre_usuario;
        formDatos.correo.value = u.correo;
        formDatos.rol.value = u.rol;

        // Contraseña temporal: abrir directamente el panel de cambio
        if (debeCambiar) cambiarPanel(contenedor, 'password');
    }

    formClave.addEventListener('submit', async e => {
        e.preventDefault();

        if (formClave.contrasena_nueva.value !== formClave.contrasena_confirmar.value) {
            return alert('La confirmación no coincide con la nueva contraseña');
        }

        const res = await api('auth/cambiar-contrasena', {
            metodo: 'POST',
            cuerpo: {
                contrasena_actual: formClave.contrasena_actual.value,
                contrasena_nueva: formClave.contrasena_nueva.value
            }
        });
        if (!res.ok) return alert(mensajeError(res));

        alert('Contraseña actualizada');
        formClave.reset();
        if (debeCambiar) {
            window.location.href = PAGINA_INICIO;
        } else {
            cambiarPanel(contenedor, 'datos');
        }
    });

    formClave.querySelector('.sec-button').addEventListener('click', () => formClave.reset());

    cargarPerfil();
})();

/* --- CERRAR SESIÓN (cualquier elemento con data-logout) --- */
document.addEventListener('click', async e => {
    if (!e.target.closest('[data-logout]')) return;
    e.preventDefault();
    await api('auth/logout', { metodo: 'POST' });
    window.location.href = PAGINA_LOGIN;
});