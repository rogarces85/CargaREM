/**
 * Barra lateral.
 *
 * Dos comportamientos distintos que comparten el mismo marcado:
 *
 *   Escritorio  la barra se colapsa a un riel de iconos. El estado se guarda
 *               en localStorage y se aplica antes del paint desde el script en
 *               línea, así que aquí solo se alterna.
 *
 *   Móvil       la barra entra y sale del lienzo con una capa oscura detrás.
 *               No se guarda: al abrir la página siguiente debe empezar cerrada.
 *
 * La fuente de verdad del estado abierto/cerrado es la clase en <html>, igual
 * que en el tema. Así el CSS puede responder sin que el JS toque estilos.
 */
const STORAGE_KEY = 'rem-sidebar';
const root = document.documentElement;

function isMobile() {
  return !window.matchMedia('(min-width: 64rem)').matches;
}

function setExpanded(sidebar, abierto) {
  document.querySelectorAll('[data-sidebar-toggle]').forEach((boton) => {
    boton.setAttribute('aria-expanded', String(abierto));
  });

  if (sidebar) {
    sidebar.dataset.state = abierto ? 'open' : 'closed';
  }
}

function closeMobile(sidebar) {
  root.classList.remove('sidebar-open');
  setExpanded(sidebar, false);
}

function openMobile(sidebar) {
  root.classList.add('sidebar-open');
  setExpanded(sidebar, true);

  // Devolver el foco al primer enlace es lo esperable al abrir un panel de
  // navegación, y evita que el tabulador se pierda detrás de la capa oscura.
  sidebar?.querySelector('a, button')?.focus({ preventScroll: true });
}

export function initSidebar() {
  const sidebar = document.querySelector('[data-sidebar]');
  const overlay = document.querySelector('[data-sidebar-overlay]');

  document.querySelectorAll('[data-sidebar-toggle]').forEach((boton) => {
    boton.addEventListener('click', () => {
      if (root.classList.contains('sidebar-open')) {
        closeMobile(sidebar);
      } else {
        openMobile(sidebar);
      }
    });
  });

  document.querySelectorAll('[data-sidebar-close]').forEach((boton) => {
    boton.addEventListener('click', () => closeMobile(sidebar));
  });

  overlay?.addEventListener('click', () => closeMobile(sidebar));

  // Colapso a riel: solo en escritorio.
  document.querySelectorAll('[data-sidebar-collapse]').forEach((boton) => {
    boton.addEventListener('click', () => {
      const colapsado = root.classList.toggle('sidebar-collapsed');

      localStorage.setItem(STORAGE_KEY, colapsado ? 'collapsed' : 'expanded');

      boton.setAttribute('aria-expanded', String(!colapsado));
      boton.setAttribute(
        'aria-label',
        colapsado ? 'Expandir la barra lateral' : 'Colapsar la barra lateral',
      );
    });
  });

  // Grupos colapsables del menú.
  document.querySelectorAll('[data-nav-group-toggle]').forEach((boton) => {
    boton.addEventListener('click', () => {
      const abierto = boton.getAttribute('aria-expanded') === 'true';

      boton.setAttribute('aria-expanded', String(!abierto));

      const lista = document.getElementById(boton.getAttribute('aria-controls'));

      if (lista) {
        lista.hidden = abierto;
      }
    });
  });

  // Escape cierra el panel móvil; en escritorio no hace nada.
  document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && root.classList.contains('sidebar-open')) {
      closeMobile(sidebar);

      document.querySelector('[data-sidebar-toggle]')?.focus({ preventScroll: true });
    }
  });

  // Al pasar a escritorio, el estado móvil deja de tener sentido.
  window.matchMedia('(min-width: 64rem)').addEventListener('change', (evento) => {
    if (evento.matches) {
      closeMobile(sidebar);
    }
  });

  setExpanded(sidebar, !isMobile());
}
