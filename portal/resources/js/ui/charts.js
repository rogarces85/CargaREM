/**
 * Gráficos del panel.
 *
 * ApexCharts pesa ~570 KB y por eso **no** se importa en el arranque: se baja
 * con `import()` solo si la página declara `[data-chart]`. El presupuesto de la
 * casa exige que el panel no pague ese costo cuando no hay gráficos.
 *
 * Los colores salen de los tokens CSS (`--primary`, `--status-*`), así que el
 * gráfico sigue el tema claro u oscuro sin una paleta paralela. Al cambiar de
 * tema se vuelve a pintar: los colores de ApexCharts se congelan al crearse.
 */

const PALETA = [
  'var(--primary)',
  'var(--status-ok)',
  'var(--status-warn)',
  'var(--status-info)',
  'var(--status-danger)',
];

const COLORES_TEXTO = {
  eje: 'var(--muted-foreground)',
  borde: 'var(--border)',
  fondo: 'var(--card)',
  texto: 'var(--foreground)',
};

let apexcharts = null;
let instancias = [];

/** Convierte los nombres de serie del dominio en etiquetas legibles. */
function etiquetaCategoria(valor) {
  const mapa = {
    recibido: 'Recibido',
    validado: 'Validado',
    con_errores: 'Con errores',
    en_revision: 'En revisión',
    observado: 'Observado',
    aprobado: 'Aprobado',
  };

  return mapa[valor] ?? String(valor);
}

function opciones(config) {
  const series = (config.valores || []).map((valor) => Number(valor));
  const categorias = (config.categorias || []).map(etiquetaCategoria);

  const base = {
    chart: {
      height: config.alto || 260,
      type: config.tipo === 'donut' ? 'donut' : (config.tipo === 'area' ? 'area' : 'bar'),
      fontFamily: 'inherit',
      foreColor: COLORES_TEXTO.eje,
      toolbar: { show: false },
      animations: {
        enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      },
    },
    colors: PALETA,
    dataLabels: { enabled: false },
    legend: {
      show: true,
      position: 'bottom',
      labels: { colors: COLORES_TEXTO.eje },
    },
    grid: {
      borderColor: COLORES_TEXTO.borde,
      strokeDashArray: 4,
      padding: { left: 4, right: 4 },
    },
    tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
  };

  if (config.tipo === 'donut') {
    return {
      ...base,
      labels: categorias,
      series,
      stroke: { colors: [COLORES_TEXTO.fondo] },
    };
  }

  return {
    ...base,
    series: [{ name: (config.series && config.series[0]) || 'Total', data: series }],
    xaxis: {
      categories: categorias,
      labels: { style: { colors: COLORES_TEXTO.eje } },
      axisBorder: { color: COLORES_TEXTO.borde },
      axisTicks: { color: COLORES_TEXTO.borde },
    },
    yaxis: {
      labels: { style: { colors: COLORES_TEXTO.eje } },
      forceNiceScale: true,
    },
    plotOptions: {
      bar: {
        borderRadius: 6,
        columnWidth: '55%',
        horizontal: false,
      },
    },
    stroke: { curve: 'smooth', width: config.tipo === 'area' ? 2 : 0 },
  };
}

async function cargar() {
  if (apexcharts) {
    return apexcharts;
  }

  const modulo = await import('apexcharts');
  apexcharts = modulo.default ?? modulo;

  return apexcharts;
}

function pintar() {
  instancias.forEach((instancia) => {
    try {
      instancia.destroy();
    } catch (e) {
      // Un gráfico ya destruido no es un problema.
    }
  });

  instancias = [];

  document.querySelectorAll('[data-chart]').forEach((nodo) => {
    let config;

    try {
      config = JSON.parse(nodo.dataset.config || '{}');
    } catch (e) {
      return;
    }

    // Sin datos no se dibuja un lienzo vacío: se deja el resumen en texto que
    // ya está en la página.
    if (!config.valores || !config.valores.length) {
      return;
    }

    const grafico = new apexcharts(nodo, opciones(config));
    instancias.push(grafico);
  });
}

async function iniciar() {
  const nodos = document.querySelectorAll('[data-chart]');

  if (!nodos.length) {
    return;
  }

  try {
    await cargar();
    pintar();

    // El tema se cambia sin recargar: los colores del gráfico se congelan al
    // crearlo, así que se vuelve a pintar cuando cambia la clase del <html>.
    const observador = new MutationObserver((mutaciones) => {
      mutaciones.forEach((mutacion) => {
        if (mutacion.attributeName === 'class') {
          pintar();
        }
      });
    });

    observador.observe(document.documentElement, { attributes: true });
  } catch (e) {
    // Si ApexCharts no carga, la pantalla sigue con el texto alternativo.
    console.warn('No se pudieron dibujar los gráficos.', e);
  }
}

export { iniciar as initCharts };
