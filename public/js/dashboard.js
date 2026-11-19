const filtroGlobal = document.getElementById('period-filter');
const etiquetaPeriodo = document.getElementById('period-value');

const ETIQUETAS_PERIODO = {
    'hoy': 'Hoy',
    'semanal': 'Esta semana',
    'mensual': 'Este mes',
    '3-meses': 'Últimos 3 meses',
    '6-meses': 'Últimos 6 meses',
    'anual': 'Este año'
};
const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const graficoSangre = crearGrafico({
    idCanvas: 'blood-chart',
    etiquetas: [],
    datos: [],
    label: 'Unidades',
    tipo: 'bar',
    orientacion: 'horizontal',
    tituloEjeX: 'Cantidad de unidades',
    tituloEjeY: 'Tipo de sangre'
});

const graficoDonaciones = crearGrafico({
    idCanvas: 'donations-chart',
    etiquetas: [],
    datos: [],
    label: 'Donaciones',
    tipo: 'line',
    tituloEjeX: 'Fecha',
    tituloEjeY: 'Cantidad de donaciones'
});

/* --- CARGA DE DATOS --- */
// Unidades disponibles por tipo (histórico, no depende del filtro)
function pintarGraficoInventario(resumen) {
    if (!graficoSangre) return;

    graficoSangre.data.labels = resumen.map(t => t.tipo_sangre);
    graficoSangre.data.datasets[0].data = resumen.map(t => Number(t.disponibles));
    graficoSangre.update();
}

// Tarjetas y tendencia, según el periodo elegido
async function cargarPeriodo() {
    const periodo = filtroGlobal.value;
    etiquetaPeriodo.textContent = ETIQUETAS_PERIODO[periodo] ?? '';

    const [resResumen, resTendencia] = await Promise.all([
        api('reportes/resumen', { params: { periodo } }),
        api('reportes/donaciones-mensuales', { params: { periodo } })
    ]);

    if (resResumen.ok) {
        const r = resResumen.data.resumen;
        document.getElementById('stat-donantes').textContent = r.donantes;
        document.getElementById('stat-donaciones').textContent = r.donaciones;
        document.getElementById('stat-solicitudes').textContent = r.solicitudes.total;
        document.getElementById('stat-atendidas').textContent = r.solicitudes.atendidas;
    }

    if (resTendencia.ok && graficoDonaciones) {
        const serie = resTendencia.data.donaciones ?? [];
        const porDia = serie.length > 0 && serie[0].fecha.length === 10;

        graficoDonaciones.data.labels = serie.map(p => formatearFecha(p.fecha));
        graficoDonaciones.data.datasets[0].data = serie.map(p => p.total);
        graficoDonaciones.options.scales.x.title.text = porDia ? 'Día' : 'Mes';
        graficoDonaciones.options.scales.x.title.display = true;
        graficoDonaciones.update();
    }
}

// Mismos umbrales que inventario.js
const STOCK_MINIMO = 5;
const DIAS_ALERTA = 7;
const MAX_ALERTAS = 5;

