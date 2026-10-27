/* --- BOTONES DE NAV --- */
document.querySelectorAll(".page-body").forEach(container => {
    container.querySelectorAll("[data-target]").forEach(boton => {
        boton.addEventListener("click", () => {
            cambiarPanel(container, boton.dataset.target);
        });
    });
});


/* --- FORMS --- */
const inputDui = document.getElementById('dui');
const telefono = document.getElementById('telefono');

if (dui) aplicarMascara(dui, 8, 9);
if (telefono) aplicarMascara(telefono, 4, 8);


/* --- UTILIDADES --- */

// Aplicar formato a campos
function aplicarMascara(input, ubiMask, longitud) {
    input.addEventListener('input', function () {
        const digitos = this.value
            .replace(/\D/g, '')
            .slice(0, longitud);

        this.value = digitos.length > ubiMask
            ? digitos.slice(0, ubiMask) + '-' +
              digitos.slice(ubiMask)
            : digitos;
    });
}

// Mostrar panel segun boton
function cambiarPanel(container, target) {
    const panelDestino = container.querySelector(`#${target}`);

    if (!panelDestino) return;

    container.querySelectorAll(".page-button").forEach(boton => {
        boton.classList.toggle(
            "active",
            boton.dataset.target === target
        );
    });

    container.querySelectorAll(".page-panel").forEach(panel => {
        panel.classList.toggle("active", panel.id === target);
    });
}