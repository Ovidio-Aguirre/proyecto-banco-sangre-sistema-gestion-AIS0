/* --- CLIENTE DE LA API (compartido por los módulos) --- */

// La API vive en api/ junto a index.php, funciona sin importar la carpeta del proyecto
const API_URL = new URL('api/', window.location.href).pathname;

// Tipos de sangre (mismos ids que la tabla tipo_sangre)
const TIPOS_SANGRE = {
    1: 'A+', 2: 'A-', 3: 'B+', 4: 'B-',
    5: 'AB+', 6: 'AB-', 7: 'O+', 8: 'O-'
};

/**
 * Hace una petición a la API y devuelve { ok, status, data }.
 * Nunca lanza error: si el servidor no responde, ok = false.
 */
async function api(ruta, { metodo = 'GET', cuerpo = null, params = null } = {}) {
    let url = API_URL + ruta;
    if (params) {
        const query = new URLSearchParams(params).toString();
        if (query) url += '?' + query;
    }

    const opciones = { method: metodo, headers: {} };
    if (cuerpo !== null) {
        opciones.headers['Content-Type'] = 'application/json';
        opciones.body = JSON.stringify(cuerpo);
    }

    try {
        const respuesta = await fetch(url, opciones);
        const data = await respuesta.json().catch(() => ({}));
        return { ok: respuesta.ok, status: respuesta.status, data };
    } catch (error) {
        return { ok: false, status: 0, data: { mensaje: 'No se pudo conectar con el servidor' } };
    }
}

// Saca el texto de error de una respuesta (la API no usa siempre la misma clave)
function mensajeError(res) {
    const d = res.data || {};
    if (d.campos) return `${d.mensaje}: ${d.campos.join(', ')}`;
    return d.mensaje || d.Mensaje || d['Mensaje:'] || d.error || 'Ocurrió un error';
}

// Escapa texto antes de meterlo en innerHTML
function esc(valor) {
    const div = document.createElement('div');
    div.textContent = valor ?? '';
    return div.innerHTML;
}

// Llena un <select> con opciones { valor: texto }
function llenarSelect(select, opciones, textoTodos = null) {
    select.innerHTML = '';
    if (textoTodos !== null) {
        select.add(new Option(textoTodos, ''));
    } else {
        const vacio = new Option('Seleccione', '', true, true);
        vacio.disabled = true;
        vacio.hidden = true;
        select.add(vacio);
    }
    Object.entries(opciones).forEach(([valor, texto]) => select.add(new Option(texto, valor)));
}

// Fila que ocupa toda la tabla (mensajes de "sin resultados", etc.)
function filaMensaje(columnas, texto) {
    return `<tr><td colspan="${columnas}" style="text-align:center">${esc(texto)}</td></tr>`;
}
