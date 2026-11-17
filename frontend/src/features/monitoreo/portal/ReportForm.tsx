import { useId, useState, type ClipboardEvent, type FormEvent } from 'react'
import { useLocation } from 'react-router'
import { ApiError } from '@/lib/api'
import { errorMessage } from '../api/monitoringApi'
import type { PortalComponent, ReportCreated, ReportUrgency } from '../api/types'
import { useReportProblem } from '../api/usePortal'
import { Select } from '../clients/ComponentForm'
import { Icon } from '../components/Icon'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { formatBytes } from '../lib/format'
import { DESCRIPTION_MAX, DESCRIPTION_MIN, screenshotProblem, SCREENSHOT_TYPES, urgencyMeta } from '../lib/reports'
import { toneColor } from '../lib/status'
import { useDataUrl } from '../lib/useDataUrl'

const urgencies: ReportUrgency[] = ['blocking', 'annoying', 'question']

export function ReportForm({ slug, components, onDone }: { slug: string; components: PortalComponent[]; onDone: (result: ReportCreated) => void }) {
  // El cliente elige entre las partes que tiene (Web, App, CMS): es lo que ve en su página de estado.
  // La API, los workers y los backups los ve el equipo en el chequeo en vivo que viaja con el reporte.
  const report = useReportProblem(slug)
  const location = useLocation()
  const [description, setDescription] = useState('')
  const [part, setPart] = useState('')
  const [urgency, setUrgency] = useState<ReportUrgency | null>(null)
  const [screenshot, setScreenshot] = useState<File | null>(null)
  const [localErrors, setLocalErrors] = useState<Record<string, string>>({})
  const preview = useDataUrl(screenshot)
  const ids = { description: useId(), counter: useId(), where: useId(), urgency: useId(), file: useId() }
  const apiError = report.error instanceof ApiError ? report.error : null
  const errorFor = (field: string) => localErrors[field] ?? apiError?.field(field)
  const length = description.trim().length
  // Al corregir un campo se va su error.
  const clearError = (field: string) =>
    setLocalErrors((current) => {
      if (!(field in current)) return current
      const next = { ...current }
      delete next[field]
      return next
    })

  const attach = (file: File | undefined) => {
    if (!file) return
    const problem = screenshotProblem(file)
    setLocalErrors((current) => {
      const next = { ...current }
      delete next.screenshot
      return problem ? { ...next, screenshot: problem } : next
    })
    if (!problem) setScreenshot(file)
  }

  // Pegar una captura (Ctrl+V / Cmd+V) en cualquier parte del formulario la adjunta.
  const onPaste = (event: ClipboardEvent) => {
    const image = [...event.clipboardData.files].find((file) => file.type.startsWith('image/'))
    if (image) {
      event.preventDefault()
      attach(image)
    }
  }

  const submit = (event: FormEvent) => {
    event.preventDefault()
    const errors: Record<string, string> = {}
    if (length < DESCRIPTION_MIN) errors.description = `Contanos un poco más (al menos ${DESCRIPTION_MIN} caracteres).`
    if (length > DESCRIPTION_MAX) errors.description = `Como máximo ${DESCRIPTION_MAX} caracteres.`
    if (!urgency) errors.urgency = 'Elegí qué tan urgente es.'
    setLocalErrors(errors)
    if (Object.keys(errors).length > 0 || !urgency) return

    const form = new FormData()
    form.append('description', description.trim())
    form.append('urgency', urgency)
    form.append('part', part)
    form.append('page_url', (location.state as { from?: string } | null)?.from ?? window.location.href)
    form.append('screen', `${window.screen.width}x${window.screen.height}`)
    form.append('timezone', Intl.DateTimeFormat().resolvedOptions().timeZone)
    form.append('language', navigator.language)
    if (screenshot) form.append('screenshot', screenshot, screenshot.name)
    report.mutate(form, { onSuccess: onDone })
  }

  return (
    <Panel>
      <p className="mb-5 text-sm text-muted">
        Contanos qué pasa. Al enviarlo revisamos tu proyecto en ese momento y le avisamos al equipo con todo el detalle.
      </p>
      <form onSubmit={submit} onPaste={onPaste} noValidate className="space-y-5" aria-label="Reportar un problema">
        <fieldset disabled={report.isPending} className="min-w-0 space-y-5">
          <div>
            <label htmlFor={ids.description} className="label mb-1.5 block">¿Qué pasa?</label>
            <textarea
              id={ids.description}
              rows={5}
              className="input h-auto min-h-32 py-2.5 leading-relaxed"
              placeholder="Por ejemplo: al pagar una reserva el botón se queda cargando y no pasa nada."
              value={description}
              maxLength={DESCRIPTION_MAX + 200}
              aria-invalid={Boolean(errorFor('description')) || undefined}
              aria-describedby={ids.counter}
              onChange={(event) => {
                setDescription(event.target.value)
                clearError('description')
              }}
            />
            <div className="mt-1.5 flex items-start justify-between gap-3 text-xs">
              <span className="text-crit" role={errorFor('description') ? 'alert' : undefined}>{errorFor('description')}</span>
              <span id={ids.counter} className={`num shrink-0 ${length > DESCRIPTION_MAX ? 'text-crit' : 'text-faint'}`}>
                {length} / {DESCRIPTION_MAX}
              </span>
            </div>
          </div>

          {components.length > 0 && (
            <div>
              <label htmlFor={ids.where} className="label mb-1.5 block">¿Dónde?</label>
              <Select id={ids.where} value={part} onChange={(event) => setPart(event.target.value)}>
                <option value="">No sé</option>
                {components.map((component) => (
                  <option key={component.kind} value={component.kind}>{component.label}</option>
                ))}
              </Select>
              {errorFor('part') && <span className="mt-1.5 block text-xs text-crit">{errorFor('part')}</span>}
            </div>
          )}

          <fieldset className="min-w-0" aria-describedby={errorFor('urgency') ? ids.urgency : undefined}>
            <legend className="label mb-1.5 block">¿Qué tan urgente es?</legend>
            <div className="grid gap-2 sm:grid-cols-3">
              {urgencies.map((value) => {
                const meta = urgencyMeta[value]
                return (
                  <label
                    key={value}
                    className="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line bg-surface p-3.5 transition-colors hover:bg-surface-2 has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-accent-soft"
                  >
                    <input type="radio" name="urgency" value={value} checked={urgency === value} onChange={() => {
                        setUrgency(value)
                        clearError('urgency')
                      }} className="mt-1 size-4 shrink-0 accent-[var(--accent)]" />
                    <span>
                      <span className="flex items-center gap-1.5 font-medium">
                        <span className="size-2 shrink-0 rounded-full" style={{ background: toneColor(meta.tone) }} aria-hidden="true" />
                        {meta.label}
                      </span>
                      <span className="mt-0.5 block text-xs text-muted">{meta.hint}</span>
                    </span>
                  </label>
                )
              })}
            </div>
            {errorFor('urgency') && <span id={ids.urgency} role="alert" className="mt-1.5 block text-xs text-crit">{errorFor('urgency')}</span>}
          </fieldset>

          <div>
            <span className="label mb-1.5 block">Captura de pantalla (opcional)</span>
            {preview && screenshot ? (
              <div className="flex items-center gap-3 rounded-xl bg-surface-2 p-3">
                <img src={preview} alt="Captura adjunta" className="h-20 w-28 shrink-0 rounded-lg object-cover" />
                <div className="min-w-0 flex-1 text-sm">
                  <div className="truncate">{screenshot.name}</div>
                  <div className="text-xs text-muted">{formatBytes(screenshot.size)}</div>
                </div>
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => setScreenshot(null)}>Quitar</button>
              </div>
            ) : (
              <div className="flex flex-wrap items-center gap-3">
                <label htmlFor={ids.file} className="btn cursor-pointer">
                  <Icon name="plus" size={14} />
                  Adjuntar captura
                </label>
                <input
                  id={ids.file}
                  type="file"
                  accept={SCREENSHOT_TYPES.join(',')}
                  className="sr-only"
                  aria-label="Archivo de la captura"
                  onChange={(event) => {
                    attach(event.target.files?.[0])
                    event.target.value = ''
                  }}
                />
                <span className="text-xs text-muted">PNG, JPG o WebP hasta 5 MB. También podés pegarla con Ctrl+V.</span>
              </div>
            )}
            {errorFor('screenshot') && <span role="alert" className="mt-1.5 block text-xs text-crit">{errorFor('screenshot')}</span>}
          </div>
        </fieldset>

        {apiError && Object.keys(apiError.fieldErrors).length === 0 && <ErrorNote>{apiError.message}</ErrorNote>}
        {report.isError && !apiError && <ErrorNote>{errorMessage(report.error)}</ErrorNote>}

        <div className="space-y-2">
          <button type="submit" className="btn btn-primary h-11 w-full text-[0.9375rem] sm:w-auto sm:px-6" disabled={report.isPending}>
            {report.isPending ? <Spinner /> : <Icon name="check" size={14} />}
            {report.isPending ? 'Revisando tu proyecto…' : 'Enviar reporte'}
          </button>
          {report.isPending && <p role="status" className="text-xs text-muted">Revisamos cada parte de tu proyecto antes de avisar al equipo. Puede tardar unos segundos.</p>}
        </div>
      </form>
    </Panel>
  )
}
