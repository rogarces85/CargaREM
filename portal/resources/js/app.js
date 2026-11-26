/**
 * Punto de entrada del cliente.
 *
 * Requisito de la casa (constitución, principio 0): esto no corre en el
 * servidor. Vite lo compila en local y `portal/public/build/` viaja como
 * artefacto estático, igual que los assets que reemplaza.
 *
 * Todo lo de aquí para abajo es aditivo: si un módulo no encuentra su marcado,
 * no hace nada. Por eso la primera versión del bundle puede convivir con las
 * vistas que todavía no se migraron.
 */
import { initTheme } from './ui/theme';
import { initSidebar } from './ui/sidebar';
import { initTabs } from './ui/tabs';
import { initToasts } from './ui/toast';
import { initModals } from './ui/modal';
import { initCharts } from './ui/charts';
import { renderIcons } from './ui/icons';

function iniciar() {
  initTheme();
  initSidebar();
  initTabs();
  initToasts();
  initModals();
  initCharts();
  renderIcons();

  // Los formularios y modales que inserten marcado después del arranque tienen
  // que poder pedir el repintado de iconos sin conocer el módulo.
  window.remRenderIcons = renderIcons;
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', iniciar, { once: true });
} else {
  iniciar();
}
