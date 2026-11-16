/**
 * Auditoría de contraste WCAG 2.2 sobre los tokens del sistema.
 *
 * Lee `resources/css/app.css`, extrae los bloques `:root` (claro) y `:root.dark`
 * (oscuro) y calcula el ratio de contraste de los pares que realmente usa la
 * interfaz. No necesita navegador: convierte oklch → sRGB con la fórmula de
 * Oklab, que es la misma que aplica el navegador.
 *
 * Uso:  node scripts/contraste.mjs
 *
 * Criterios (WCAG 2.2):
 *   4.5:1  texto normal (AA)
 *   3.0:1  texto grande (≥18.66px bold o ≥24px) e indicadores de interfaz (AA)
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const aqui = dirname(fileURLToPath(import.meta.url));
const css = readFileSync(join(aqui, '..', 'resources', 'css', 'app.css'), 'utf8');

/** Extrae los tokens de un bloque, dado su selector literal. */
function bloque(selector) {
  const desde = css.indexOf(selector);

  if (desde === -1) {
    throw new Error(`No encontré el bloque ${selector}`);
  }

  const abre = css.indexOf('{', desde);
  const cierra = css.indexOf('}', abre);
  const cuerpo = css.slice(abre + 1, cierra);
  const tokens = {};

  for (const coincidencia of cuerpo.matchAll(/--([a-z0-9-]+):\s*([^;]+);/g)) {
    tokens[`--${coincidencia[1]}`] = coincidencia[2].trim();
  }

  return tokens;
}

/** oklch(L C H) → [r,g,b] en sRGB lineal 0..1. */
function oklchARgbLineal(valor) {
  const m = valor.match(/oklch\(\s*([\d.]+)%?\s+([\d.]+)\s+([\d.]+)/i);

  if (!m) {
    throw new Error(`Valor no reconocido: ${valor}`);
  }

  const L = parseFloat(m[1]) > 1 ? parseFloat(m[1]) / 100 : parseFloat(m[1]);
  const C = parseFloat(m[2]);
  const H = (parseFloat(m[3]) * Math.PI) / 180;

  const a = C * Math.cos(H);
  const b = C * Math.sin(H);

  const l_ = L + 0.3963377774 * a + 0.2158037573 * b;
  const m_ = L - 0.1055613458 * a - 0.0638541728 * b;
  const s_ = L - 0.0894841775 * a - 1.291485548 * b;

  const l3 = l_ ** 3;
  const m3 = m_ ** 3;
  const s3 = s_ ** 3;

  return [
    4.0767416621 * l3 - 3.3077115913 * m3 + 0.2309699292 * s3,
    -1.2684380046 * l3 + 2.6097574011 * m3 - 0.3413193965 * s3,
    -0.0041960863 * l3 - 0.7034186147 * m3 + 1.707614701 * s3,
  ].map((c) => Math.min(1, Math.max(0, c)));
}

const luminancia = ([r, g, b]) => 0.2126 * r + 0.7152 * g + 0.0722 * b;

const contraste = (uno, otro) => {
  const a = luminancia(oklchARgbLineal(uno));
  const b = luminancia(oklchARgbLineal(otro));

  return Math.round(((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)) * 100) / 100;
};

/** Simula una capa translúcida sobre un fondo opaco (para los tintes /10). */
const tinte = (color, alfa, fondo) => {
  const c = oklchARgbLineal(color);
  const f = oklchARgbLineal(fondo);

  return c.map((v, i) => v * alfa + f[i] * (1 - alfa));
};

const contrasteTinte = (texto, color, alfa, fondo) => {
  const a = luminancia(oklchARgbLineal(texto));
  const b = luminancia(tinte(color, alfa, fondo));

  return Math.round(((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)) * 100) / 100;
};

const pares = (t) => {
  const card = t['--card'];
  const filas = [
    ['foreground / background', t['--foreground'], t['--background'], 4.5],
    ['muted-foreground / card', t['--muted-foreground'], card, 4.5],
    ['muted-foreground / background', t['--muted-foreground'], t['--background'], 4.5],
    ['primary-foreground / primary', t['--primary-foreground'], t['--primary'], 4.5],
    ['destructive-foreground / destructive', t['--destructive-foreground'], t['--destructive'], 4.5],
  ];

  for (const estado of ['ok', 'warn', 'danger', 'idle']) {
    filas.push([`status-${estado}-fg / card`, t[`--status-${estado}-fg`], card, 4.5]);
    filas.push([
      `status-${estado}-fg / tinte 10%`,
      contrasteTinte(t[`--status-${estado}-fg`], t[`--status-${estado}`], 0.1, card),
      null,
      4.5,
      true,
    ]);
  }

  return filas;
};

function informe(nombre, tokens) {
  console.log(`\n── ${nombre} ${'─'.repeat(58 - nombre.length)}`);
  let fallos = 0;

  for (const [etiqueta, unO, otroO, minimo, esTinte] of pares(tokens)) {
    const ratio = esTinte ? unO : contraste(unO, otroO);
    const ok = ratio >= minimo;

    if (!ok) {
      fallos++;
    }

    console.log(
      `${ok ? 'PASA' : 'FALLA'}  ${ratio.toFixed(2).padStart(5)}:1  (min ${minimo})  ${etiqueta}`,
    );
  }

  return fallos;
}

const claro = bloque(':root {');
const oscuro = bloque(':root.dark {');

/*
 * Los tokens oscuros están declarados dos veces a propósito: en la clase
 * `.dark` (la pone el script anti-flash) y dentro de
 * `@media (prefers-color-scheme: dark)` con `:root:not(.light)` (para quien
 * navega sin JavaScript). Dos copias se desincronizan solas: esta comprobación
 * obliga a que digan lo mismo.
 */
const oscuroPorSistema = bloque(':root:not(.light) {');

const claves = new Set([...Object.keys(oscuro), ...Object.keys(oscuroPorSistema)]);
const discrepancias = [];

for (const clave of claves) {
  const enClase = oscuro[clave];
  const enMedia = oscuroPorSistema[clave];

  if (enClase !== enMedia) {
    discrepancias.push(`${clave}: clase="${enClase ?? '—'}" media="${enMedia ?? '—'}"`);
  }
}

console.log('\n── Paridad de los tokens oscuros ' + '─'.repeat(27));

if (discrepancias.length === 0) {
  console.log(`PASA  .dark y @media definen los mismos ${claves.size} tokens con el mismo valor.`);
} else {
  console.log('FALLA  las dos declaraciones del tema oscuro difieren:');
  discrepancias.forEach((d) => console.log(`       ${d}`));
}

const fallos = informe('Tema claro', claro) + informe('Tema oscuro', oscuro) + discrepancias.length;

console.log(`\n${fallos === 0 ? 'Sin fallos de contraste ni de paridad.' : `${fallos} problema(s) encontrados.`}`);

process.exit(fallos === 0 ? 0 : 1);
