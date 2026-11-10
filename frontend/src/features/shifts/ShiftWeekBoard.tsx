import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { SuccessNotice } from '@/components/ui/ErrorAlert'
import { PageState } from '@/components/ui/PageState'
import { useWeekParam } from '@/features/timesheet/useWeekParam'
import { WeekNav } from '@/features/timesheet/WeekNav'
import { datesBetween, weekLabel } from '@/features/timesheet/week'
import { AssignCellsDialog } from './AssignCellsDialog'
import { BulkAssignDialog } from './BulkAssignDialog'
import { cellKey, type Cell } from './selection'
import { ShiftGridTable } from './ShiftGridTable'
import { useAssignShifts, useCopyPreviousWeek, useSetShiftCells, useShiftGrid, useShiftTemplates } from './useShifts'

type OpenDialog = 'cells' | 'bulk' | 'copy' | null

const daysLabel = (count: number) => (count === 1 ? '1 día' : `${count} días`)

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
  const [notice, setNotice] = useState<string | null>(null)
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

  function finishAssign(cells: number) {
    setNotice(`Listo: ${daysLabel(cells)} actualizados.`)
    setSelected(new Map())
    closeDialog()
  }

  function assignSelection(templateId: number | null) {
    const cells = [...selected.values()].map((cell) => ({ user_id: cell.userId, date: cell.date }))
    setCells.mutate({ cells, shift_template_id: templateId }, { onSuccess: finishAssign })
  }

  function confirmCopy() {
    copy.mutate(week.from, {
      onSuccess: (copied) => {
        setNotice(copied === 0 ? 'La semana anterior no tenía turnos.' : `Se copiaron ${copied} turnos.`)
        closeDialog()
      },
    })
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
      <SuccessNotice message={notice} />
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
        <PageState isPending={grid.isPending} error={grid.error} />
      ) : grid.data.people.length === 0 ? (
        <p className="text-sm text-muted">No hay personas activas.</p>
      ) : (
        <>
          <p className="text-xs text-muted">Tocá uno o varios días para asignarles un turno. Horarios de {grid.data.timezone}.</p>
          <ShiftGridTable grid={grid.data} dates={dates} selected={new Set(selected.keys())} onToggle={toggle} />
        </>
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
          title="Copiar semana anterior"
          confirmLabel="Copiar"
          pending={copy.isPending}
          error={copy.error}
          onConfirm={confirmCopy}
          onClose={closeDialog}
        >
          <p>
            La semana {weekLabel(week)} pasa a ser una copia de la anterior: a quienes tenían turnos se les reemplazan los de esta
            semana.
          </p>
        </ConfirmDialog>
      )}
    </div>
  )
}
