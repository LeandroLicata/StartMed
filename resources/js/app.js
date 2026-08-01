/*
 * Unico JS de la aplicacion: abrir y cerrar la barra lateral en pantallas
 * chicas. En escritorio la barra es estatica y esto no hace nada.
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
