import { CalendarSync, Plus } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { PageState } from '@/components/ui/PageState'
import { useCurrentUser } from '@/features/auth/useAuth'
import { weekOf } from '@/lib/week'
import { formatDate } from '@/lib/dates'
import { browserTimeZone, todayIn } from '@/lib/timezone'
import { EndRecurrenceDialog } from './EndRecurrenceDialog'
import { RecurrenceFormDialog } from './RecurrenceFormDialog'
import type { ShiftRecurrence } from './recurrenceTypes'
import { workModeLabels } from './types'
import { useDeleteShiftRecurrence, useShiftRecurrences } from './useRecurrences'
import { useShiftGrid, useShiftTemplates } from './useShifts'
import { WeekdayChips } from './Weekdays'

type OpenDialog = { kind: 'new' } | { kind: 'end' | 'delete'; recurrence: ShiftRecurrence } | null

function RecurrenceStatus({ recurrence, today }: { recurrence: ShiftRecurrence; today: string }) {
  if (recurrence.ends_on !== null && recurrence.ends_on < today) return <Badge>Finalizado</Badge>
  if (recurrence.starts_on > today) return <Badge tone="accent">Empieza el {formatDate(recurrence.starts_on)}</Badge>
  return <Badge tone="success">Vigente</Badge>
}

interface RecurrenceRowProps {
  recurrence: ShiftRecurrence
  today: string
  onEnd: () => void
  onDelete: () => void
}

function RecurrenceRow({ recurrence, today, onEnd, onDelete }: RecurrenceRowProps) {
  const { template } = recurrence
  const ended = recurrence.ends_on !== null && recurrence.ends_on < today

  return (
    <li className="flex flex-col gap-3 py-3 sm:flex-row sm:items-center">
      <span
        aria-hidden="true"
        className="hidden h-10 w-1 shrink-0 rounded-full sm:block"
        style={{ backgroundColor: template?.color ?? 'var(--color-border)' }}
      />
      <div className="min-w-0 flex-1 space-y-1.5">
        <p className="flex flex-wrap items-center gap-2 text-sm">
          {template ? (
            <>
              <span className="font-medium">{template.name}</span>
              <span className="tabular text-muted">
                {template.starts_at}–{template.ends_at}, {workModeLabels[template.mode]}
              </span>
            </>
          ) : (
            <Badge tone="warning">Plantilla eliminada: ya no genera días</Badge>
          )}
          <RecurrenceStatus recurrence={recurrence} today={today} />
        </p>
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted">
          <WeekdayChips weekdays={recurrence.weekdays} />
          <span className="tabular">
            Desde {formatDate(recurrence.starts_on)} {recurrence.ends_on ? `hasta ${formatDate(recurrence.ends_on)}` : 'sin fin'}
          </span>
          {!recurrence.skip_holidays && <span>Incluye feriados</span>}
        </div>
      </div>
      <div className="flex gap-2">
        {!ended && (
          <Button variant="secondary" aria-label={`Finalizar turno fijo de ${recurrence.user.full_name}`} onClick={onEnd}>
            Finalizar
          </Button>
        )}
        <Button variant="ghost" aria-label={`Borrar turno fijo de ${recurrence.user.full_name}`} onClick={onDelete}>
          Borrar
        </Button>
      </div>
    </li>
  )
}

function groupByPerson(recurrences: readonly ShiftRecurrence[]) {
  const groups = new Map<number, { name: string; rules: ShiftRecurrence[] }>()
  for (const recurrence of recurrences) {
    const group = groups.get(recurrence.user.id) ?? { name: recurrence.user.full_name, rules: [] }
    group.rules.push(recurrence)
    groups.set(recurrence.user.id, group)
  }
  return [...groups.entries()].sort(([, a], [, b]) => a.name.localeCompare(b.name, 'es'))
}

interface RecurringShiftsPanelProps {
  /** Without templates there is nothing to repeat: the empty state sends the person there. */
  onGoToTemplates: () => void
}

