/* --- MÓDULO SOLICITUDES --- */
(function () {
    const tabla = document.querySelector('#request-table');
    if (!tabla) return;

    const cuerpo = tabla.tBodies[0] ?? tabla.createTBody();
    const cuerpoHistorial = document.querySelector('#request-history-table').createTBody();
    const contenedor = document.querySelector('.page-body');

    const inputBuscar = document.getElementById('buscar');
    const selectEstado = document.getElementById('buscar-estado');
    const btnBuscar = document.querySelector('#request-header .main-button');

    const inputDesde = document.getElementById('desde');
    const inputHasta = document.getElementById('hasta');
    const selectEstadoHistorial = document.getElementById('historial-estado');
    const btnBuscarHistorial = document.querySelector('#request-history-header .main-button');

    const formAprobar = document.querySelector('#aprobar form');
    const formRechazar = document.querySelector('#rechazar form');
    const formAsignar = document.querySelector('#asignar form');
    const cuerpoAsignadas = document.getElementById('assigned-body');
    const cuerpoCompatibles = document.getElementById('compatible-body');
    const btnAsignar = document.getElementById('asignar-guardar');

    // Datos de apoyo para mostrar nombres en vez de ids
    let hospitales = {};   // id -> hospital
    let pacientes = {};    // id -> paciente
    let solicitudes = [];  // lista tal como la devuelve la API

    const ESTADOS = { PENDIENTE: 'Pendiente', APROBADA: 'Aprobada', RECHAZADA: 'Rechazada', ENTREGADA: 'Entregada' };

    const codigo = id => 'SOL-' + String(id).padStart(3, '0');
    const nombrePaciente = p => p ? `${p.nombre_paciente} ${p.apellido_paciente}` : '—';

    /* --- CARGA DE DATOS --- */
    // Las listas vacías la API las responde con 400, aquí se toman como lista vacía
    async function listar(ruta, clave) {
        const res = await api(ruta);
        if (!res.ok && res.status !== 400) return null;
        return res.data[clave] ?? [];
    }

    async function cargarDatos() {
        const [listaHospitales, listaPacientes, listaSolicitudes] = await Promise.all([
            listar('hospitales', 'Hospitales'),
            listar('pacientes', 'Pacientes'),
            listar('solicitudes', 'solicitudes')
        ]);

        if (listaHospitales === null || listaPacientes === null || listaSolicitudes === null) {
            const error = filaMensaje(8, 'No se pudo conectar con el servidor');
            cuerpo.innerHTML = error;
            cuerpoHistorial.innerHTML = error;
            return;
        }

        hospitales = Object.fromEntries(listaHospitales.map(h => [h.id_hospital, h]));
        pacientes = Object.fromEntries(listaPacientes.map(p => [p.id_paciente, p]));
        solicitudes = listaSolicitudes;

        pintarConsulta();
        pintarHistorial();
    }

    /* --- CONSULTAR --- */
    function pintarConsulta() {
        const texto = inputBuscar.value.trim().toLowerCase();
        const estado = selectEstado.value;

        const lista = solicitudes.filter(s => {
            const hospital = hospitales[s.id_hospital]?.nombre_hospital ?? '';
            const paciente = nombrePaciente(pacientes[s.id_paciente]);
            return (!estado || s.estado === estado.toUpperCase())
                && (!texto || `${hospital} ${paciente}`.toLowerCase().includes(texto));
        });

        if (lista.length === 0) {
            cuerpo.innerHTML = filaMensaje(8, 'No se encontraron solicitudes');
            return;
        }

        cuerpo.innerHTML = lista.map(s => {
            const paciente = pacientes[s.id_paciente];
            // Solo se puede aprobar/rechazar una pendiente y entregar una aprobada
            let acciones = '';
            if (s.estado === 'PENDIENTE') {
                acciones = `<button class="table-button" data-accion="aprobar">Aprobar</button>
                            <button class="table-button" data-accion="rechazar">Rechazar</button>`;
            } else if (s.estado === 'APROBADA') {
                acciones = `<button class="table-button" data-accion="asignar">Asignar</button>
                            <button class="table-button" data-accion="entregar">Entregar</button>`;
            }
            return `
            <tr class="text" data-id="${s.id_solicitud}">
                <td>${codigo(s.id_solicitud)}</td>
                <td>${esc(hospitales[s.id_hospital]?.nombre_hospital ?? '—')}</td>
                <td>${esc(nombrePaciente(paciente))}</td>
                <td><span class="pill">${esc(TIPOS_SANGRE[paciente?.id_tipo_sangre] ?? '—')}</span></td>
                <td>${esc(s.unidades_solicitadas)}</td>
                <td>${esc(s.fecha_solicitud)}</td>
                <td>${esc(ESTADOS[s.estado] ?? s.estado)}</td>
                <td><article>${acciones}</article></td>
            </tr>`;
        }).join('');
    }

    btnBuscar.addEventListener('click', pintarConsulta);
    inputBuscar.addEventListener('keydown', e => { if (e.key === 'Enter') pintarConsulta(); });
    selectEstado.addEventListener('change', pintarConsulta);

    /* --- HISTORIAL (solicitudes ya resueltas) --- */
    function pintarHistorial() {
        const estado = selectEstadoHistorial.value;

        const lista = solicitudes.filter(s =>
            s.estado !== 'PENDIENTE'
            && (!estado || s.estado === estado.toUpperCase())
            && (!inputDesde.value || s.fecha_solicitud >= inputDesde.value)
            && (!inputHasta.value || s.fecha_solicitud <= inputHasta.value));

        if (lista.length === 0) {
            cuerpoHistorial.innerHTML = filaMensaje(7, 'No hay solicitudes en el historial');
            return;
        }

        cuerpoHistorial.innerHTML = lista.map(s => {
            // Entregada: fecha de entrega. Rechazada: el motivo. Aprobada: aún sin entregar
            const detalle = s.estado === 'ENTREGADA' ? s.fecha_entrega
                : s.estado === 'RECHAZADA' ? s.motivo_rechazo
                : 'Pendiente de entrega';
            return `
            <tr class="text">
                <td>${codigo(s.id_solicitud)}</td>
                <td>${esc(s.fecha_solicitud)}</td>
                <td>${esc(hospitales[s.id_hospital]?.nombre_hospital ?? '—')}</td>
                <td>${esc(nombrePaciente(pacientes[s.id_paciente]))}</td>
                <td>${esc(s.unidades_solicitadas)}</td>
                <td>${esc(ESTADOS[s.estado] ?? s.estado)}</td>
                <td>${esc(detalle ?? '—')}</td>
            </tr>`;
        }).join('');
    }

    btnBuscarHistorial.addEventListener('click', pintarHistorial);
    selectEstadoHistorial.addEventListener('change', pintarHistorial);
    inputDesde.addEventListener('change', pintarHistorial);
    inputHasta.addEventListener('change', pintarHistorial);

    /* --- ACCIONES DE LA TABLA (aprobar, rechazar, entregar) --- */
    // Muestra en el panel los datos de la solicitud elegida
    function abrirPanel(panel, form, solicitud) {
        form.id_solicitud.value = solicitud.id_solicitud;
        document.getElementById(`${panel}-hospital`).textContent = hospitales[solicitud.id_hospital]?.nombre_hospital ?? '—';
        document.getElementById(`${panel}-paciente`).textContent = nombrePaciente(pacientes[solicitud.id_paciente]);
        document.getElementById(`${panel}-unidades`).textContent = solicitud.unidades_solicitadas;
        cambiarPanel(contenedor, panel);
    }

    // La API solo pide el nuevo estado y el motivo de rechazo en caso de aplicar
    function cuerpoActualizacion(s, estado, motivo = null) {
        return { estado, motivoRechazo: motivo };
    }

    const buscarSolicitud = id => solicitudes.find(s => String(s.id_solicitud) === String(id));

    cuerpo.addEventListener('click', async e => {
        const boton = e.target.closest('[data-accion]');
        if (!boton) return;
        const solicitud = buscarSolicitud(boton.closest('tr').dataset.id);

        if (boton.dataset.accion === 'aprobar') abrirPanel('aprobar', formAprobar, solicitud);
        if (boton.dataset.accion === 'rechazar') abrirPanel('rechazar', formRechazar, solicitud);
        if (boton.dataset.accion === 'asignar') abrirAsignar(solicitud);
        if (boton.dataset.accion === 'entregar') {
            if (!confirm('¿Marcar esta solicitud como entregada?')) return;
            const res = await api(`solicitudes/${solicitud.id_solicitud}`, { metodo: 'PATCH' });
            if (!res.ok) return alert(mensajeError(res));
            cargarDatos();
        }
    });

    formAprobar.addEventListener('submit', async e => {
        e.preventDefault();
        const solicitud = buscarSolicitud(formAprobar.id_solicitud.value);
        const res = await api(`solicitudes/${solicitud.id_solicitud}`, {
            metodo: 'PUT',
            cuerpo: cuerpoActualizacion(solicitud, 'APROBADA')
        });
        if (!res.ok) return alert(mensajeError(res));
        cambiarPanel(contenedor, 'consultar');
        cargarDatos();
    });

    formRechazar.addEventListener('submit', async e => {
        e.preventDefault();
        const solicitud = buscarSolicitud(formRechazar.id_solicitud.value);
        const res = await api(`solicitudes/${solicitud.id_solicitud}`, {
            metodo: 'PUT',
            cuerpo: cuerpoActualizacion(solicitud, 'RECHAZADA', formRechazar.motivo_rechazo.value.trim())
        });
        if (!res.ok) return alert(mensajeError(res));
        formRechazar.reset();
        cambiarPanel(contenedor, 'consultar');
        cargarDatos();
    });

    /* --- ASIGNAR UNIDADES --- */
    let cupoRestante = 0;

    // GET /solicitudes/{id}/unidades-compatibles
    async function cargarAsignacion(idSolicitud) {
        const res = await api(`solicitudes/${idSolicitud}/unidades-compatibles`);
        if (!res.ok) {
            alert(mensajeError(res));
            return false;
        }

        const d = res.data;
        cupoRestante = Number(d.cupo_restante);

        document.getElementById('asignar-tipo').textContent = d.tipo_requerido ?? '—';
        document.getElementById('asignar-progreso').textContent =
            `${d.unidades_asignadas} de ${d.unidades_solicitadas}`;
        document.getElementById('asignar-cupo').textContent = cupoRestante;

        // Unidades ya asignadas (solo las RESERVADA se pueden desvincular)
        const asignadas = d.asignadas ?? [];
        cuerpoAsignadas.innerHTML = asignadas.length
            ? asignadas.map(u => `
                <tr class="text" data-id="${u.id_donacion}">
                    <td>DON-${String(u.id_donacion).padStart(3, '0')}</td>
                    <td><span class="pill">${esc(u.tipo_sangre)}</span></td>
                    <td>${esc(u.fecha_caducidad)}</td>
                    <td>${esc(u.estado === 'RESERVADA' ? 'Reservada' : 'Utilizada')}</td>
                    <td>${u.estado === 'RESERVADA'
                        ? '<article><button type="button" class="table-button" data-accion="desvincular">Desvincular</button></article>'
                        : '—'}</td>
                </tr>`).join('')
            : filaMensaje(5, 'Aún no hay unidades asignadas');

        // Unidades compatibles disponibles
        const unidades = d.unidades ?? [];
        if (d.estado !== 'APROBADA') {
            cuerpoCompatibles.innerHTML = filaMensaje(6, 'La solicitud no está aprobada');
        } else if (cupoRestante === 0) {
            cuerpoCompatibles.innerHTML = filaMensaje(6, 'La solicitud ya tiene todas las unidades asignadas');
        } else if (!unidades.length) {
            cuerpoCompatibles.innerHTML = filaMensaje(6, 'No hay unidades compatibles disponibles');
        } else {
            cuerpoCompatibles.innerHTML = unidades.map(u => `
                <tr class="text">
                    <td><input type="checkbox" name="unidad" value="${u.id_donacion}"></td>
                    <td>DON-${String(u.id_donacion).padStart(3, '0')}</td>
                    <td><span class="pill">${esc(u.tipo_sangre)}</span></td>
                    <td>${esc(u.fecha_recoleccion)}</td>
                    <td>${esc(u.fecha_caducidad)}</td>
                    <td>${Number(u.dias_para_vencer) === 0 ? 'Hoy' : esc(u.dias_para_vencer)}</td>
                </tr>`).join('');
        }

        actualizarSeleccion();
        return true;
    }

    // Limita la selección al cupo restante (el backend lo vuelve a validar)
    function actualizarSeleccion() {
        const casillas = cuerpoCompatibles.querySelectorAll('input[name="unidad"]');
        const marcadas = cuerpoCompatibles.querySelectorAll('input[name="unidad"]:checked');

        document.getElementById('asignar-seleccionadas').textContent = marcadas.length;
        casillas.forEach(c => { c.disabled = !c.checked && marcadas.length >= cupoRestante; });
        btnAsignar.disabled = marcadas.length === 0;
    }

    cuerpoCompatibles.addEventListener('change', actualizarSeleccion);

    async function abrirAsignar(solicitud) {
        formAsignar.id_solicitud.value = solicitud.id_solicitud;
        document.getElementById('asignar-hospital').textContent = hospitales[solicitud.id_hospital]?.nombre_hospital ?? '—';
        document.getElementById('asignar-paciente').textContent = nombrePaciente(pacientes[solicitud.id_paciente]);

        if (await cargarAsignacion(solicitud.id_solicitud)) cambiarPanel(contenedor, 'asignar');
    }

    // PATCH /donaciones/{id}/solicitud con id_solicitud: null
    cuerpoAsignadas.addEventListener('click', async e => {
        const boton = e.target.closest('[data-accion="desvincular"]');
        if (!boton) return;
        if (!confirm('¿Desvincular esta unidad? Volverá a estar Disponible.')) return;

        const idDonacion = boton.closest('tr').dataset.id;
        const res = await api(`donaciones/${idDonacion}/solicitud`, {
            metodo: 'PATCH',
            cuerpo: { id_solicitud: null }
        });
        if (!res.ok) return alert(mensajeError(res));

        await cargarAsignacion(formAsignar.id_solicitud.value);
    });

    // POST /solicitudes/{id}/unidades
    formAsignar.addEventListener('submit', async e => {
        e.preventDefault();

        const ids = [...cuerpoCompatibles.querySelectorAll('input[name="unidad"]:checked')]
            .map(c => Number(c.value));
        if (!ids.length) return alert('Seleccione al menos una unidad');

        const idSolicitud = formAsignar.id_solicitud.value;
        const res = await api(`solicitudes/${idSolicitud}/unidades`, {
            metodo: 'POST',
            cuerpo: { ids }
        });
        if (!res.ok) {
            await cargarAsignacion(idSolicitud); // refresca por si otra persona ya tomó alguna unidad
            return alert(mensajeError(res));
        }

        alert('Unidades asignadas correctamente');
        await cargarAsignacion(idSolicitud);
    });

    cargarDatos();
})();
