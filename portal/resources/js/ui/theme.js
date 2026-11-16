/**
 * Tema claro / oscuro / sistema.
 *
 * La decisión se guarda como preferencia (`light` | `dark` | `system`) y se
 * aplica como clase concreta (`light` o `dark`) en <html>. Nunca se deja el
 * elemento sin clase: la hoja de estilos define los tokens oscuros también en
 * `@media (prefers-color-scheme: dark)` con el selector `:root:not(.light)`, así
 * que una clase explícita es la única forma de que «elegí claro» gane sobre un
 * sistema operativo en oscuro.
 *
 * El script de `resources/views/partials/tema.blade.php` hace exactamente lo
 * mismo en línea, antes del primer paint, para que no haya destello. Este
 * módulo repite la resolución porque el script en línea corre antes de que el
 * bundle exista y no puede exportar nada.
 */
const STORAGE_KEY = 'rem-theme';
const VALID = ['light', 'dark', 'system'];

const root = document.documentElement;
const media = window.matchMedia('(prefers-color-scheme: dark)');

export function preferredTheme() {
  const stored = localStorage.getItem(STORAGE_KEY);

  return VALID.includes(stored) ? stored : 'system';
}

export function effectiveTheme(preference) {
  if (preference === 'light' || preference === 'dark') {
    return preference;
  }

  return media.matches ? 'dark' : 'light';
}

function syncControls(preference) {
  document.querySelectorAll('[data-theme-set]').forEach((boton) => {
    boton.setAttribute('aria-pressed', String(boton.dataset.themeSet === preference));
  });

  // El botón de la cabecera alterna entre claro y oscuro: si el usuario venía
  // en «sistema», el primer clic fija el opuesto al que está viendo.
  document.querySelectorAll('[data-theme-toggle]').forEach((boton) => {
    const oscuro = effectiveTheme(preference) === 'dark';
    boton.setAttribute('aria-label', oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
    boton.dataset.themeState = oscuro ? 'dark' : 'light';
  });
}

export function applyTheme(preference) {
  const efectivo = effectiveTheme(preference);

  root.classList.toggle('dark', efectivo === 'dark');
  root.classList.toggle('light', efectivo === 'light');
  root.dataset.themePreference = preference;

  syncControls(preference);

  document.dispatchEvent(
    new CustomEvent('rem:themechange', { detail: { preference, effective: efectivo } }),
  );

  return efectivo;
}

export function setTheme(preference) {
  if (!VALID.includes(preference)) {
    return;
  }

  localStorage.setItem(STORAGE_KEY, preference);
  applyTheme(preference);
}

export function initTheme() {
  applyTheme(preferredTheme());

  document.querySelectorAll('[data-theme-set]').forEach((boton) => {
    boton.addEventListener('click', () => setTheme(boton.dataset.themeSet));
  });

  document.querySelectorAll('[data-theme-toggle]').forEach((boton) => {
    boton.addEventListener('click', () => {
      setTheme(effectiveTheme(preferredTheme()) === 'dark' ? 'light' : 'dark');
    });
  });

  // Si la preferencia es «sistema», seguir al sistema operativo en vivo.
  media.addEventListener('change', () => {
    if (preferredTheme() === 'system') {
      applyTheme('system');
    }
  });
}
