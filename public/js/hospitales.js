/* --- MÓDULO HOSPITALES --- */
(function () {
    const tabla = document.querySelector('#hospital-table');
    if (!tabla) return;

    const cuerpo = tabla.tBodies[0] ?? tabla.createTBody();
    const form = document.querySelector('#registrar form');
    const inputBuscar = document.getElementById('buscar');
    const selectFiltroDepto = document.getElementById('buscar-departamento');
    const selectDepto = document.getElementById('departamento');
    const selectMunicipio = document.getElementById('municipio');
    const selectDistrito = document.getElementById('distrito');
    const btnBuscar = document.querySelector('#hospital-header .main-button');
    const titulo = document.querySelector('#registrar .title');
    const btnGuardar = form.querySelector('[type="submit"]');
    const contenedor = document.querySelector('.page-body');

    let idEditando = null; // null = registrando uno nuevo

    /* --- DIVISIÓN TERRITORIAL (catálogo en geo.js) --- */
    const aMapa = (lista, filtro = () => true) =>
        Object.fromEntries(lista.filter(filtro).map(fila => [fila[0], fila[fila.length - 1]]));

    const deptos = aMapa(GEO.departamentos);
    const distritoPorId = Object.fromEntries(GEO.distritos.map(([id, idMun, nombre]) => [id, { nombre, idMun }]));
    const municipioPorId = Object.fromEntries(GEO.municipios.map(([id, idDep, nombre]) => [id, { nombre, idDep }]));

    llenarSelect(selectFiltroDepto, deptos, 'Todos');
    llenarSelect(selectDepto, deptos);
    llenarSelect(selectMunicipio, {});
    llenarSelect(selectDistrito, {});

    // Al elegir departamento se cargan sus municipios, y al elegir municipio sus distritos
    function cargarMunicipios(idDepto) {
        llenarSelect(selectMunicipio, aMapa(GEO.municipios, m => m[1] === Number(idDepto)));
        llenarSelect(selectDistrito, {});
    }

    function cargarDistritos(idMunicipio) {
        llenarSelect(selectDistrito, aMapa(GEO.distritos, d => d[1] === Number(idMunicipio)));
    }

    selectDepto.addEventListener('change', () => cargarMunicipios(selectDepto.value));
    selectMunicipio.addEventListener('change', () => cargarDistritos(selectMunicipio.value));

    // Nombre del distrito, municipio y departamento a partir del id del distrito
    function ubicacion(idDistrito) {
        const distrito = distritoPorId[idDistrito];
        const municipio = distrito && municipioPorId[distrito.idMun];
        return {
            distrito: distrito?.nombre ?? '—',
            municipio: municipio?.nombre ?? '—',
            departamento: municipio ? deptos[municipio.idDep] : '—'
        };
    }

    /* --- LISTAR --- */
    async function cargarHospitales() {
        const params = {};
        const texto = inputBuscar.value.trim();
        if (texto) params.nombreHospital = texto;
        if (selectFiltroDepto.value) params.departamentoHospital = selectFiltroDepto.value;

        const res = await api('hospitales', { params });
        const hospitales = res.data.Hospitales ?? [];

        // Cuando no hay resultados la API responde 400, se muestra como lista vacía
        if (hospitales.length === 0) {
            const sinResultados = res.status === 400 || res.ok;
            cuerpo.innerHTML = filaMensaje(7, sinResultados ? 'No se encontraron hospitales' : mensajeError(res));
            return;
        }

        cuerpo.innerHTML = hospitales.map(h => {
            const lugar = ubicacion(Number(h.id_distrito));
            return `
            <tr class="text" data-id="${h.id_hospital}">
                <td>${esc(h.nombre_hospital)}</td>
                <td>${esc(lugar.departamento)}</td>
                <td>${esc(lugar.municipio)}</td>
                <td>${esc(lugar.distrito)}</td>
                <td>${esc(h.direccion)}</td>
                <td><span class="pill">Activo</span></td>
                <td>
                    <article>
                        <button class="table-button" data-accion="editar">Editar</button>
                        <button class="table-button" data-accion="eliminar">Eliminar</button>
                    </article>
                </td>
            </tr>`;
        }).join('');
    }

    btnBuscar.addEventListener('click', cargarHospitales);
    inputBuscar.addEventListener('keydown', e => { if (e.key === 'Enter') cargarHospitales(); });
    selectFiltroDepto.addEventListener('change', cargarHospitales);

    /* --- EDITAR / ELIMINAR (botones de la tabla) --- */
    cuerpo.addEventListener('click', async e => {
        const boton = e.target.closest('[data-accion]');
        if (!boton) return;
        const id = boton.closest('tr').dataset.id;

        if (boton.dataset.accion === 'editar') {
            const res = await api(`hospitales/${id}`);
            if (!res.ok) return alert(mensajeError(res));
            const h = res.data.Hospital;

            // Se reconstruyen los selects en cascada hasta el distrito guardado
            const distrito = distritoPorId[h.id_distrito];
            const municipio = municipioPorId[distrito.idMun];

            idEditando = h.id_hospital;
            form.nombre_hospital.value = h.nombre_hospital;
            form.direccion.value = h.direccion;
            selectDepto.value = municipio.idDep;
            cargarMunicipios(municipio.idDep);
            selectMunicipio.value = distrito.idMun;
            cargarDistritos(distrito.idMun);
            selectDistrito.value = h.id_distrito;

            titulo.textContent = 'Editar hospital';
            btnGuardar.textContent = 'ACTUALIZAR HOSPITAL';
            cambiarPanel(contenedor, 'registrar');
        }

        if (boton.dataset.accion === 'eliminar') {
            if (!confirm('¿Eliminar este hospital?')) return;
            const res = await api(`hospitales/${id}`, { metodo: 'DELETE' });
            if (!res.ok) return alert(mensajeError(res));
            cargarHospitales();
        }
    });

    /* --- GUARDAR (crear o actualizar) --- */
    function volverAConsulta() {
        idEditando = null;
        form.reset();
        llenarSelect(selectMunicipio, {});
        llenarSelect(selectDistrito, {});
        titulo.textContent = 'Datos del hospital';
        btnGuardar.textContent = 'GUARDAR HOSPITAL';
        cambiarPanel(contenedor, 'consultar');
    }

    form.addEventListener('submit', async e => {
        e.preventDefault();

        // Nombres que espera la API (la ubicación se guarda solo con el distrito)
        const datos = {
            nombreHospital: form.nombre_hospital.value.trim(),
            direccionHospital: form.direccion.value.trim(),
            distritoHospital: Number(selectDistrito.value)
        };

        const res = idEditando
            ? await api(`hospitales/${idEditando}`, { metodo: 'PUT', cuerpo: datos })
            : await api('hospitales', { metodo: 'POST', cuerpo: datos });

        if (!res.ok) return alert(mensajeError(res));

        alert(idEditando ? 'Hospital actualizado' : 'Hospital registrado');
        volverAConsulta();
        cargarHospitales();
    });

    // Cancelar también limpia el modo edición
    form.querySelector('.sec-button').addEventListener('click', volverAConsulta);

    cargarHospitales();
})();
