/**
 * Diálogos modales.
 *
 * El elemento `<dialog>` ya resuelve lo difícil: foco atrapado, capa superior,
 * cierre con Escape y devolución del foco al disparador. Este módulo agrega
 * solo lo que el navegador no hace:
 *
 *   - abrir desde cualquier botón con `data-modal-open="id"`;
 *   - cerrar con `data-modal-close`;
 *   - cerrar al hacer clic fuera del contenido.
 *
 * Se usa `showModal()` y no `show()`: con `show()` el diálogo no queda en la
 * capa superior ni bloquea la interacción con el resto del documento.
 */
export function initModals() {
  document.querySelectorAll('[data-modal-open]').forEach((disparador) => {
    disparador.addEventListener('click', () => {
      const dialogo = document.getElementById(disparador.dataset.modalOpen);

      if (dialogo && typeof dialogo.showModal === 'function' && !dialogo.open) {
        dialogo.showModal();
      }
    });
  });

  document.querySelectorAll('dialog[data-modal]').forEach((dialogo) => {
    dialogo.querySelectorAll('[data-modal-close]').forEach((boton) => {
      boton.addEventListener('click', () => dialogo.close());
    });

    // El clic en el fondo llega con el propio <dialog> como destino, porque el
    // contenido lo cubre por completo.
    dialogo.addEventListener('click', (evento) => {
      if (evento.target === dialogo) {
        dialogo.close();
      }
    });
  });
}
