import { Plus } from 'lucide-react'
import { useId, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { ACTIVE_PROJECTS, useProjects } from '@/features/projects/useProjects'
import { defaultTaskFilters } from '@/features/tasks/types'
import { useMyTasks } from '@/features/tasks/useTasks'
import { dayLabel } from '@/features/timesheet/week'
import type { DayClock, DayPartInput } from '@/features/timer/types'
import { useSplitDay } from '@/features/timer/useTimer'
import { ApiError, fieldErrorOf } from '@/lib/api'
import { formatDuration } from '@/lib/duration'
import { zonedPartsOf } from '@/lib/timezone'
import {
  addPart,
  clockLabel,
  colorsByProject,
  durationOf,
  fillGap,
  gapsOf,
  initialParts,
  isCompletePart,
  nextKey,
  noProjectColor,
  offsetOfTime,
  removePart,
  secondsByProject,
  secondsPastMinute,
  setPartEdge,
  toPartInput,
  updatePart,
  type DayPart,
  type Span,
} from './dayParts'
import { DayTimeline } from './DayTimeline'
import { PartCard, type Overdraft } from './PartCard'

interface SplitDayDialogProps {
  /** A stopped (pending) day. */
  dayClock: DayClock
  /** Escape, X, scrim and "Repartir más tarde": the day stays pending. */
  onClose: () => void
  /** After saving; defaults to `onClose`. */
  onDone?: () => void
}

const projectsText = (count: number) => (count === 1 ? '1 proyecto' : `${count} proyectos`)

/**
 * RF-05.19 "Repartir el día": the stopped day is cut into parts, each with project, task and what was done.
 * Gaps aren't counted; a part may leave a bag below zero (saved anyway, the server tells admin and leader).
 */
export function SplitDayDialog({ dayClock, onClose, onDone = onClose }: SplitDayDialogProps) {
  const baseId = useId()
  const timeZone = useProfileTimeZone()
  const total = dayClock.elapsed_seconds
  const startSecond = secondsPastMinute(dayClock.started_at)
  const start = zonedPartsOf(dayClock.started_at, timeZone)
  const [parts, setParts] = useState(() => initialParts(total))
  const [activeKey, setActiveKey] = useState<number | null>(1)
  const [discarding, setDiscarding] = useState(false)
  const projects = useProjects(ACTIVE_PROJECTS)
  const myTasks = useMyTasks(defaultTaskFilters)
  const split = useSplitDay()

  const labelAt = (offset: number) => clockLabel(dayClock.started_at, offset, timeZone)
  const cardId = (key: number) => `${baseId}-part-${key}`
  const projectList = projects.data?.data ?? []
  const projectOf = (id: number | null) => projectList.find((project) => project.id === id)
  const colors = colorsByProject(parts)
  const colorOf = (part: DayPart) => (part.projectId !== null ? colors.get(part.projectId) : undefined) ?? noProjectColor
  const used = secondsByProject(parts)
  const assigned = parts.reduce((sum, part) => sum + durationOf(part), 0)
  const autoCut = dayClock.ended_at !== null && Date.parse(dayClock.ended_at) === Date.parse(dayClock.cutoff_at)
  const weekday = dayLabel(start.date).replace(/^./, (letter) => letter.toUpperCase())

  function change(next: DayPart[], selectKey?: number) {
    split.reset()
    setParts(next)
    if (selectKey !== undefined && next.length > parts.length) setActiveKey(selectKey)
  }

  function select(key: number) {
    setActiveKey(key)
    document.getElementById(cardId(key))?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' })
  }

  function save(input: DayPartInput[]) {
    split.mutate(
      { dayClockId: dayClock.id, parts: input },
      { onSuccess: onDone, onError: () => setDiscarding(false) },
    )
  }

  /** Shown on the last part of a capped project that this split leaves below zero. */
  function overdraftOf(part: DayPart, index: number): Overdraft | null {
    const project = projectOf(part.projectId)
    const available = project?.hours.has_cap ? project.hours.available_seconds : null
    if (!project || available === null || parts.slice(index + 1).some((later) => later.projectId === project.id)) return null
    const left = available - (used.get(project.id) ?? 0)
    return left < 0 ? { available, left } : null
  }

  // Phones list the gaps between the cards too.
  const rows: { from: number; part?: DayPart; index?: number; gap?: Span }[] = [
    ...parts.map((part, index) => ({ from: part.from, part, index })),
    ...gapsOf(parts, total).map((gap) => ({ from: gap.from, gap })),
  ].sort((left, right) => left.from - right.from)
  // A rule about the whole list (not one part) has no card to sit on.
  const listError = fieldErrorOf(split.error, 'parts')

  return (
    <Dialog
      title="Repartir el día"
      size="xl"
      onClose={onClose}
      description={
        <>
          {weekday} · de <strong className="tabular font-semibold text-text">{labelAt(0)}</strong> a{' '}
          <strong className="tabular font-semibold text-text">{labelAt(total)}</strong> · {formatDuration(total)} en el reloj
          {autoCut && ' · se cortó solo a medianoche'}
        </>
      }
    >
      <DayTimeline
        parts={parts}
        total={Math.max(total, 1)}
        startTime={start.time}
        startSecond={startSecond}
        labelAt={labelAt}
        colorOf={colorOf}
        nameOf={(part) => projectOf(part.projectId)?.name ?? 'Sin proyecto'}
        activeKey={activeKey}
        onChange={change}
        onSelect={select}
        onFillGap={(gap: Span) => change(fillGap(parts, gap), nextKey(parts))}
      />
      <div className="space-y-2.5">
        {rows.map(({ part, index = 0, gap }) =>
          part ? (
            <PartCard
              key={part.key}
              id={cardId(part.key)}
              index={index}
              part={part}
              color={colorOf(part)}
              expanded={part.key === activeKey}
              project={projectOf(part.projectId)}
              projects={projectList}
              projectsLoading={projects.isPending}
              tasks={(myTasks.data?.data ?? []).filter((task) => part.projectId !== null && task.project?.id === part.projectId)}
              overdraft={overdraftOf(part, index)}
              error={split.error}
              canRemove={parts.length > 1}
              labelAt={labelAt}
              onTimeChange={(edge, time) => change(setPartEdge(parts, index, edge, offsetOfTime(time, start.time, startSecond, total), total))}
              onChange={(patch) => change(updatePart(parts, part.key, patch))}
              onRemove={() => change(removePart(parts, part.key))}
              onExpand={() => setActiveKey(part.key)}
            />
          ) : (
            gap && (
              <p key={`gap-${gap.from}`} className="px-1 text-xs text-muted sm:hidden">
                {labelAt(gap.from)} – {labelAt(gap.to)} · {formatDuration(durationOf(gap))} sin asignar: no se cuentan
              </p>
            )
          ),
        )}
      </div>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Button variant="secondary" className="border-dashed text-accent" onClick={() => change(addPart(parts, total, startSecond), nextKey(parts))}>
          <Plus aria-hidden="true" className="size-4" />
          Agregar parte
        </Button>
        <Button variant="link" className="min-h-11 px-1 text-sm" onClick={() => setDiscarding(true)}>
          No conté nada este día
        </Button>
      </div>
      <ErrorAlert error={split.error} />
      {listError && <ErrorAlert error={new ApiError(422, listError)} />}
      <DialogFooter>
        <div className="order-last text-sm sm:order-first sm:mr-auto">
          <p>
            <strong>{formatDuration(assigned)}</strong> repartidas{used.size > 0 && ` en ${projectsText(used.size)}`}
          </p>
          {total - assigned > 0 && <p className="text-muted">{formatDuration(total - assigned)} sin asignar: no se cuentan</p>}
        </div>
        <Button variant="secondary" onClick={onClose}>
          Repartir más tarde
        </Button>
        <Button loading={split.isPending && !discarding} disabled={parts.length === 0 || !parts.every(isCompletePart)} onClick={() => save(parts.map(toPartInput))}>
          Guardar reparto
        </Button>
      </DialogFooter>
      {discarding && (
        <ConfirmDialog
          title="¿No contaste nada este día?"
          confirmLabel="Sí, no conté nada"
          pending={split.isPending}
          danger
          onConfirm={() => save([])}
          onClose={() => setDiscarding(false)}
        >
          <p>El reloj marcó {formatDuration(total)}. No se carga ninguna hora y el día queda cerrado.</p>
        </ConfirmDialog>
      )}
    </Dialog>
  )
}
