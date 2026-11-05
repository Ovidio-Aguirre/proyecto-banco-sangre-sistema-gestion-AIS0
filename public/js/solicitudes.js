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

    const formNueva = document.querySelector('#nueva form');
    const selectHospital = document.getElementById('hospital');
    const selectTipoPaciente = document.getElementById('tsangre-paciente');
    const formAprobar = document.querySelector('#aprobar form');
    const formRechazar = document.querySelector('#rechazar form');

    // La API pone sola la fecha de entrega al marcar la solicitud como entregada,
    // por eso en el formulario de aprobar ese campo no se pide
    const fechaEntrega = document.getElementById('fecha-entrega');
    fechaEntrega.required = false;
    fechaEntrega.closest('.form-group').hidden = true;

    // Datos de apoyo para mostrar nombres en vez de ids
    let hospitales = {};   // id -> hospital
    let pacientes = {};    // id -> paciente
    let solicitudes = [];  // lista tal como la devuelve la API

    const ESTADOS = { PENDIENTE: 'Pendiente', APROBADA: 'Aprobada', RECHAZADA: 'Rechazada', ENTREGADA: 'Entregada' };

    llenarSelect(selectTipoPaciente, TIPOS_SANGRE);

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
            listar('solicitudes', 'Pacientes') // la API de solicitudes devuelve la lista bajo la clave "Pacientes"
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

        llenarSelect(selectHospital, Object.fromEntries(listaHospitales.map(h => [h.id_hospital, h.nombre_hospital])));
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
                acciones = `<button class="table-button" data-accion="entregar">Entregar</button>`;
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

    // La API cambia el estado con PUT y necesita todos los datos de la solicitud
    function cuerpoActualizacion(s, estado, motivo = null) {
        return {
            idPaciente: Number(s.id_paciente),
            idHospital: Number(s.id_hospital),
            unidadesSolicitadas: Number(s.unidades_solicitadas),
            justificacion: s.justificacion,
            estado,
            motivoRechazo: motivo
        };
    }

    const buscarSolicitud = id => solicitudes.find(s => String(s.id_solicitud) === String(id));

    cuerpo.addEventListener('click', async e => {
        const boton = e.target.closest('[data-accion]');
        if (!boton) return;
        const solicitud = buscarSolicitud(boton.closest('tr').dataset.id);

        if (boton.dataset.accion === 'aprobar') abrirPanel('aprobar', formAprobar, solicitud);
        if (boton.dataset.accion === 'rechazar') abrirPanel('rechazar', formRechazar, solicitud);

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

    /* --- NUEVA SOLICITUD --- */
    // La API pide un paciente ya registrado: primero se registra (o se reutiliza si ya existe)
    async function obtenerIdPaciente(datos) {
        const res = await api('pacientes', {
            metodo: 'POST',
            cuerpo: {
                nombrePaciente: datos.nombre,
                apellidoPaciente: datos.apellido,
                tipoSangre: datos.tipoSangre,
                fechaPaciente: datos.nacimiento,
                sexoPaciente: datos.sexo
            }
        });
        // 409 = el paciente ya estaba registrado, se busca su id
        if (!res.ok && res.status !== 409) return { error: mensajeError(res) };

        const lista = await listar('pacientes', 'Pacientes');
        const paciente = (lista ?? []).find(p =>
            p.nombre_paciente.toLowerCase() === datos.nombre.toLowerCase()
            && p.apellido_paciente.toLowerCase() === datos.apellido.toLowerCase()
            && p.fecha_nacimiento === datos.nacimiento);

        return paciente ? { id: paciente.id_paciente } : { error: 'No se pudo encontrar al paciente' };
    }

    formNueva.addEventListener('submit', async e => {
        e.preventDefault();

        const justificacion = formNueva.justificacion.value.trim();
        if (!justificacion) return alert('La justificación es obligatoria');

        const paciente = await obtenerIdPaciente({
            nombre: formNueva.nombre_paciente.value.trim(),
            apellido: formNueva.apellido_paciente.value.trim(),
            nacimiento: formNueva.fecha_nacimiento.value,
            sexo: formNueva.sexo.value,
            tipoSangre: Number(selectTipoPaciente.value)
        });
        if (paciente.error) return alert(paciente.error);

        const res = await api('solicitudes', {
            metodo: 'POST',
            cuerpo: {
                idPaciente: Number(paciente.id),
                idHospital: Number(selectHospital.value),
                unidadesSolicitadas: Number(formNueva.unidades_solicitadas.value),
                justificacion
            }
        });
        if (!res.ok) return alert(mensajeError(res));

        alert('Solicitud registrada');
        formNueva.reset();
        cambiarPanel(contenedor, 'consultar');
        cargarDatos();
    });

    cargarDatos();
})();