// Alertas de inventario
function pintarAlertas(resumen, unidades) {
    const tabla = document.getElementById('alerts-table');
    if (!tabla) return;
    const cuerpo = tabla.tBodies[0] ?? tabla.createTBody();

    const codigo = id => `DON-${String(id).padStart(3, '0')}`;
    const textoDias = n => n === 0 ? 'hoy' : n === 1 ? 'en 1 día' : `en ${n} días`;

    const vencimientos = unidades
        .filter(u => u.estado !== 'VENCIDA' && Number(u.dias_para_vencer) <= DIAS_ALERTA)
        .sort((a, b) => Number(a.dias_para_vencer) - Number(b.dias_para_vencer))
        .map(u => ({
            categoria: 'Vencimiento',
            texto: `${codigo(u.id_donacion)} (${u.tipo_sangre}) vence ${textoDias(Number(u.dias_para_vencer))}`
        }));

    const bajos = resumen
        .filter(t => Number(t.disponibles) < STOCK_MINIMO)
        .sort((a, b) => Number(a.disponibles) - Number(b.disponibles))
        .map(t => ({
            categoria: 'Inventario',
            texto: Number(t.disponibles) === 0
                ? `${t.tipo_sangre} agotado (0/${STOCK_MINIMO})`
                : `${t.tipo_sangre} por debajo del mínimo (${Number(t.disponibles)}/${STOCK_MINIMO})`
        }));

    const alertas = [...vencimientos, ...bajos].slice(0, MAX_ALERTAS);

    cuerpo.innerHTML = alertas.length
        ? alertas.map(a => `
            <tr class="subtext">
                <td>${esc(a.categoria)}</td>
                <td>${esc(a.texto)}</td>
            </tr>`).join('')
        : filaMensaje(2, 'Sin alertas de inventario');
}

// Una sola carga de inventario para el gráfico y las alertas
async function cargarInventario() {
    const [resStock, resUnidades] = await Promise.all([
        api('inventario'),
        api('inventario/unidades')
    ]);

    const cuerpoAlertas = document.getElementById('alerts-table')?.tBodies[0];

    if (!resStock.ok) {
        if (cuerpoAlertas) cuerpoAlertas.innerHTML = filaMensaje(2, mensajeError(resStock));
        return;
    }

    const resumen = resStock.data.inventario ?? [];
    pintarGraficoInventario(resumen); // el gráfico solo necesita el resumen

    if (!resUnidades.ok) {
        if (cuerpoAlertas) cuerpoAlertas.innerHTML = filaMensaje(2, mensajeError(resUnidades));
        return;
    }

    pintarAlertas(resumen, resUnidades.data.unidades ?? []);
}

// 'YYYY-MM-DD' -> 'dd/mm' | 'YYYY-MM' -> 'Mes'
function formatearFecha(fecha) {
    const [anio, mes, dia] = fecha.split('-');
    return dia ? `${dia}/${mes}` : `${MESES[Number(mes) - 1]} ${anio}`;
}

if (filtroGlobal && etiquetaPeriodo) {
    filtroGlobal.addEventListener('change', cargarPeriodo);
    cargarPeriodo();
    cargarInventario();
}

// ---- UTILIDADES ----
function crearGrafico({
    idCanvas,
    etiquetas,
    datos,
    label,
    tipo = 'bar',
    orientacion = 'vertical',
    color = '#e02b20',
    tituloEjeX = '',
    tituloEjeY = '',
    mostrarLeyenda = false
}) {
    const canvas = document.getElementById(idCanvas);

    if (!canvas) return null;

    return new Chart(canvas, {
        type: tipo,

        data: {
            labels: etiquetas,
            datasets: [{
                label: label,
                data: datos,
                backgroundColor: tipo === 'line'
                    ? `${color}1A`
                    : color,
                borderRadius: tipo === 'bar' ? 10 : 0,
                maxBarThickness: 25,
                fill: tipo === 'line',
                tension: tipo === 'line' ? 0.3 : 0,
                pointRadius: tipo === 'line' ? 4 : 0,
                pointHoverRadius: tipo === 'line' ? 6 : 0,
                pointHitRadius: tipo === 'line' ? 12 : 0,
                pointBackgroundColor: color
            }]
        },
        
        /*plugins: [ChartDataLabels],*/

        options: {
            indexAxis: orientacion === 'horizontal' ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: mostrarLeyenda
                },
                /*datalabels: {
                    anchor: 'end',
                    align: 'end',
                    color: '#6f726f',
                    clip: false,
                    formatter: (valor) => valor
                }*/
            },
        
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    title: {
                        display: tituloEjeX !== '',
                        text: tituloEjeX
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    title: {
                        display: tituloEjeY !== '',
                        text: tituloEjeY
                    }
                }
            },
        }
    });
}