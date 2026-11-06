import type { PageSpeedRun, PageSpeedSummary } from '../api/types'
import { Panel } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatDateTime } from '../lib/format'
import { formatCLS, formatINP, formatLCP, rateCLS, rateINP, rateLCP, ratingMeta, scoreTone, type Rating } from '../lib/pagespeed'
import { toneColor } from '../lib/status'

// ---------- PageSpeed (4b) ----------

const shortDate = new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'short' })

// Línea chica con el puntaje de cada semana. La escala va del peor al mejor puntaje (con 5 puntos de aire):
// sobre 0–100 una mejora de 10 puntos se vería plana. Con menos de 2 mediciones no dice nada.
function Sparkline({ scores, label }: { scores: readonly number[]; label: string }) {
  if (scores.length < 2) return null
  const width = 104
  const height = 30
  const low = Math.max(0, Math.min(...scores) - 5)
  const high = Math.min(100, Math.max(...scores) + 5)
  const step = width / (scores.length - 1)
  const y = (score: number) => height - 2 - ((score - low) / Math.max(high - low, 1)) * (height - 4)
  const points = scores.map((score, index) => `${(index * step).toFixed(1)},${y(score).toFixed(1)}`).join(' ')
  return (
    <svg role="img" aria-label={`${label}: ${scores.join(', ')}`} width={width} height={height} viewBox={`0 0 ${width} ${height}`} className="overflow-visible">
      <polyline points={points} fill="none" stroke="var(--chart-1)" strokeWidth={1.5} strokeLinejoin="round" strokeLinecap="round" />
    </svg>
  )
}

function MetricRow({ name, hint, value, rating }: { name: string; hint: string; value: string; rating: Rating | null }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <dt className="text-muted" title={hint}>{name}</dt>
      <dd className="num flex items-center gap-2">
        {value}
        {rating && <Badge tone={ratingMeta[rating].tone}>{ratingMeta[rating].label}</Badge>}
      </dd>
    </div>
  )
}

function StrategyCard({ label, run, scores }: { label: string; run: PageSpeedRun | null; scores: readonly number[] }) {
  if (!run) {
    return (
      <div className="rounded-xl bg-surface-2 p-4">
        <span className="label">{label}</span>
        <p className="mt-2 text-sm text-muted">Todavía no hay una medición.</p>
      </div>
    )
  }
  const tone = scoreTone(run.score)
  return (
    <div className="rounded-xl bg-surface-2 p-4">
      <div className="flex items-center justify-between gap-2">
        <span className="label">{label}</span>
        <span className="num text-xs text-faint" title={formatDateTime(run.measured_at)}>Medido el {shortDate.format(new Date(run.measured_at))}</span>
      </div>
      <div className="mt-3 flex items-center gap-4">
        <span
          className="num grid size-14 shrink-0 place-items-center rounded-full border-4 text-xl font-semibold"
          style={{ borderColor: toneColor(tone), color: toneColor(tone) }}
        >
          <span aria-hidden="true">{run.score}</span>
          <span className="sr-only">Puntaje {run.score} de 100</span>
        </span>
        <Sparkline scores={scores} label={`Evolución del puntaje en ${label.toLowerCase()}`} />
      </div>
      <dl className="mt-3 space-y-1.5 text-sm">
        <MetricRow
          name="Carga (LCP)"
          hint="Cuánto tarda en verse lo principal de la página"
          value={run.lcp_ms === null ? '—' : formatLCP(run.lcp_ms)}
          rating={run.lcp_ms === null ? null : rateLCP(run.lcp_ms)}
        />
        <MetricRow
          name="Respuesta (INP)"
          hint="Qué tan rápido responde la página a un toque o un clic"
          value={run.inp_ms === null ? 'Solo con datos reales' : formatINP(run.inp_ms)}
          rating={run.inp_ms === null ? null : rateINP(run.inp_ms)}
        />
        <MetricRow
          name="Estabilidad (CLS)"
          hint="Cuánto se mueve el contenido mientras carga"
          value={run.cls === null ? '—' : formatCLS(run.cls)}
          rating={run.cls === null ? null : rateCLS(run.cls)}
        />
      </dl>
      <p className="mt-3 text-xs text-muted">{run.field ? 'Datos reales de usuarios (últimos 28 días)' : 'Medición de laboratorio'}</p>
    </div>
  )
}

// Una vez por semana, en celular y en computadora. Sin mediciones buenas no se muestra.
export function PageSpeedPanel({ summaries }: { summaries: readonly PageSpeedSummary[] }) {
  if (summaries.length === 0) return null
  return (
    <Panel title="Rendimiento (PageSpeed)" actions={<span className="text-xs text-muted">Se mide una vez por semana</span>}>
      <div className="space-y-6">
        {summaries.map((summary) => (
          <section key={summary.component} aria-label={`PageSpeed de ${summary.component}`} className="space-y-3">
            <h3 className="font-medium">{summary.component}</h3>
            <div className="grid gap-4 sm:grid-cols-2">
              <StrategyCard label="Celular" run={summary.mobile} scores={summary.history.filter((point) => point.strategy === 'mobile').map((point) => point.score)} />
              <StrategyCard label="Computadora" run={summary.desktop} scores={summary.history.filter((point) => point.strategy === 'desktop').map((point) => point.score)} />
            </div>
          </section>
        ))}
      </div>
      <p className="mt-4 text-xs text-faint">Puntaje de 0 a 100 de Google PageSpeed: de 90 para arriba es bueno.</p>
    </Panel>
  )
}
