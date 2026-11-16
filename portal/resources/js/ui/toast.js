/**
 * Avisos efímeros.
 *
 * Origen de los mensajes:
 *   1. Descargas del servidor: `session('ok')` y `$errors`. El layout los deja
 *      en `[data-flashes]` como hijos con `data-type` y `data-message`.
 *   2. Acciones del cliente: un elemento con `data-toast` y opcionalmente
 *      `data-toast-type` dispara un aviso al hacer clic.
 *   3. Código: `window.remToast('mensaje', 'error')`.
 *
 * Notyf se carga aparte con `import()`. Son ~7 KB que la mayoría de las
 * páginas no necesita hasta que hay algo que anunciar, y en esta aplicación
 * hay algo que anunciar en casi todas… pero sigue valiendo la pena porque el
 * primer mensaje casi nunca aparece antes de una interacción.
 */
let notyf = null;

const TYPES = {
  success: { duration: 4500 },
  error: { duration: 8000, dismissible: true },
  info: { duration: 5000 },
};

async function instance() {
  if (notyf) {
    return notyf;
  }

  const { Notyf } = await import('notyf');

  notyf = new Notyf({
    duration: 4500,
    ripple: false,
    dismissible: false,
    position: { x: 'right', y: 'top' },
    types: Object.entries(TYPES).map(([type, opciones]) => ({ type, ...opciones })),
  });

  return notyf;
}

/**
 * Muestra un aviso. No lanza si Notyf falla al cargar: un aviso que no aparece
 * no debe romper la página que el usuario vino a usar.
 */
export async function toast(message, type = 'success') {
  if (!message) {
    return;
  }

  try {
    const avisos = await instance();

    if (typeof avisos[type] === 'function') {
      avisos[type](message);
    } else {
      avisos.success(message);
    }
  } catch (error) {
    console.error('[avisos] no se pudo cargar Notyf', error);
  }
}

export function initToasts() {
  window.remToast = toast;

  // 1. Mensajes que vienen del servidor.
  const contenedor = document.querySelector('[data-flashes]');

  if (contenedor) {
    Array.from(contenedor.querySelectorAll('[data-message]')).forEach((nodo, indice) => {
      // Escalonar evita que dos avisos se apilen encima y se tapan.
      window.setTimeout(() => {
        toast(nodo.dataset.message, nodo.dataset.type || 'success');
      }, indice * 250);
    });
  }

  // 2. Mensajes disparados por un clic.
  document.querySelectorAll('[data-toast]').forEach((elemento) => {
    elemento.addEventListener('click', () => {
      toast(elemento.dataset.toast, elemento.dataset.toastType || 'success');
    });
  });
}