/** RF-06.03 "Turnos fijos": rules per person that fill the weekly grid by themselves. */
export function RecurringShiftsPanel({ onGoToTemplates }: RecurringShiftsPanelProps) {
  const recurrences = useShiftRecurrences()
  const templates = useShiftTemplates()
  const [week] = useState(() => weekOf(new Date()))
  const people = useShiftGrid(week.from, week.to)
  const remove = useDeleteShiftRecurrence()
  const timeZone = useCurrentUser().data?.company.timezone ?? browserTimeZone()
  const today = todayIn(timeZone)
  const [dialog, setDialog] = useState<OpenDialog>(null)
  const canCreate = Boolean(templates.data && templates.data.length > 0 && people.data)

  function close() {
    remove.reset()
    setDialog(null)
  }

  const newButton = (
    <Button onClick={() => setDialog({ kind: 'new' })} disabled={!canCreate}>
      <Plus aria-hidden="true" className="size-4" />
      Nuevo turno fijo
    </Button>
  )

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="max-w-prose text-sm text-muted">
          Un turno fijo carga solo los días de las próximas 8 semanas y se va extendiendo. Nunca pisa un día que ya tiene turno.
        </p>
        {recurrences.data && recurrences.data.length > 0 && templates.data?.length !== 0 && newButton}
      </div>

      {!recurrences.data || !templates.data ? (
        <PageState
          isPending={recurrences.isPending || templates.isPending}
          error={recurrences.error ?? templates.error}
          shape="list"
          onRetry={() => void Promise.all([recurrences.refetch(), templates.refetch()])}
        />
      ) : templates.data.length === 0 ? (
        <EmptyState title="Primero necesitás una plantilla" text="El turno fijo repite una plantilla (ej.: “Completo 09:00–18:00”).">
          <Button onClick={onGoToTemplates}>Crear plantilla</Button>
        </EmptyState>
      ) : recurrences.data.length === 0 ? (
        <EmptyState title="Todavía no hay turnos fijos" text="Ej.: lunes a viernes, Completo, desde el 01/12. Los días se generan solos.">
          {newButton}
        </EmptyState>
      ) : (
        <ul className="space-y-3" aria-label="Turnos fijos por persona">
          {groupByPerson(recurrences.data).map(([userId, group]) => (
            <li key={userId} className="rounded-xl border border-border bg-surface px-4 py-1">
              <h3 className="pt-3 text-sm font-semibold">{group.name}</h3>
              <ul className="divide-y divide-border">
                {group.rules.map((recurrence) => (
                  <RecurrenceRow
                    key={recurrence.id}
                    recurrence={recurrence}
                    today={today}
                    onEnd={() => setDialog({ kind: 'end', recurrence })}
                    onDelete={() => setDialog({ kind: 'delete', recurrence })}
                  />
                ))}
              </ul>
            </li>
          ))}
        </ul>
      )}

      {dialog?.kind === 'new' && templates.data && people.data && (
        <RecurrenceFormDialog people={people.data.people} templates={templates.data} today={today} onClose={close} />
      )}
      {dialog?.kind === 'end' && <EndRecurrenceDialog recurrence={dialog.recurrence} today={today} onClose={close} />}
      {dialog?.kind === 'delete' && (
        <ConfirmDialog
          title={`Borrar turno fijo de ${dialog.recurrence.user.full_name}`}
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={() => remove.mutate(dialog.recurrence.id, { onSuccess: close })}
          onClose={close}
        >
          <p>Se quitan los días que generó de mañana en adelante.</p>
          <p className="text-muted">Los días hasta hoy quedan cargados como turnos comunes, y los cargados a mano no se tocan.</p>
        </ConfirmDialog>
      )}
    </div>
  )
}

function EmptyState({ title, text, children }: { title: string; text: string; children: ReactNode }) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-surface px-6 py-10 text-center">
      <span className="grid size-12 place-items-center rounded-full bg-brand/15 text-brand-strong">
        <CalendarSync aria-hidden="true" className="size-5" />
      </span>
      <p className="font-semibold">{title}</p>
      <p className="max-w-sm text-sm text-muted">{text}</p>
      {children}
    </div>
  )
}
