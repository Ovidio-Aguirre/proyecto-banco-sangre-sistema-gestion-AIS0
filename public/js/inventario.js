/* --- MÓDULO INVENTARIO --- */
(function () {
    const panelStock = document.getElementById('stock-cards');
    if (!panelStock) return;
    const cuerpoInventario = document.querySelector('#inventory-table').createTBody();
    const cuerpoBajo = document.querySelector('#low-stock-table').createTBody();
    const cuerpoVence = document.querySelector('#expiring-table').createTBody();

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

    /* --- UTILIDADES --- */
    const codigo = id => `DON-${String(id).padStart(3, '0')}`;

    /* --- CARGA DE DATOS --- */
    // GET /inventario (totales) + GET /inventario/{id} por cada tipo (unidades)
    async function cargarInventario() {
        const res = await api('inventario');
        if (!res.ok) {
            const msg = mensajeError(res);
            panelStock.innerHTML = `<p class="text">${esc(msg)}</p>`
            cuerpoInventario.innerHTML = filaMensaje(6, msg);
            cuerpoBajo.innerHTML = filaMensaje(4, msg);
            cuerpoVence.innerHTML = filaMensaje(4, msg);
            return;
        }
        resumen = res.data.inventario ?? [];

        // Una sola petición con todas las unidades (ya traen id_tipo_sangre y tipo_sangre)
        const resUnidades = await api('inventario/unidades');
        if (!resUnidades.ok) {
            const msg = mensajeError(resUnidades);
            cuerpoInventario.innerHTML = filaMensaje(6, msg);
            cuerpoVence.innerHTML = filaMensaje(4, msg);
            return;
        }
        unidades = resUnidades.data.unidades ?? [];

        llenarFiltroTipo();
        pintarStock();
        pintarBusqueda();
        pintarAlertas();
    }

    /* --- VER STOCK --- */
    function pintarStock() {
        if (!resumen.length) {
            panelStock.innerHTML = '<p class="text">Sin datos de inventario</p>';
            return;
        }
        panelStock.innerHTML = resumen.map(t => `
            <article class="card">
                <h3 class="card-value">${esc(t.tipo_sangre)}</h3>
                <p class="subtext">Disponibles: <b>${Number(t.disponibles)}</b></p>
                <p class="subtext">Vencidas: <b>${Number(t.vencidas)}</b></p>
            </article>`).join('');
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

    /* --- INICIO --- */
    // Abrir un panel desde la URL
    window.addEventListener('load', () => {
        const panel = location.hash.slice(1);
        if (panel) cambiarPanel(document.querySelector('.page-body'), panel);
    });

    cargarInventario();
})();