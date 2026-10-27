const filtroGlobal = document.getElementById('period-filter');
const etiquetaPeriodo = document.getElementById('period-value');


if (filtroGlobal && etiquetaPeriodo) {
    filtroGlobal.addEventListener('change', actualizarPeriodoGrafica);
    actualizarPeriodoGrafica();
}

crearGrafico({
    idCanvas: 'blood-chart',
    etiquetas: ['A+','A-','B+','B-','AB+','AB-','O+', 'O-'],
    datos: [48, 12, 36, 8, 14, 4, 55, 18],
    label: 'Unidades',
    tipo: 'bar',
    orientacion: 'horizontal',
    tituloEjeX: 'Cantidad de unidades',
    tituloEjeY: 'Tipo de sangre'
});

crearGrafico({
    idCanvas: 'donations-chart',
    etiquetas: ['Ago','Sept','Oct'],
    datos: [48, 12, 26],
    label: 'Donaciones',
    tipo: 'line',
    tituloEjeX: 'Mes',
    tituloEjeY: 'Cantidad de donaciones'
});

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

    if (!canvas) return;

    new Chart(canvas, {
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

function obtenerPeriodoGrafica(periodo) {
    switch (periodo) {
        case '6-meses':
            return {
                tipo: 'ultimos-meses',
                meses: 6,
                etiqueta: 'Últimos 6 meses'
            };

        case 'anual':
            return {
                tipo: 'anio-actual',
                etiqueta: 'Este año'
            };

        default:
            return {
                tipo: 'ultimos-meses',
                meses: 3,
                etiqueta: 'Últimos 3 meses'
            };
    }
}

function actualizarPeriodoGrafica() {
    const periodo = obtenerPeriodoGrafica(filtroGlobal.value);

    etiquetaPeriodo.textContent = periodo.etiqueta;

    // Aquí actualizas los datos de la gráfica
    // utilizando periodo.tipo y periodo.meses.
}