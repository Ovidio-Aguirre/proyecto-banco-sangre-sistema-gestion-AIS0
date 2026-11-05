/* --- MÓDULO DONANTES --- */
(function () {
    const tabla = document.querySelector('#donor-table');
    if (!tabla) return;

    const cuerpo = tabla.tBodies[0] ?? tabla.createTBody();
    const form = document.querySelector('#registrar form');
    const inputBuscar = document.getElementById('buscar');
    const selectFiltro = document.getElementById('buscar-tipo');
    const selectTipo = document.getElementById('tsangre');
    const selectSexo = document.getElementById('sexo');
    const btnBuscar = document.querySelector('#donor-header .main-button');
    const titulo = document.querySelector('#registrar .title');
    const btnGuardar = form.querySelector('[type="submit"]');
    const contenedor = document.querySelector('.page-body');

    const tablaEvaluaciones = document.querySelector('#eval-table').createTBody();
    const tablaDonaciones = document.querySelector('#don-hist-table').createTBody();

    let idEditando = null; // null = registrando uno nuevo

    // Los selects de tipo de sangre usan los ids reales (A+, A-, ...)
    llenarSelect(selectFiltro, TIPOS_SANGRE, 'Todos');
    llenarSelect(selectTipo, TIPOS_SANGRE);

    // DUI: 00000000-0
    form.dui.addEventListener('input', () => {
        const d = form.dui.value.replace(/\D/g, '').slice(0, 9);
        form.dui.value = d.length > 8 ? `${d.slice(0, 8)}-${d.slice(8)}` : d;
    });

    // Contacto: 0000-0000
    form.contacto.addEventListener('input', () => {
        const d = form.contacto.value.replace(/\D/g, '').slice(0, 8);
        form.contacto.value = d.length > 4 ? `${d.slice(0, 4)}-${d.slice(4)}` : d;
    });

    /* --- LISTAR --- */
    async function cargarDonantes() {
        const params = {};
        const texto = inputBuscar.value.trim();
        if (texto) params.busqueda = texto;
        if (selectFiltro.value) params.id_tipo_sangre = selectFiltro.value;

        const [res, resDonaciones] = await Promise.all([
            api('donantes', { params }),
            api('donaciones')
        ]);
        if (!res.ok) {
            cuerpo.innerHTML = filaMensaje(6, mensajeError(res));
            return;
        }

        const donantes = res.data.donantes ?? [];
        if (donantes.length === 0) {
            cuerpo.innerHTML = filaMensaje(6, 'No se encontraron donantes');
            return;
        }

        // La lista de donaciones viene de la más reciente a la más antigua:
        // la primera que aparezca de cada donante es su última donación
        const ultimaDonacion = {};
        (resDonaciones.data?.donaciones ?? []).forEach(don => {
            if (!(don.id_donante in ultimaDonacion)) ultimaDonacion[don.id_donante] = don.fecha_recoleccion;
        });

        cuerpo.innerHTML = donantes.map(d => `
            <tr class="text" data-id="${d.id_donante}">
                <td>${esc(d.dui)}</td>
                <td>${esc(d.nombre)} ${esc(d.apellido)}</td>
                <td><span class="pill">${esc(TIPOS_SANGRE[d.id_tipo_sangre])}</span></td>
                <td>${esc(d.contacto)}</td>
                <td>${esc(ultimaDonacion[d.id_donante] ?? '—')}</td>
                <td>
                    <article>
                        <button class="table-button" data-accion="historial">Historial</button>
                        <button class="table-button" data-accion="editar">Editar</button>
                        <button class="table-button" data-accion="eliminar">Eliminar</button>
                    </article>
                </td>
            </tr>`).join('');
    }

    btnBuscar.addEventListener('click', cargarDonantes);
    inputBuscar.addEventListener('keydown', e => { if (e.key === 'Enter') cargarDonantes(); });
    selectFiltro.addEventListener('change', cargarDonantes);

    /* --- EDITAR / ELIMINAR (botones de la tabla) --- */
    cuerpo.addEventListener('click', async e => {
        const boton = e.target.closest('[data-accion]');
        if (!boton) return;
        const id = boton.closest('tr').dataset.id;

        if (boton.dataset.accion === 'historial') {
            const [resDonante, resEvaluaciones, resDonaciones] = await Promise.all([
                api(`donantes/${id}`),
                api(`donantes/${id}/evaluaciones`, { params: { historial: 1 } }),
                api(`donantes/${id}/donaciones`)
            ]);
            if (!resDonante.ok) return alert(mensajeError(resDonante));
            const d = resDonante.data.donante;

            document.getElementById('hist-nombre').textContent = `${d.nombre} ${d.apellido}`;
            document.getElementById('hist-dui').textContent = d.dui;
            document.getElementById('hist-tipo').textContent = TIPOS_SANGRE[d.id_tipo_sangre];

            const evaluaciones = resEvaluaciones.data?.evaluaciones ?? [];
            tablaEvaluaciones.innerHTML = evaluaciones.length
                ? evaluaciones.map(ev => `
                    <tr class="text">
                        <td>${esc(ev.fecha_evaluacion)}</td>
                        <td>${esc(ev.peso)}</td>
                        <td>${esc(ev.altura)}</td>
                        <td>${esc(ev.resultado === 'APTO' ? 'Apto' : 'No apto')}</td>
                        <td>${esc(ev.motivo ?? '—')}</td>
                    </tr>`).join('')
                : filaMensaje(5, 'Sin evaluaciones registradas');

            const donaciones = resDonaciones.data?.donaciones ?? [];
            tablaDonaciones.innerHTML = donaciones.length
                ? donaciones.map(x => `
                    <tr class="text">
                        <td>DON-${String(x.id_donacion).padStart(3, '0')}</td>
                        <td>${esc(x.fecha_recoleccion)}</td>
                        <td>${esc(x.fecha_caducidad)}</td>
                        <td>${esc(x.estado)}</td>
                    </tr>`).join('')
                : filaMensaje(4, 'Sin donaciones registradas');

            cambiarPanel(contenedor, 'historial');
        }

        if (boton.dataset.accion === 'editar') {
            const res = await api(`donantes/${id}`);
            if (!res.ok) return alert(mensajeError(res));
            const d = res.data.donante;

            idEditando = d.id_donante;
            form.nombre.value = d.nombre;
            form.apellido.value = d.apellido;
            form.dui.value = d.dui;
            form.contacto.value = d.contacto;
            form.nacimiento.value = d.fecha_nacimiento;
            selectSexo.value = d.sexo;
            selectTipo.value = d.id_tipo_sangre;
            titulo.textContent = 'Editar donante';
            btnGuardar.textContent = 'ACTUALIZAR DONANTE';
            cambiarPanel(contenedor, 'registrar');
        }

        if (boton.dataset.accion === 'eliminar') {
            if (!confirm('¿Desactivar este donante?')) return;
            const res = await api(`donantes/${id}`, { metodo: 'PATCH', cuerpo: { activo: false } });
            if (!res.ok) return alert(mensajeError(res));
            cargarDonantes();
        }
    });

    /* --- GUARDAR (crear o actualizar) --- */
    function volverAConsulta() {
        idEditando = null;
        form.reset();
        titulo.textContent = 'Datos del donante';
        btnGuardar.textContent = 'GUARDAR DONANTE';
        cambiarPanel(contenedor, 'consultar');
    }

    form.addEventListener('submit', async e => {
        e.preventDefault();

        // El peso del formulario no se envía: la tabla donantes no tiene esa columna
        const datos = {
            id_tipo_sangre: Number(selectTipo.value),
            dui: form.dui.value.trim(),
            nombre: form.nombre.value.trim(),
            apellido: form.apellido.value.trim(),
            contacto: form.contacto.value.trim(),
            fecha_nacimiento: form.nacimiento.value,
            sexo: selectSexo.value
        };

        const res = idEditando
            ? await api(`donantes/${idEditando}`, { metodo: 'PUT', cuerpo: datos })
            : await api('donantes', { metodo: 'POST', cuerpo: datos });

        if (!res.ok) return alert(mensajeError(res));

        alert(idEditando ? 'Donante actualizado' : (res.data.mensaje || 'Donante registrado'));
        volverAConsulta();
        cargarDonantes();
    });

    // Cancelar también limpia el modo edición
    form.querySelector('.sec-button').addEventListener('click', volverAConsulta);

    cargarDonantes();
})();
