/* --- MÓDULO INVENTARIO --- */
(function () {
    const tablaStock = document.querySelector('#stock-table');
    if (!tablaStock) return;

    const cuerpoStock = tablaStock.tBodies[0] ?? tablaStock.createTBody();
    const cuerpoInventario = document.querySelector('#inventory-table').createTBody();
    const cuerpoBajo = document.querySelector('#low-stock-table').createTBody();
    const cuerpoVence = document.querySelector('#expiring-table').createTBody();
    const contenedor = document.querySelector('.page-body');

    // Formulario de uso
    const formUso = document.querySelector('#usar form');
    const selectUnidad = document.getElementById('uso-unidad');
    const selectSolicitud = document.getElementById('uso-solicitud');

    // Filtros de búsqueda
    const filtroTipo = document.getElementById('filtro-tipo');
    const filtroEstado = document.getElementById('filtro-estado');
    const caducaAntes = document.getElementById('caduca-antes');
    const btnBuscar = document.querySelector('#inventory-header .main-button');
    const btnLimpiar = document.querySelector('#inventory-header .sec-button');

    // Estados que devuelve la API de inventario
    const ESTADOS = { DISPONIBLE: 'Disponible', RESERVADA: 'Reservada', VENCIDA: 'Vencida' };

    // Umbrales de las alertas (el backend no guarda un mínimo por tipo)
    const STOCK_MINIMO = 5;   // unidades disponibles por tipo
    const DIAS_ALERTA = 7;    // días para considerar "próxima a caducar"

    let resumen = [];       // [{ id_tipo_sangre, tipo_sangre, disponibles, reservadas, vencidas }]
    let unidades = [];      // todas las unidades, con su tipo
    let solicitudes = [];   // solicitudes aprobadas normalizadas

    /* --- UTILIDADES --- */
    const codigo = id => `DON-${String(id).padStart(3, '0')}`;
    const setTexto = (id, texto) => { document.getElementById(id).textContent = texto ?? ''; };

    function llenarOpciones(select, opciones, placeholder = 'Seleccione') {
        select.innerHTML =
            `<option value="" disabled selected hidden>${placeholder}</option>` +
            opciones.map(o => `<option value="${esc(o.value)}">${esc(o.texto)}</option>`).join('');
    }

    /* --- CARGA DE DATOS --- */
    // GET /inventario (totales) + GET /inventario/{id} por cada tipo (unidades)
    async function cargarInventario() {
        const res = await api('inventario');
        if (!res.ok) {
            const msg = mensajeError(res);
            cuerpoStock.innerHTML = filaMensaje(5, msg);
            cuerpoInventario.innerHTML = filaMensaje(6, msg);
            cuerpoBajo.innerHTML = filaMensaje(4, msg);
            cuerpoVence.innerHTML = filaMensaje(4, msg);
            return;
        }
        resumen = res.data.inventario ?? [];

        const detalles = await Promise.all(resumen.map(t => api(`inventario/${t.id_tipo_sangre}`)));
        unidades = detalles.flatMap((r, i) => r.ok
            ? (r.data.inventario?.unidades ?? []).map(u => ({
                ...u,
                id_tipo_sangre: resumen[i].id_tipo_sangre,
                tipo_sangre: resumen[i].tipo_sangre
            }))
            : []);

        llenarFiltroTipo();
        pintarStock();
        pintarBusqueda();
        pintarAlertas();
        llenarUnidades();
    }

    /* --- VER STOCK --- */
    function pintarStock() {
        if (!resumen.length) {
            cuerpoStock.innerHTML = filaMensaje(5, 'Sin datos de inventario');
            return;
        }
        cuerpoStock.innerHTML = resumen.map(t => {
            const disp = Number(t.disponibles), res = Number(t.reservadas), venc = Number(t.vencidas);
            return `
            <tr class="text">
                <td><span class="pill">${esc(t.tipo_sangre)}</span></td>
                <td>${disp}</td>
                <td>${res}</td>
                <td>${venc}</td>
                <td>${disp + res + venc}</td>
            </tr>`;
        }).join('');
    }

    /* --- BUSCAR Y FILTRAR --- */
    function llenarFiltroTipo() {
        filtroTipo.innerHTML = '<option value="">Todos</option>' +
            resumen.map(t => `<option value="${t.id_tipo_sangre}">${esc(t.tipo_sangre)}</option>`).join('');
    }

    function pintarBusqueda() {
        const tipo = filtroTipo.value;
        const estado = filtroEstado.value;
        const antes = caducaAntes.value;

        const lista = unidades.filter(u =>
            (!tipo || String(u.id_tipo_sangre) === tipo) &&
            (!estado || u.estado === estado) &&
            (!antes || u.fecha_caducidad < antes)
        );

        if (!lista.length) {
            cuerpoInventario.innerHTML = filaMensaje(6, 'No se encontraron unidades');
            return;
        }

        cuerpoInventario.innerHTML = lista.map(u => `
            <tr class="text">
                <td>${codigo(u.id_donacion)}</td>
                <td><span class="pill">${esc(u.tipo_sangre)}</span></td>
                <td>${esc(ESTADOS[u.estado] ?? u.estado)}</td>
                <td>${esc(u.fecha_recoleccion)}</td>
                <td>${esc(u.fecha_caducidad)}</td>
                <td>${esc(u.dias_para_vencer)}</td>
            </tr>`).join('');
    }

    btnBuscar.addEventListener('click', pintarBusqueda);
    [filtroTipo, filtroEstado, caducaAntes].forEach(el => el.addEventListener('change', pintarBusqueda));
    btnLimpiar.addEventListener('click', () => {
        filtroTipo.value = '';
        filtroEstado.value = '';
        caducaAntes.value = '';
        pintarBusqueda();
    });

    /* --- ALERTAS --- */
    function pintarAlertas() {
        // Niveles bajos
        const bajos = resumen.filter(t => Number(t.disponibles) < STOCK_MINIMO);
        cuerpoBajo.innerHTML = bajos.length
            ? bajos.map(t => `
                <tr class="text">
                    <td><span class="pill">${esc(t.tipo_sangre)}</span></td>
                    <td>${Number(t.disponibles)}</td>
                    <td>${STOCK_MINIMO}</td>
                    <td>${Number(t.disponibles) === 0 ? 'Agotado' : 'Bajo'}</td>
                </tr>`).join('')
            : filaMensaje(4, 'Todos los tipos tienen stock suficiente');

        // Próximas a caducar (solo unidades aún vigentes)
        const porVencer = unidades
            .filter(u => u.estado !== 'VENCIDA' && Number(u.dias_para_vencer) <= DIAS_ALERTA)
            .sort((a, b) => Number(a.dias_para_vencer) - Number(b.dias_para_vencer));

        cuerpoVence.innerHTML = porVencer.length
            ? porVencer.map(u => `
                <tr class="text">
                    <td>${codigo(u.id_donacion)}</td>
                    <td><span class="pill">${esc(u.tipo_sangre)}</span></td>
                    <td>${esc(u.fecha_caducidad)}</td>
                    <td>${Number(u.dias_para_vencer) === 0 ? 'Hoy' : esc(u.dias_para_vencer)}</td>
                </tr>`).join('')
            : filaMensaje(4, `Ninguna unidad vence en los próximos ${DIAS_ALERTA} días`);
    }

    /* --- USAR UNIDADES --- */
    function llenarUnidades() {
        const disponibles = unidades
            .filter(u => u.estado === 'DISPONIBLE')
            .sort((a, b) => a.fecha_caducidad.localeCompare(b.fecha_caducidad)); // primero las que vencen antes

        llenarOpciones(selectUnidad, disponibles.map(u => ({
            value: u.id_donacion,
            texto: `${codigo(u.id_donacion)} · ${u.tipo_sangre} · caduca ${u.fecha_caducidad}`
        })));
    }

    selectUnidad.addEventListener('change', () => {
        const u = unidades.find(x => String(x.id_donacion) === selectUnidad.value);
        if (!u) return;
        setTexto('detalle-tipo', u.tipo_sangre);
        setTexto('detalle-estado', ESTADOS[u.estado] ?? u.estado);
        setTexto('detalle-recoleccion', u.fecha_recoleccion);
        setTexto('detalle-caducidad', u.fecha_caducidad);
    });

    const normalizarSolicitud = s => ({
        id: s.id_solicitud,
        estado: s.estado,
        hospital: s.nombre_hospital ?? `Hospital #${s.id_hospital}`,
        paciente: [s.nombre_paciente, s.apellido_paciente].filter(Boolean).join(' ') || `Paciente #${s.id_paciente}`,
        tipo: TIPOS_SANGRE[s.id_tipo_sangre] ?? '—',
        unidades: s.unidades_solicitadas
    });

    async function cargarSolicitudes() {
        const res = await api('solicitudes');

        const sinResultados = !res.ok && res.status === 400;

        if (!res.ok && !sinResultados) {
            console.error(mensajeError(res));
            llenarOpciones(selectSolicitud, [], 'Error al cargar solicitudes (ver consola)');
            return;
        }

        const lista = res.data?.Pacientes ?? res.data?.solicitudes ?? [];
        solicitudes = lista
            .map(normalizarSolicitud)
            .filter(s => String(s.estado).trim().toUpperCase() === 'APROBADA');

        llenarOpciones(
            selectSolicitud,
            solicitudes.map(s => ({
                value: s.id,
                texto: `SOL-${String(s.id).padStart(3, '0')} · ${s.hospital} · ${s.tipo} · ${s.unidades} u.`
            })),
            solicitudes.length ? 'Seleccione' : `No hay solicitudes aprobadas (de ${lista.length})`
        );
    }

    selectSolicitud.addEventListener('change', () => {
        const s = solicitudes.find(x => String(x.id) === selectSolicitud.value);
        if (!s) return;
        setTexto('sol-hospital', s.hospital);
        setTexto('sol-paciente', s.paciente);
        setTexto('sol-tipo', s.tipo);
        setTexto('sol-unidades', s.unidades);
    });

    function limpiarUso() {
        formUso.reset();
        ['detalle-tipo', 'detalle-estado', 'detalle-recoleccion', 'detalle-caducidad',
         'sol-hospital', 'sol-paciente', 'sol-tipo', 'sol-unidades'].forEach(id => setTexto(id, ''));
    }

    // Registrar uso = vincular la solicitud (DISPONIBLE -> RESERVADA) y marcarla UTILIZADA
    formUso.addEventListener('submit', async e => {
        e.preventDefault();

        const idDonacion = selectUnidad.value;
        const idSolicitud = Number(selectSolicitud.value);

        // 1) PATCH /donaciones/{id}/solicitud
        let res = await api(`donaciones/${idDonacion}/solicitud`, {
            metodo: 'PATCH',
            cuerpo: { id_solicitud: idSolicitud }
        });
        if (!res.ok) return alert(mensajeError(res));

        // 2) PUT /donaciones/{id}  (RESERVADA -> UTILIZADA)
        res = await api(`donaciones/${idDonacion}`, { metodo: 'PUT', cuerpo: { estado: 'UTILIZADA' } });
        if (!res.ok) {
            await cargarInventario();
            return alert(`La unidad quedó reservada para la solicitud, pero no se pudo marcar como utilizada: ${mensajeError(res)}`);
        }

        alert(`Uso de ${codigo(idDonacion)} registrado`);
        limpiarUso();
        await cargarInventario();
        cambiarPanel(contenedor, 'stock');
    });

    formUso.querySelector('.sec-button').addEventListener('click', limpiarUso);

    /* --- INICIO --- */
    cargarInventario();
    cargarSolicitudes();
})();