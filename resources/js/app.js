/*
 * Barra lateral en pantallas chicas: abrirla y cerrarla. En escritorio la
 * barra es estatica y esto no hace nada.
 */
const barra = document.getElementById('barra-lateral');
const fondo = document.getElementById('fondo-menu');
const abrir = document.getElementById('abrir-menu');

if (barra && fondo && abrir) {
    const mostrar = (visible) => {
        barra.classList.toggle('-translate-x-full', !visible);
        fondo.classList.toggle('hidden', !visible);
        abrir.setAttribute('aria-expanded', String(visible));
    };

    abrir.addEventListener('click', () => mostrar(true));

    document
        .querySelectorAll('[data-cerrar-menu]')
        .forEach((el) => el.addEventListener('click', () => mostrar(false)));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') mostrar(false);
    });
}

/*
 * Confirmacion antes de una accion destructiva, en reemplazo del confirm() del
 * navegador. Un formulario la pide declarando sus textos:
 *
 *     <form method="POST" action="..."
 *           data-confirmar="Se puede reactivar despues."
 *           data-confirmar-titulo="Dar de baja «Hemograma»"
 *           data-confirmar-accion="Dar de baja">
 *
 * <dialog> ya resuelve foco, Escape y fondo inerte, asi que aca solo queda
 * mover el texto y volver a enviar el formulario si el usuario acepta.
 */
const dialogo = document.getElementById('dialogo-confirmar');

if (dialogo && typeof dialogo.showModal === 'function') {
    const titulo = dialogo.querySelector('#dialogo-confirmar-titulo');
    const detalle = dialogo.querySelector('[data-dialogo-detalle]');
    const aceptar = dialogo.querySelector('[data-dialogo-aceptar]');
    const cancelar = dialogo.querySelector('[data-dialogo-cancelar]');

    let pendiente = null;

    document.addEventListener('submit', (e) => {
        const formulario = e.target;

        // `confirmado` lo ponemos nosotros al reenviar: evita el bucle.
        if (!formulario.dataset.confirmar || formulario.dataset.confirmado) {
            return;
        }

        e.preventDefault();
        pendiente = formulario;

        titulo.textContent = formulario.dataset.confirmarTitulo ?? '¿Confirmás la acción?';
        detalle.textContent = formulario.dataset.confirmar;
        aceptar.textContent = formulario.dataset.confirmarAccion ?? 'Confirmar';

        dialogo.showModal();
        cancelar.focus();
    });

    aceptar.addEventListener('click', () => {
        const formulario = pendiente;
        dialogo.close();

        if (formulario) {
            formulario.dataset.confirmado = '1';
            formulario.requestSubmit();
        }
    });

    cancelar.addEventListener('click', () => dialogo.close());

    // Cubre tambien el cierre con Escape, que no pasa por el boton.
    dialogo.addEventListener('close', () => {
        pendiente = null;
    });

    // Click en el fondo oscuro: el target es el propio <dialog>.
    dialogo.addEventListener('click', (e) => {
        if (e.target === dialogo) dialogo.close();
    });
}
