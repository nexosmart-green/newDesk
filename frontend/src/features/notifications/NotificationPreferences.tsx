import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { PageState } from '@/components/ui/PageState'
import { useNotificationPreferences, useSavingPreferenceEvents, useToggleEmailPreference } from './useNotifications'

const LOCKED_EXPLANATION = 'Obligatoria para administradores: avisa cuando un proyecto se queda sin horas o se corta un cronómetro.'

/** RF-09: which events also arrive by e-mail. The bell always gets everything. */
export function NotificationPreferences() {
  const preferences = useNotificationPreferences()
  const toggle = useToggleEmailPreference()
  const saving = useSavingPreferenceEvents()

  if (!preferences.data) return <PageState isPending={preferences.isPending} error={preferences.error} onRetry={() => void preferences.refetch()} />
  const list = preferences.data

  return (
    <section className="space-y-3" aria-label="Preferencias de notificaciones">
      <p className="text-sm text-muted">Todas las notificaciones llegan a la campana. Elegí cuáles querés recibir también por e-mail.</p>
      <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
        {list.map((preference) => {
          const hintId = `preference-${preference.event}-hint`
          return (
            <li key={preference.event} className="flex items-start justify-between gap-3 p-3">
              <div>
                <p className="text-sm">{preference.label}</p>
                {preference.locked && (
                  <p id={hintId} className="text-xs text-muted">
                    {LOCKED_EXPLANATION}
                  </p>
                )}
              </div>
              <label className="flex shrink-0 items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={preference.email}
                  disabled={preference.locked || saving.includes(preference.event)}
                  aria-describedby={preference.locked ? hintId : undefined}
                  aria-label={`Recibir por e-mail: ${preference.label}`}
                  onChange={(event) => toggle.mutate({ event: preference.event, email: event.target.checked })}
                />
                <span aria-hidden="true">E-mail</span>
              </label>
            </li>
          )
        })}
      </ul>
      <FieldErrorsAlert error={toggle.error} />
    </section>
  )
}
