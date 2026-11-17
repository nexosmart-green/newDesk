import { useMemo } from 'react'
import type { MetricPoint } from '../api/types'
import { TimeSeriesChart } from '../components/TimeSeriesChart'

export { ChartSkeleton } from '../components/TimeSeriesChart'

// Serie 0: línea principal con relleno; serie 1: comparación punteada (CPU máx, Swap, Salida).
export interface SeriesDef {
  key: keyof MetricPoint
  label: string
}

interface MetricsChartProps {
  title: string
  points: MetricPoint[]
  series: SeriesDef[]
  unit: 'percent' | 'rate'
  height?: number
}

function formatAxis(unit: MetricsChartProps['unit'], value: number): string {
  if (unit === 'percent') return `${Math.round(value)}%`
  if (value >= 1024 ** 2) return `${(value / 1024 ** 2).toFixed(1)}M`
  if (value >= 1024) return `${Math.round(value / 1024)}K`
  return `${Math.round(value)}`
}

const percentRange = (): [number, number] => [0, 100]
const rateRange = (max: number): [number, number] => [0, Math.max(1024, max * 1.15)]

// Métricas de un server (CPU, memoria, disco, red) sobre el gráfico genérico.
export function MetricsChart({ title, points, series, unit, height }: MetricsChartProps) {
  const xs = useMemo(() => points.map((p) => new Date(p.ts).getTime() / 1000), [points])
  const values = useMemo(
    () => series.map((def) => ({ label: def.label, values: points.map((p) => (typeof p[def.key] === 'number' ? (p[def.key] as number) : null)) })),
    [points, series],
  )
  return (
    <TimeSeriesChart
      title={title}
      xs={xs}
      series={values}
      height={height}
      yRange={unit === 'percent' ? percentRange : rateRange}
      formatAxis={(value) => formatAxis(unit, value)}
      formatValue={(value) => (unit === 'percent' ? `${value.toFixed(1)} %` : `${formatAxis('rate', value)}B/s`)}
    />
  )
}
