import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { PageState } from '@/components/ui/PageState'
import { StaleContent } from '@/components/ui/StaleContent'
import { useWeekParam } from '@/lib/useWeekParam'
import { WeekNav } from '@/components/ui/WeekNav'
import { datesBetween, weekLabel } from '@/lib/week'
import { AssignCellsDialog } from './AssignCellsDialog'
import { BulkAssignDialog } from './BulkAssignDialog'
import { cellKey, type Cell } from './selection'
import { ShiftGridTable } from './ShiftGridTable'
import { daysLabel, useAssignShifts, useCopyPreviousWeek, useSetShiftCells, useShiftGrid, useShiftTemplates } from './useShifts'

type OpenDialog = 'cells' | 'bulk' | 'copy' | null

/** The weekly grid with its actions: assign selected cells, bulk assign, copy previous week. */
export function ShiftWeekBoard() {
  const { week, isCurrentWeek, goToWeek } = useWeekParam()
  const grid = useShiftGrid(week.from, week.to)
  const templates = useShiftTemplates()
  const assign = useAssignShifts()
  const setCells = useSetShiftCells()
  const copy = useCopyPreviousWeek()
  const [selected, setSelected] = useState<Map<string, Cell>>(new Map())
  const [dialog, setDialog] = useState<OpenDialog>(null)
  const dates = datesBetween(week.from, week.to)

  function changeWeek(monday: string | null) {
    setSelected(new Map())
    goToWeek(monday)
  }

  function toggle(cell: Cell) {
    const next = new Map(selected)
    if (next.has(cellKey(cell))) next.delete(cellKey(cell))
    else next.set(cellKey(cell), cell)
    setSelected(next)
  }

  function closeDialog() {
    assign.reset()
    setCells.reset()
    copy.reset()
    setDialog(null)
  }

  function finishAssign() {
    setSelected(new Map())
    closeDialog()
  }

  function assignSelection(templateId: number | null) {
    const cells = [...selected.values()].map((cell) => ({ user_id: cell.userId, date: cell.date }))
    setCells.mutate({ cells, shift_template_id: templateId }, { onSuccess: finishAssign })
  }

  function confirmCopy() {
    copy.mutate(week.from, { onSuccess: closeDialog })
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <WeekNav week={week} isCurrentWeek={isCurrentWeek} onChange={changeWeek} />
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => setDialog('copy')}>
            Copiar semana anterior
          </Button>
          <Button onClick={() => setDialog('bulk')} disabled={!grid.data || !templates.data}>
            Asignación masiva
          </Button>
        </div>
      </div>
      {selected.size > 0 && (
        <div className="flex flex-wrap items-center gap-3 rounded border border-accent/40 bg-accent/10 px-3 py-2 text-sm" role="region" aria-label="Selección">
          <span>{daysLabel(selected.size)} seleccionados</span>
          <Button onClick={() => setDialog('cells')} disabled={!templates.data}>
            Asignar turno
          </Button>
          <Button variant="link" onClick={() => setSelected(new Map())}>
            Limpiar selección
          </Button>
        </div>
      )}
      {!grid.data ? (
        <PageState isPending={grid.isPending} error={grid.error} shape="table" onRetry={() => void grid.refetch()} />
      ) : grid.data.people.length === 0 ? (
        <p className="text-sm text-muted">No hay personas activas.</p>
      ) : (
        <StaleContent stale={grid.isPlaceholderData} className="space-y-3">
          <p className="text-xs text-muted">Tocá uno o varios días para asignarles un turno. Horarios de {grid.data.timezone}.</p>
          <ShiftGridTable grid={grid.data} dates={dates} selected={new Set(selected.keys())} onToggle={toggle} />
        </StaleContent>
      )}
      {dialog === 'cells' && templates.data && (
        <AssignCellsDialog
          cellCount={selected.size}
          templates={templates.data}
          pending={setCells.isPending}
          error={setCells.error}
          onSubmit={assignSelection}
          onClose={closeDialog}
        />
      )}
      {dialog === 'bulk' && grid.data && templates.data && (
        <BulkAssignDialog
          people={grid.data.people}
          templates={templates.data}
          week={week}
          pending={assign.isPending}
          error={assign.error}
          onSubmit={(input) => assign.mutate(input, { onSuccess: finishAssign })}
          onClose={closeDialog}
        />
      )}
      {dialog === 'copy' && (
        <ConfirmDialog
          title="¿Reemplazar con la semana anterior?"
          confirmLabel="Reemplazar con la semana anterior"
          danger
          pending={copy.isPending}
          error={copy.error}
          onConfirm={confirmCopy}
          onClose={closeDialog}
        >
          <p>
            A quienes tenían turnos la semana anterior se les borran los que ya tienen cargados en la semana {weekLabel(week)} y se
            reemplazan por los de la anterior. No se puede deshacer.
          </p>
        </ConfirmDialog>
      )}
    </div>
  )
}
