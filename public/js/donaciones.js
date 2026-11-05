/* --- MÓDULO DONACIONES --- */
(function () {
    const tabla = document.querySelector('#donation-table');
    if (!tabla) return;

    const cuerpo = tabla.tBodies[0] ?? tabla.createTBody();
    const contenedor = document.querySelector('.page-body');

    // Formularios
    const formEvaluar = document.querySelector('#evaluar form');
    const formRegistrar = document.querySelector('#registrar form');

    // Filtros del historial
    const inputDesde = document.getElementById('desde');
    const inputHasta = document.getElementById('hasta');
    const selectEstado = document.getElementById('buscar-estado');
    const btnBuscar = document.querySelector('#donation-header .main-button');

    // Selects de los formularios
    const selectDonante = document.getElementById('donante');
    const selectEvaluacion = document.getElementById('evaluacion');

    // Estados reales del backend (DonacionController::ESTADOS)
    const ESTADOS = {
        EN_EVALUACION: 'En evaluación',
        DISPONIBLE: 'Disponible',
        RESERVADA: 'Reservada',
        UTILIZADA: 'Utilizada',
        VENCIDA: 'Vencida',
        DESCARTADA: 'Descartada'
    };

    // Acciones válidas por estado (según Donacion::TRANSICIONES y los métodos del modelo)
    const ACCIONES = {
        EN_EVALUACION: [['disponible', 'Disponible'], ['descartar', 'Descartar'], ['reasignar', 'Reasignar']],
        DISPONIBLE:    [['vincular', 'Vincular'], ['reasignar', 'Reasignar']],
        RESERVADA:     [['utilizar', 'Utilizar'], ['desvincular', 'Desvincular']],
        VENCIDA:       [['descartar', 'Descartar']],
        DESCARTADA:    [],
        UTILIZADA:     []
    };

    let evaluacionesAptas = []; // [{ id_evaluacion, value, texto }]

    /* --- UTILIDADES --- */
    const pad = n => String(n).padStart(2, '0');
    const fechaLocal = (f = new Date()) =>
        `${f.getFullYear()}-${pad(f.getMonth() + 1)}-${pad(f.getDate())}`;

    function fechaCaducidad() {
        const f = new Date();
        f.setDate(f.getDate() + 42); // Igual que el modelo: recolección + 42 días
        return fechaLocal(f);
    }

    function llenarOpciones(select, opciones, placeholder = 'Seleccione') {
        select.innerHTML =
            `<option value="" disabled selected hidden>${placeholder}</option>` +
            opciones.map(o => `<option value="${esc(o.value)}">${esc(o.texto)}</option>`).join('');
    }

    function celdaAcciones(estado) {
        const acciones = ACCIONES[estado] ?? [];
        if (!acciones.length) return '—';
        return `<article>${acciones.map(([accion, texto]) =>
            `<button class="table-button" data-accion="${accion}">${texto}</button>`).join('')}</article>`;
    }

    // Filtro de estado con los valores reales del backend ("Todos" seleccionable)
    selectEstado.innerHTML =
        '<option value="">Todos</option>' +
        Object.entries(ESTADOS).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');

    function prepararFormEvaluar() {
        formEvaluar.reset();
        // La fecha la asigna el backend (CURDATE), aquí solo se muestra
        formEvaluar.fecha_evaluacion.value = fechaLocal();
        formEvaluar.fecha_evaluacion.readOnly = true;
        // El backend solo acepta APTO / NO_APTO
        formEvaluar.resultado.querySelector('option[value="PENDIENTE"]')?.remove();
    }

    function prepararFormRegistrar() {
        // Solo informativos: el backend calcula fechas y estado inicial
        formRegistrar.fecha_recoleccion.value = fechaLocal();
        formRegistrar.fecha_recoleccion.readOnly = true;
        formRegistrar.fecha_caducidad.value = fechaCaducidad();
        formRegistrar.fecha_caducidad.readOnly = true;
        formRegistrar.estado.value = ESTADOS.EN_EVALUACION;
    }

    /* --- LISTAR (historial de donaciones) --- */
    async function cargarDonaciones() {
        const params = {};
        if (inputDesde.value) params.fecha_ini = inputDesde.value;
        if (inputHasta.value) params.fecha_fin = inputHasta.value;
        if (selectEstado.value) params.estado = selectEstado.value;

        const res = await api('donaciones', { params });
        if (!res.ok) {
            cuerpo.innerHTML = filaMensaje(8, mensajeError(res));
            return;
        }

        const donaciones = res.data.donaciones ?? [];
        if (donaciones.length === 0) {
            cuerpo.innerHTML = filaMensaje(8, 'No se encontraron donaciones');
            return;
        }

        cuerpo.innerHTML = donaciones.map(d => `
            <tr class="text" data-id="${d.id_donacion}">
                <td>DON-${String(d.id_donacion).padStart(3, '0')}</td>
                <td>${esc(d.nombre)} ${esc(d.apellido)}</td>
                <td><span class="pill">${esc(TIPOS_SANGRE[d.id_tipo_sangre])}</span></td>
                <td>${esc(d.fecha_recoleccion)}</td>
                <td>${esc(d.fecha_caducidad)}</td>
                <td>${esc(ESTADOS[d.estado] ?? d.estado)}</td>
                <td>${esc(d.registrado_por ?? '—')}</td>
                <td>${celdaAcciones(d.estado)}</td>
            </tr>`).join('');
    }

    btnBuscar.addEventListener('click', cargarDonaciones);
    [inputDesde, inputHasta, selectEstado].forEach(el => el.addEventListener('change', cargarDonaciones));

    /* --- DONANTES PARA EVALUAR --- */
    async function cargarDonantes() {
        const res = await api('donantes'); // el backend ya devuelve solo activos
        if (!res.ok) return alert(mensajeError(res));

        llenarOpciones(selectDonante, (res.data.donantes ?? []).map(d => ({
            value: d.id_donante,
            texto: `${d.nombre} ${d.apellido} — ${d.dui}`
        })));
    }

    /* --- EVALUACIONES APTAS DISPONIBLES PARA DONAR --- */
    async function cargarEvaluacionesAptas() {
        const res = await api('donaciones/elegibles');
        if (!res.ok) return alert(mensajeError(res));

        evaluacionesAptas = (res.data.evaluaciones ?? []).map(ev => ({
            id_evaluacion: ev.id_evaluacion, // para PATCH /donaciones/{id}/reasignar
            value: ev.id_donante,            // para POST /donaciones
            texto: `EVA-${String(ev.id_evaluacion).padStart(3, '0')} · ${ev.nombre} ${ev.apellido} (${ev.dui})`
        }));

        llenarOpciones(selectEvaluacion, evaluacionesAptas);
    }

    /* --- GUARDAR EVALUACIÓN --- */
    formEvaluar.addEventListener('submit', async e => {
        e.preventDefault();

        const resultado = formEvaluar.resultado.value;
        const motivo = formEvaluar.motivo.value.trim();

        if (resultado === 'NO_APTO' && !motivo) {
            return alert('El motivo es obligatorio si el donante no es apto');
        }

        // Sin fecha_evaluacion: la asigna el backend
        const datos = {
            peso: Number(formEvaluar.peso.value),
            altura: Number(formEvaluar.altura.value),
            resultado
        };
        if (motivo) datos.motivo = motivo;

        const res = await api(`donantes/${selectDonante.value}/evaluaciones`, { metodo: 'POST', cuerpo: datos });
        if (!res.ok) return alert(mensajeError(res));

        alert(`Evaluación EVA-${String(res.data.id_evaluacion).padStart(3, '0')} registrada`);
        prepararFormEvaluar();

        if (resultado === 'APTO') {
            await cargarEvaluacionesAptas();
            cambiarPanel(contenedor, 'registrar');
        } else {
            cambiarPanel(contenedor, 'historial');
        }
    });

    /* --- REGISTRAR DONACIÓN --- */
    formRegistrar.addEventListener('submit', async e => {
        e.preventDefault();

        const res = await api('donaciones', {
            metodo: 'POST',
            cuerpo: { id_donante: Number(selectEvaluacion.value) }
        });
        if (!res.ok) return alert(mensajeError(res));

        alert(`Donación DON-${String(res.data.id_donacion).padStart(3, '0')} registrada`);
        formRegistrar.reset();
        prepararFormRegistrar();
        await Promise.all([cargarDonaciones(), cargarEvaluacionesAptas()]);
        cambiarPanel(contenedor, 'historial');
    });

    /* --- ACCIONES DE LA TABLA --- */
    cuerpo.addEventListener('click', async e => {
        const boton = e.target.closest('[data-accion]');
        if (!boton) return;

        const id = boton.closest('tr').dataset.id;
        const accion = boton.dataset.accion;
        let res;

        // PUT /donaciones/{id} -> cambio manual de estado
        if (['disponible', 'descartar', 'utilizar'].includes(accion)) {
            const estado = { disponible: 'DISPONIBLE', descartar: 'DESCARTADA', utilizar: 'UTILIZADA' }[accion];
            if (!confirm(`¿Cambiar el estado a "${ESTADOS[estado]}"?`)) return;
            res = await api(`donaciones/${id}`, { metodo: 'PUT', cuerpo: { estado } });
        }

        // PATCH /donaciones/{id}/solicitud -> vincular
        if (accion === 'vincular') {
            const idSolicitud = Number(prompt('ID de la solicitud a vincular:'));
            if (!Number.isInteger(idSolicitud) || idSolicitud <= 0) return;
            res = await api(`donaciones/${id}/solicitud`, { metodo: 'PATCH', cuerpo: { id_solicitud: idSolicitud } });
        }

        // PATCH /donaciones/{id}/solicitud -> desvincular (id_solicitud: null)
        if (accion === 'desvincular') {
            if (!confirm('¿Desvincular la solicitud? La donación volverá a Disponible.')) return;
            res = await api(`donaciones/${id}/solicitud`, { metodo: 'PATCH', cuerpo: { id_solicitud: null } });
        }

        // PATCH /donaciones/{id}/reasignar -> otra evaluación apta del día
        if (accion === 'reasignar') {
            if (!evaluacionesAptas.length) return alert('No hay evaluaciones aptas disponibles para reasignar');

            const lista = evaluacionesAptas.map(ev => ev.texto).join('\n');
            const idEvaluacion = Number(prompt(`Evaluaciones disponibles:\n${lista}\n\nEscribe el número de la evaluación (ej. 5 para EVA-005):`));
            if (!evaluacionesAptas.some(ev => ev.id_evaluacion === idEvaluacion)) return;

            res = await api(`donaciones/${id}/reasignar`, { metodo: 'PATCH', cuerpo: { id_evaluacion: idEvaluacion } });
        }

        if (!res) return;
        if (!res.ok) return alert(mensajeError(res));

        alert(res.data.mensaje || 'Operación realizada');
        await Promise.all([cargarDonaciones(), cargarEvaluacionesAptas()]);
    });

    // Cancelar limpia los formularios
    formEvaluar.querySelector('.sec-button').addEventListener('click', prepararFormEvaluar);
    formRegistrar.querySelector('.sec-button').addEventListener('click', () => {
        formRegistrar.reset();
        prepararFormRegistrar();
    });

    /* --- INICIO --- */
    prepararFormEvaluar();
    prepararFormRegistrar();
    cargarDonaciones();
    cargarDonantes();
    cargarEvaluacionesAptas();
})();