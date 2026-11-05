/* --- MÓDULO USUARIOS (solo administrador) --- */
(async function () {
    const raiz = document.querySelector('.usuarios');
    const tabla = raiz?.querySelector('#user-table');
    if (!tabla) return;

    const cuerpo = tabla.tBodies[0] ?? tabla.createTBody();
    const formCrear = raiz.querySelector('#crear form');
    const formEditar = raiz.querySelector('#editar form');
    const formDesactivar = raiz.querySelector('#desactivar form');
    const inputBuscar = raiz.querySelector('#buscar');
    const selectFiltro = raiz.querySelector('#buscar-rol');
    const btnBuscar = raiz.querySelector('#user-header .main-button');
    const contenedor = raiz.querySelector('.page-body');

    // Roles desde la API
    const ROLES = await cargarRoles();
    llenarSelect(selectFiltro, ROLES, 'Todos');
    llenarSelect(formCrear.id_rol, ROLES);
    llenarSelect(formEditar.id_rol, ROLES);

    /* --- LISTAR --- */
    async function cargarUsuarios() {
        const params = {};
        const texto = inputBuscar.value.trim();
        if (texto) params.q = texto;
        if (selectFiltro.value) params.id_rol = selectFiltro.value;

        const res = await api('usuarios', { params });
        if (!res.ok) {
            cuerpo.innerHTML = filaMensaje(5, mensajeError(res));
            return;
        }

        const usuarios = res.data.usuarios ?? [];
        if (usuarios.length === 0) {
            cuerpo.innerHTML = filaMensaje(5, 'No se encontraron usuarios');
            return;
        }

        cuerpo.innerHTML = usuarios.map(u => `
            <tr class="text" data-id="${u.id_usuario}" data-id-rol="${u.id_rol}" data-activo="${Number(u.activo)}">
                <td>${esc(u.nombre_usuario)}</td>
                <td>${esc(u.correo)}</td>
                <td>${esc(u.rol)}</td>
                <td>${Number(u.activo) === 1 ? 'Activo' : 'Inactivo'}</td>
                <td>
                    <article>
                        <button class="table-button" data-accion="editar">Editar</button>
                        ${Number(u.activo) === 1
                            ? '<button class="table-button" data-accion="desactivar">Desactivar</button>'
                            : ''}
                    </article>
                </td>
            </tr>`).join('');
    }

    btnBuscar.addEventListener('click', cargarUsuarios);
    inputBuscar.addEventListener('keydown', e => { if (e.key === 'Enter') cargarUsuarios(); });
    selectFiltro.addEventListener('change', cargarUsuarios);

    /* --- BOTONES DE LA TABLA --- */
    cuerpo.addEventListener('click', e => {
        const boton = e.target.closest('[data-accion]');
        if (!boton) return;

        const fila = boton.closest('tr');
        const id = fila.dataset.id;
        const celdas = fila.children;

        if (boton.dataset.accion === 'editar') {
            formEditar.id_usuario.value = id;
            formEditar.nombre_usuario.value = celdas[0].textContent;
            formEditar.correo.value = celdas[1].textContent;
            formEditar.id_rol.value = fila.dataset.idRol;
            formEditar.activo.value = fila.dataset.activo;

            // Valores originales, para enviar solo lo que cambie
            formEditar.dataset.correo = celdas[1].textContent;
            formEditar.dataset.idRol = fila.dataset.idRol;
            formEditar.dataset.activo = fila.dataset.activo;
            cambiarPanel(contenedor, 'editar');
        }

        if (boton.dataset.accion === 'desactivar') {
            formDesactivar.id_usuario.value = id;
            raiz.querySelector('#desactivar-usuario').textContent = celdas[0].textContent;
            cambiarPanel(contenedor, 'desactivar');
        }
    });

    /* --- CREAR --- */
    formCrear.addEventListener('submit', async e => {
        e.preventDefault();

        const res = await api('usuarios', {
            metodo: 'POST',
            cuerpo: {
                nombre: formCrear.nombre.value.trim(),
                apellido: formCrear.apellido.value.trim(),
                correo: formCrear.correo.value.trim(),
                id_rol: Number(formCrear.id_rol.value)
            }
        });
        if (!res.ok) return alert(mensajeError(res));

        // La contraseña temporal solo se muestra esta vez
        alert(
            `Usuario creado\n\n` +
            `Usuario: ${res.data.nombre_usuario}\n` +
            `Contraseña temporal: ${res.data.contrasena_temporal}\n\n` +
            `Entréguela al usuario; deberá cambiarla en su primer ingreso.`
        );
        formCrear.reset();
        cambiarPanel(contenedor, 'consultar');
        cargarUsuarios();
    });

    /* --- EDITAR --- */
    formEditar.addEventListener('submit', async e => {
        e.preventDefault();

        // Solo se envían los campos modificados
        const cambios = {};
        const correo = formEditar.correo.value.trim();
        if (correo !== formEditar.dataset.correo) cambios.correo = correo;
        if (formEditar.id_rol.value !== formEditar.dataset.idRol) {
            cambios.id_rol = Number(formEditar.id_rol.value);
        }
        if (formEditar.activo.value !== formEditar.dataset.activo) {
            cambios.activo = formEditar.activo.value === '1';
        }

        if (Object.keys(cambios).length === 0) {
            return alert('No hay cambios para guardar');
        }

        const res = await api(`usuarios/${formEditar.id_usuario.value}`, {
            metodo: 'PATCH',
            cuerpo: cambios
        });
        if (!res.ok) return alert(mensajeError(res));

        alert('Usuario actualizado');
        cambiarPanel(contenedor, 'consultar');
        cargarUsuarios();
    });

    /* --- DESACTIVAR --- */
    formDesactivar.addEventListener('submit', async e => {
        e.preventDefault();

        const res = await api(`usuarios/${formDesactivar.id_usuario.value}`, {
            metodo: 'PATCH',
            cuerpo: { activo: false }
        });
        if (!res.ok) return alert(mensajeError(res));

        cambiarPanel(contenedor, 'consultar');
        cargarUsuarios();
    });

    // Cancelar limpia los formularios de creación
    formCrear.querySelector('.sec-button').addEventListener('click', () => formCrear.reset());

    cargarUsuarios();
})();