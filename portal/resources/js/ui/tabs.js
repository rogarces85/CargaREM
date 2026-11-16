/**
 * Pestañas accesibles.
 *
 * Marcado esperado:
 *
 *   <div data-tabs>
 *     <div role="tablist" aria-label="…">
 *       <button role="tab" data-tab="series" aria-controls="panel-series"
 *               aria-selected="true" tabindex="0">Series</button>
 *       …
 *     </div>
 *     <div role="tabpanel" data-panel="series" id="panel-series" tabindex="0">…</div>
 *   </div>
 *
 * La primera pestaña queda seleccionada si ninguna lo está. Se respeta el
 * patrón de teclado WAI-ARIA: flechas para moverse, Home y End a los extremos,
 * y una sola pestaña en el orden de tabulación (`tabindex` 0 / -1).
 */
export function initTabs() {
  document.querySelectorAll('[data-tabs]').forEach((contenedor) => {
    const pestanas = Array.from(contenedor.querySelectorAll('[data-tab]'));
    const paneles = Array.from(contenedor.querySelectorAll('[data-panel]'));

    if (pestanas.length === 0) {
      return;
    }

    const activar = (objetivo, moverFoco = true) => {
      pestanas.forEach((pestana) => {
        const activa = pestana === objetivo;

        pestana.setAttribute('aria-selected', String(activa));
        pestana.tabIndex = activa ? 0 : -1;
      });

      paneles.forEach((panel) => {
        panel.hidden = panel.dataset.panel !== objetivo.dataset.tab;
      });

      if (moverFoco) {
        objetivo.focus();
      }
    };

    pestanas.forEach((pestana) => {
      pestana.addEventListener('click', () => activar(pestana, false));

      pestana.addEventListener('keydown', (evento) => {
        const indice = pestanas.indexOf(pestana);
        let destino = null;

        switch (evento.key) {
          case 'ArrowRight':
            destino = pestanas[(indice + 1) % pestanas.length];
            break;
          case 'ArrowLeft':
            destino = pestanas[(indice - 1 + pestanas.length) % pestanas.length];
            break;
          case 'Home':
            destino = pestanas[0];
            break;
          case 'End':
            destino = pestanas[pestanas.length - 1];
            break;
          default:
            return;
        }

        evento.preventDefault();
        activar(destino);
      });
    });

    const yaHayUna = pestanas.some((p) => p.getAttribute('aria-selected') === 'true');

    activar(yaHayUna ? pestanas.find((p) => p.getAttribute('aria-selected') === 'true') : pestanas[0], false);
  });
}
