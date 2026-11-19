/* --- MÓDULO ACTIVIDAD --- */
(function () {
    const tabla = document.querySelector('#activity-table');
    if (!tabla) return;

    const cuerpo = tabla.tBodies[0] ?? tabla.createTBody();
    const inputBuscar = document.getElementById('buscar');
    const inputDesde = document.getElementById('desde');
    const inputHasta = document.getElementById('hasta');
    const btnBuscar = document.getElementById('activity-search');
    const btnLimpiar = document.getElementById('activity-clear');
    const btnPrev = document.getElementById('activity-prev');
    const btnNext = document.getElementById('activity-next');
    const info = document.getElementById('activity-info');

    const POR_PAGINA = 20;
    let pagina = 1;
    let paginas = 1;

    async function cargar() {
        const params = { pagina, por_pagina: POR_PAGINA };
        const texto = inputBuscar.value.trim();
        if (texto) params.busqueda = texto;
        if (inputDesde.value) params.desde = inputDesde.value;
        if (inputHasta.value) params.hasta = inputHasta.value;

        const res = await api('reportes/actividad', { params });
        if (!res.ok) {
            cuerpo.innerHTML = filaMensaje(5, mensajeError(res));
            return;
        }

        const actividad = res.data.actividad ?? [];
        paginas = Math.max(res.data.paginas ?? 1, 1);

        cuerpo.innerHTML = actividad.length
            ? actividad.map(a => `
                <tr class="text">
                    <td>${esc(a.fecha)}</td>
                    <td>${esc(a.hora)}</td>
                    <td>${esc(a.nombre_usuario ?? '—')}</td>
                    <td>${esc(a.accion)}</td>
                    <td>${esc(a.descripcion)}</td>
                </tr>`).join('')
            : filaMensaje(5, 'No se encontraron acciones');

        info.textContent = `Página ${pagina} de ${paginas} (${res.data.total ?? 0} acciones)`;
        btnPrev.disabled = pagina <= 1;
        btnNext.disabled = pagina >= paginas;
    }

    function buscar() {
        pagina = 1;
        cargar();
    }

    btnBuscar.addEventListener('click', buscar);
    inputBuscar.addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });
    inputDesde.addEventListener('change', buscar);
    inputHasta.addEventListener('change', buscar);
    btnLimpiar.addEventListener('click', () => {
        inputBuscar.value = '';
        inputDesde.value = '';
        inputHasta.value = '';
        buscar();
    });
    btnPrev.addEventListener('click', () => { if (pagina > 1) { pagina--; cargar(); } });
    btnNext.addEventListener('click', () => { if (pagina < paginas) { pagina++; cargar(); } });

    cargar();
})();