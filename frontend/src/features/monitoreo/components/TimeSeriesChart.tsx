import { useEffect, useRef } from 'react'
import uPlot from 'uplot'
import 'uplot/dist/uPlot.min.css'
import { useColorScheme } from '../lib/theme'

// Serie 0: línea principal con relleno, en --chart-1 (el color de la marca, si el contenedor lo pisa);
// el resto: comparación punteada en --chart-2.
export interface ChartSeries {
  label: string
  values: readonly (number | null)[]
}

interface TimeSeriesChartProps {
  title: string
  // Segundos Unix, alineados con los valores de cada serie.
  xs: readonly number[]
  series: readonly ChartSeries[]
  formatAxis: (value: number) => string
  formatValue: (value: number) => string
  // Rango del eje Y; por defecto de 0 a un 15 % arriba del máximo.
  yRange?: (max: number) => [number, number]
  height?: number
}

// Color resuelto de un token. getPropertyValue devolvería light-dark(…) sin resolver y el canvas no lo entiende.
function tokenColor(element: HTMLElement, token: string): string {
  element.style.color = `var(${token})`
  const color = getComputedStyle(element).color
  element.style.color = ''
  return color
}

const hourFormat = new Intl.DateTimeFormat('es-AR', { hour: '2-digit', minute: '2-digit', hour12: false })
const dayFormat = new Intl.DateTimeFormat('es-AR', { day: '2-digit', month: '2-digit' })
const fullFormat = new Intl.DateTimeFormat('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false })

// Eje X en 24 h: horas si el rango visible es menor a 2 días, fechas si no.
function timeTicks(u: uPlot, splits: number[]): string[] {
  const min = u.scales.x?.min ?? 0
  const max = u.scales.x?.max ?? 0
  const format = max - min > 2 * 86_400 ? dayFormat : hourFormat
  return splits.map((seconds) => format.format(new Date(seconds * 1000)))
}

const defaultRange = (max: number): [number, number] => [0, Math.max(1, max * 1.15)]

// Gráfico de líneas con uPlot. Se recrea al cambiar el esquema de color (manual o del sistema) o la cantidad
// de series; el ancho sigue al contenedor.
export function TimeSeriesChart({ title, xs, series, formatAxis, formatValue, yRange = defaultRange, height = 170 }: TimeSeriesChartProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const plotRef = useRef<uPlot | null>(null)
  const scheme = useColorScheme()
  const labels = series.map((each) => each.label).join('\u0000')

  useEffect(() => {
    const container = containerRef.current
    if (!container) return
    const color = (token: string) => tokenColor(container, token)
    const font = '11px "Inter Variable", system-ui, sans-serif'
    const xAxis: uPlot.Axis = { stroke: color('--muted'), font, grid: { show: false }, ticks: { show: false } }
    const yAxis: uPlot.Axis = { stroke: color('--muted'), font, size: 58, grid: { stroke: color('--line'), width: 1 }, ticks: { show: false } }
    const primary = color('--chart-1')

    const options: uPlot.Options = {
      width: container.clientWidth || 600,
      height,
      padding: [8, 4, 0, 0],
      cursor: { drag: { x: false, y: false }, points: { size: 7, fill: primary } },
      legend: { show: true, live: true },
      scales: { x: { time: true }, y: { range: (_u, _min, max) => yRange(max) } },
      axes: [
        { ...xAxis, values: timeTicks },
        { ...yAxis, values: (_u, values) => values.map(formatAxis) },
      ],
      series: [
        { label: 'Hora', value: (_u, v) => (v === null ? '—' : fullFormat.format(new Date(v * 1000))) },
        ...series.map<uPlot.Series>((def, index) => ({
          label: def.label,
          stroke: index === 0 ? primary : color('--chart-2'),
          width: index === 0 ? 1.5 : 1.25,
          dash: index === 0 ? undefined : [3, 3],
          fill: index === 0 ? `color-mix(in oklab, ${primary} 10%, transparent)` : undefined,
          points: { show: false },
          value: (_u, v) => (v === null ? '—' : formatValue(v)),
        })),
      ],
    }
    const plot = new uPlot(options, [[], ...series.map(() => [])], container)
    plotRef.current = plot

    const resize = new ResizeObserver(() => plot.setSize({ width: container.clientWidth, height }))
    resize.observe(container)
    return () => {
      resize.disconnect()
      plot.destroy()
      plotRef.current = null
    }
    // Los formateadores y las series son estables por gráfico; se recrea con esquema, alto o series nuevas.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [scheme, height, labels])

  useEffect(() => {
    plotRef.current?.setData([[...xs], ...series.map((def) => [...def.values])] as uPlot.AlignedData)
  }, [xs, series, scheme, labels])

  return (
    <figure className="min-w-0">
      <figcaption className="label mb-2">{title}</figcaption>
      <div ref={containerRef} className="w-full" />
    </figure>
  )
}

export function ChartSkeleton({ height = 170 }: { height?: number }) {
  return (
    <div className="space-y-2" aria-hidden="true">
      <span className="skeleton h-3.5 w-20" />
      <span className="skeleton rounded-xl" style={{ height }} />
      <span className="skeleton h-3 w-40" />
    </div>
  )
}
