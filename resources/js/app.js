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

/*
 * Asistente de uso (components/asistente.blade.php). El historial vive en
 * sessionStorage y viaja entero en cada pedido: el servidor no guarda nada.
 * La respuesta llega en streaming como texto plano y se pinta con
 * textContent, nunca como HTML, asi que lo que devuelva el modelo no puede
 * inyectar marcado en la pagina.
 */
const asistente = document.getElementById('asistente');

if (asistente) {
    const panel = document.getElementById('asistente-panel');
    const abrirAsistente = document.getElementById('asistente-abrir');
    const lista = document.getElementById('asistente-mensajes');
    const formulario = document.getElementById('asistente-form');
    const texto = document.getElementById('asistente-texto');
    const enviar = formulario.querySelector('button[type="submit"]');
    const { url, token, clave, bienvenida } = asistente.dataset;
    const maxMensajes = Number(asistente.dataset.maxMensajes) || 20;

    const GENERICO = 'No pude responder ahora. Probá de nuevo en un rato.';

    // Los errores de Laravel llegan como JSON; los del asistente, como texto.
    const ERRORES = {
        401: 'Tu sesión terminó. Recargá la página para seguir.',
        419: 'La página estuvo abierta mucho tiempo. Recargá la página para seguir.',
        422: 'No pude procesar ese mensaje. Probá escribirlo más corto o empezá una conversación nueva.',
    };

    class ErrorAsistente extends Error {}

    const leer = () => {
        try {
            const guardado = JSON.parse(sessionStorage.getItem(clave) ?? '[]');
            return Array.isArray(guardado) ? guardado : [];
        } catch {
            return [];
        }
    };

    const guardar = () => {
        try {
            sessionStorage.setItem(clave, JSON.stringify(mensajes));
        } catch {
            // Sin storage (navegacion privada estricta): la charla dura lo que la pagina.
        }
    };

    let mensajes = leer();

    const bajar = () => {
        lista.scrollTop = lista.scrollHeight;
    };

    /*
     * Botones «Ir a …». data-destinos es una lista blanca que arma el servidor
     * (DestinosAsistente): pantallas a las que este usuario puede entrar. Se
     * ofrece un boton por cada direccion de la lista que aparezca en la
     * respuesta; lo que el modelo invente no esta en la lista y no genera nada.
     */
    const destinos = (() => {
        try {
            const lista = JSON.parse(asistente.dataset.destinos ?? '{}');
            return lista && typeof lista === 'object' && !Array.isArray(lista) ? lista : {};
        } catch {
            return {};
        }
    })();

    const MAX_BOTONES = 3;
    const claveAbierto = `${clave}:abierto`;

    const escapar = (literal) => literal.replace(/[.*+?^$()|[\]\\{}]/g, '\\$&');

    // La direccion tiene que estar entera: /admin no matchea dentro de /admin/usuarios.
    const patronDe = (direccion) => new RegExp('(^|[^\\w/])' + escapar(direccion) + '(?![\\w/-])');

    const destinosEn = (contenido) => Object.keys(destinos)
        .map((direccion) => ({ direccion, posicion: contenido.search(patronDe(direccion)) }))
        .filter(({ direccion, posicion }) => posicion >= 0 && direccion !== window.location.pathname)
        .sort((a, b) => a.posicion - b.posicion)
        .slice(0, MAX_BOTONES)
        .map(({ direccion }) => direccion);

    const agregarBotones = (parrafo, contenido) => {
        const encontrados = destinosEn(contenido);

        if (encontrados.length === 0) return;

        const fila = document.createElement('div');
        fila.className = 'flex max-w-[85%] flex-wrap gap-1.5';

        encontrados.forEach((direccion) => {
            const enlace = document.createElement('a');
            enlace.href = direccion;
            enlace.className = 'inline-flex items-center gap-0.5 rounded-full border border-hu-azul/30 bg-white py-1 pl-3 pr-2 '
                + 'text-xs font-semibold text-hu-azul transition-colors hover:border-hu-dorado hover:bg-hu-azul-tenue';
            enlace.textContent = `Ir a ${destinos[direccion]}`;

            const flecha = document.createElement('span');
            flecha.className = 'material-symbols-rounded text-base';
            flecha.setAttribute('aria-hidden', 'true');
            flecha.textContent = 'chevron_right';
            enlace.append(flecha);

            // Al llegar a la otra pantalla, el panel se reabre con la charla.
            enlace.addEventListener('click', () => {
                try {
                    sessionStorage.setItem(claveAbierto, '1');
                } catch {
                    // Sin storage el panel simplemente arranca cerrado.
                }
            });

            fila.append(enlace);
        });

        parrafo.after(fila);
        bajar();
    };

    const burbuja = (rol, contenido, error = false) => {
        const item = document.createElement('li');
        item.className = rol === 'usuario' ? 'flex justify-end' : 'flex flex-col items-start gap-1.5';

        const parrafo = document.createElement('p');
        parrafo.className = rol === 'usuario'
            ? 'max-w-[85%] whitespace-pre-line rounded-2xl rounded-br-sm bg-hu-azul px-3 py-2 text-sm text-white'
            : error
                ? 'max-w-[85%] whitespace-pre-line rounded-2xl rounded-bl-sm bg-red-50 px-3 py-2 text-sm text-red-700'
                : 'max-w-[85%] whitespace-pre-line rounded-2xl rounded-bl-sm bg-hu-gris-tenue px-3 py-2 text-sm text-hu-gris';
        parrafo.textContent = contenido;

        item.append(parrafo);
        lista.append(item);
        bajar();

        return parrafo;
    };

    const pintar = () => {
        lista.replaceChildren();
        burbuja('asistente', bienvenida);
        mensajes.forEach((mensaje) => {
            const parrafo = burbuja(mensaje.rol, mensaje.texto);

            if (mensaje.rol === 'asistente') agregarBotones(parrafo, mensaje.texto);
        });
    };

    const mostrarAsistente = (visible) => {
        panel.hidden = !visible;
        abrirAsistente.setAttribute('aria-expanded', String(visible));

        if (visible) {
            bajar();
            texto.focus();
        }
    };

    // Lo que viaja: los ultimos turnos, empezando siempre por el usuario.
    const historial = () => {
        let recorte = mensajes.slice(-maxMensajes);

        while (recorte.length > 0 && recorte[0].rol !== 'usuario') {
            recorte = recorte.slice(1);
        }

        return recorte;
    };

    const preguntar = async (pregunta) => {
        enviar.disabled = true;
        mensajes.push({ rol: 'usuario', texto: pregunta });
        burbuja('usuario', pregunta);
        const respuesta = burbuja('asistente', '…');

        try {
            const pedido = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // JSON para que Laravel conteste 401/419/422 sin redirigir.
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ mensajes: historial() }),
            });

            if (!pedido.ok) {
                const esTexto = (pedido.headers.get('Content-Type') ?? '').startsWith('text/plain');
                const cuerpo = esTexto ? (await pedido.text()).trim() : '';

                throw new ErrorAsistente(ERRORES[pedido.status] ?? (cuerpo || GENERICO));
            }

            const lector = pedido.body.getReader();
            const decodificador = new TextDecoder();
            let acumulado = '';

            for (;;) {
                const { done, value } = await lector.read();

                if (done) break;

                acumulado += decodificador.decode(value, { stream: true });
                respuesta.textContent = acumulado;
                bajar();
            }

            acumulado = (acumulado + decodificador.decode()).trim() || GENERICO;
            respuesta.textContent = acumulado;
            mensajes.push({ rol: 'asistente', texto: acumulado });
            agregarBotones(respuesta, acumulado);
        } catch (error) {
            // La pregunta que fallo sale del historial; queda a la vista para
            // poder copiarla, pero no viaja en el proximo pedido.
            mensajes.pop();
            respuesta.parentElement.remove();
            burbuja('asistente', error instanceof ErrorAsistente ? error.message : GENERICO, true);
        } finally {
            guardar();
            enviar.disabled = false;
            texto.focus();
        }
    };

    abrirAsistente.addEventListener('click', () => mostrarAsistente(panel.hidden));
    asistente.querySelector('[data-asistente-cerrar]').addEventListener('click', () => mostrarAsistente(false));

    asistente.querySelector('[data-asistente-reiniciar]').addEventListener('click', () => {
        mensajes = [];
        guardar();
        pintar();
        texto.focus();
    });

    formulario.addEventListener('submit', (e) => {
        e.preventDefault();
        const pregunta = texto.value.trim();

        if (!pregunta || enviar.disabled) return;

        texto.value = '';
        preguntar(pregunta);
    });

    // Enter envia; Shift+Enter baja de linea.
    texto.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            formulario.requestSubmit();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) mostrarAsistente(false);
    });

    pintar();

    // Se llego aca desde un boton «Ir a …»: el panel sigue abierto.
    try {
        if (sessionStorage.getItem(claveAbierto) === '1') {
            sessionStorage.removeItem(claveAbierto);
            mostrarAsistente(true);
        }
    } catch {
        // Sin storage, arranca cerrado.
    }
}
