import { useId, useState } from 'react'
import { ApiError } from '@/lib/api'
import { errorMessage } from '../api/monitoringApi'
import { useProjectIncidents } from '../api/usePortalAdmin'
import { useProjectMutations } from '../api/useProjectMutations'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { ConfirmButton } from '../settings/ConfirmButton'
import { IncidentList } from '../portal/PortalHistory'

// ---------- incidentes y notas (3a, 3b) ----------

const QUICK_NOTES = [
  { label: 'Investigando…', text: 'Investigando: ' },
  { label: 'Identificamos la causa…', text: 'Identificamos la causa: ' },
  { label: 'Resuelto: …', text: 'Resuelto: ' },
] as const

export function IncidentsAdminPanel({ projectId, readOnly }: { projectId: number; readOnly: boolean }) {
  const incidents = useProjectIncidents(projectId)
  const { removeNote } = useProjectMutations(projectId)
  const [writing, setWriting] = useState<number | null>(null)

  return (
    <Panel title="Incidentes (90 días)" bodyClassName="">
      <p className="px-5 pb-3 text-sm text-muted">Cada caída es un incidente. Las notas las ve el cliente en su página de estado, sin el autor.</p>
      {incidents.isPending && <div className="px-5 pb-5"><Spinner label="Cargando incidentes…" /></div>}
      {incidents.isError && <div className="px-5 pb-5"><ErrorNote onRetry={() => void incidents.refetch()}>{errorMessage(incidents.error)}</ErrorNote></div>}
      {incidents.data && (
        <IncidentList
          incidents={incidents.data}
          empty="Sin incidentes en los últimos 90 días."
          noteActions={readOnly ? undefined : (note) => <ConfirmButton label="Borrar" confirmLabel="Confirmar" onConfirm={() => removeNote.mutate(note.id)} />}
          footer={
            readOnly
              ? undefined
              : (incident) =>
                  writing === incident.id ? (
                    <NoteForm projectId={projectId} incidentId={incident.id} onDone={() => setWriting(null)} />
                  ) : (
                    <button type="button" className="btn btn-sm mt-3" onClick={() => setWriting(incident.id)}>Agregar nota</button>
                  )
          }
        />
      )}
    </Panel>
  )
}

function NoteForm({ projectId, incidentId, onDone }: { projectId: number; incidentId: number; onDone: () => void }) {
  const { addNote } = useProjectMutations(projectId)
  const [body, setBody] = useState('')
  const id = useId()
  const text = body.trim()
  const apiError = addNote.error instanceof ApiError ? addNote.error : null

  return (
    <form
      aria-label="Nueva nota"
      className="fade-in mt-3 space-y-2"
      noValidate
      onSubmit={(event) => {
        event.preventDefault()
        addNote.mutate({ incidentId, body: text }, { onSuccess: onDone })
      }}
    >
      <label htmlFor={id} className="label block">Nota para el cliente</label>
      <div className="flex flex-wrap gap-1.5">
        {QUICK_NOTES.map((chip) => (
          <button key={chip.label} type="button" className="btn btn-sm" onClick={() => setBody(chip.text)}>{chip.label}</button>
        ))}
      </div>
      <textarea
        id={id}
        className="input min-h-20 py-2"
        maxLength={2000}
        value={body}
        aria-invalid={apiError?.field('body') ? true : undefined}
        onChange={(event) => setBody(event.target.value)}
      />
      <div className="flex flex-wrap items-center gap-2">
        <button type="submit" className="btn btn-primary btn-sm" disabled={addNote.isPending || !text}>
          {addNote.isPending && <Spinner />}
          Guardar nota
        </button>
        <button type="button" className="btn btn-ghost btn-sm" onClick={onDone}>Cancelar</button>
        <span className="num ml-auto text-xs text-faint">{body.length} / 2000</span>
      </div>
      {apiError && <ErrorNote>{apiError.field('body') ?? apiError.message}</ErrorNote>}
    </form>
  )
}
