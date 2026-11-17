import { useEffect, useState, type FormEvent } from 'react'
import { errorMessage } from '../api/monitoringApi'
import { useDailyReportStatus, useGenerateDailyReport } from '../api/useReports'
import type { DailyReport } from '../api/types'
import { Icon } from '../components/Icon'
import { ErrorNote, Spinner } from '../components/Panel'
import { copyText } from '../lib/clipboard'
import { GitHubError, readTodayCommits, startOfToday } from '../lib/githubCommits'

// "Generar reporte" (D-067, D-068, D-074): tus commits de hoy como títulos simples por proyecto, para pegar en la herramienta del equipo.
// El token de GitHub se pide cada vez y lo usa solo tu navegador: no se guarda y nunca llega al panel ni a Claude.
// Al panel solo le llegan los mensajes de los commits, y el claude -p de root del server 5 arma el reporte.
// Deshabilitado, con el motivo, si ese Claude no está conectado al panel.
export function DailyReportButton({ className = '' }: { className?: string }) {
  const status = useDailyReportStatus()
  const [open, setOpen] = useState(false)
  const available = status.data?.available ?? false
  const reason = status.isError ? errorMessage(status.error) : (status.data?.reason ?? 'Revisando si se puede generar…')

  return (
    <>
      <span title={available ? 'Tus commits de hoy, resumidos por Claude' : reason} className={className}>
        <button type="button" className="btn btn-sm" disabled={!available} onClick={() => setOpen(true)} aria-describedby={available ? undefined : 'daily-report-reason'}>
          <Icon name="bars" size={14} />
          Generar reporte
        </button>
        {!available && (
          <span id="daily-report-reason" className="sr-only">
            {reason}
          </span>
        )}
      </span>
      {open && <DailyReportDialog onClose={() => setOpen(false)} />}
    </>
  )
}

type Phase = { step: 'token' } | { step: 'github'; done: number; total: number } | { step: 'claude' } | { step: 'done'; report: DailyReport; skipped: string[] } | { step: 'error'; message: string }

function DailyReportDialog({ onClose }: { onClose: () => void }) {
  const generate = useGenerateDailyReport()
  const [token, setToken] = useState('')
  const [phase, setPhase] = useState<Phase>({ step: 'token' })
  const [copied, setCopied] = useState(false)

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && onClose()
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [onClose])

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    const secret = token.trim()
    setToken('') // el campo se vacía enseguida; el token solo vive en esta llamada
    setPhase({ step: 'github', done: 0, total: 0 })
    let today
    try {
      today = await readTodayCommits(secret, startOfToday(), (done, total) => setPhase({ step: 'github', done, total }))
    } catch (error) {
      const message = error instanceof GitHubError ? error.message : 'No se pudo hablar con GitHub. Revisá tu conexión y probá de nuevo.'
      setPhase({ step: 'error', message })
      return
    }
    if (today.repos.length === 0) {
      setPhase({ step: 'done', report: { markdown: '', projects: [], empty: true }, skipped: today.skipped })
      return
    }
    setPhase({ step: 'claude' })
    generate.mutate(today.repos, {
      onSuccess: (report) => setPhase({ step: 'done', report, skipped: today.skipped }),
      onError: (error) => setPhase({ step: 'error', message: errorMessage(error) }),
    })
  }

  const copy = async (markdown: string) => {
    if (await copyText(markdown)) {
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    }
  }

  return (
    <div className="fixed inset-0 z-[80] grid place-items-center bg-black/30 p-4" onClick={onClose}>
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="daily-report-title"
        className="panel fade-in flex max-h-[85dvh] w-full max-w-xl flex-col shadow-[var(--shadow-pop)]"
        onClick={(event) => event.stopPropagation()}
      >
        <header className="flex items-center gap-2 border-b border-line px-5 py-3">
          <h2 id="daily-report-title" className="flex-1 text-headline font-semibold">Reporte de hoy</h2>
          <button type="button" className="btn btn-ghost btn-sm size-8 px-0" aria-label="Cerrar" onClick={onClose}>
            <Icon name="close" size={14} />
          </button>
        </header>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
          {phase.step === 'token' && (
            <form id="daily-report-token" className="space-y-3" onSubmit={(event) => void submit(event)}>
              <label className="block space-y-1.5">
                <span className="text-sm font-medium">Tu token de GitHub</span>
                <input
                  type="password"
                  name="github-token"
                  className="input h-9 w-full font-mono text-xs"
                  autoComplete="off"
                  spellCheck={false}
                  required
                  autoFocus
                  value={token}
                  onChange={(event) => setToken(event.target.value)}
                />
              </label>
              <p className="flex items-start gap-1.5 text-xs text-muted">
                <Icon name="alert" size={12} className="mt-0.5 shrink-0" />
                <span>
                  Lo usa solo tu navegador para leer tus commits de hoy en GitHub. No se guarda y nunca llega al panel ni a Claude: al panel solo le llegan los
                  mensajes de los commits.
                </span>
              </p>
            </form>
          )}
          {phase.step === 'github' && (
            <Spinner label={phase.total ? `Leyendo tus commits en GitHub… (${phase.done} de ${phase.total} repos)` : 'Buscando tus repos con cambios hoy…'} />
          )}
          {phase.step === 'claude' && <Spinner label="Claude está armando el reporte… (puede tardar un minuto)" />}
          {phase.step === 'error' && <ErrorNote onRetry={() => setPhase({ step: 'token' })}>{phase.message}</ErrorNote>}
          {phase.step === 'done' &&
            (phase.report.empty ? (
              <p className="py-6 text-center text-sm text-muted">Hoy no hay commits tuyos en los repos donde podés pushear.</p>
            ) : (
              <>
                <pre aria-label="Reporte" className="rounded-xl bg-surface-2 p-4 font-sans text-sm leading-relaxed whitespace-pre-wrap">{phase.report.markdown}</pre>
                <p className="text-xs text-muted">
                  Basado en {phase.report.projects.map((project) => `${project.name} (${project.commits} commit${project.commits === 1 ? '' : 's'})`).join(', ')}.
                </p>
              </>
            ))}
          {phase.step === 'done' && phase.skipped.length > 0 && (
            <div className="text-xs text-muted">
              <p>No se pudieron leer:</p>
              <ul className="mt-1 list-disc pl-5">
                {phase.skipped.map((repo) => (
                  <li key={repo}>{repo}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
        {phase.step === 'token' && (
          <footer className="flex justify-end gap-2 border-t border-line px-5 py-3">
            <button type="submit" form="daily-report-token" className="btn btn-primary btn-sm" disabled={!token.trim()}>
              Generar
            </button>
          </footer>
        )}
        {phase.step === 'done' && !phase.report.empty && (
          <footer className="flex justify-end gap-2 border-t border-line px-5 py-3">
            <button type="button" className="btn btn-sm" onClick={() => setPhase({ step: 'token' })}>
              <Icon name="refresh" size={14} />
              Generar de nuevo
            </button>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => void copy(phase.report.markdown)}>
              <Icon name={copied ? 'check' : 'copy'} size={14} />
              {copied ? 'Copiado' : 'Copiar'}
            </button>
          </footer>
        )}
      </section>
    </div>
  )
}
