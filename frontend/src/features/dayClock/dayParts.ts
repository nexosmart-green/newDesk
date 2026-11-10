import { isValidComment, type DayPartInput } from '@/features/timer/types'
import { zonedPartsOf } from '@/lib/timezone'

/** RF-05.19 day split math. Offsets are integer seconds from the day clock's `started_at`. */

export interface Span {
  from: number
  to: number
}

export interface DayPart extends Span {
  key: number
  projectId: number | null
  taskId: number | null
  /** Not null = "Nueva tarea…" chosen: the title being typed. */
  newTaskTitle: string | null
  comment: string
}

/** A draggable edge: a part's start/end next to a gap (or the day's edge), or the cut shared by two parts. */
export interface Handle {
  kind: 'from' | 'to' | 'cut'
  /** The part it belongs to; for a cut, the left one. */
  index: number
  value: number
}

/** The shortest part a drag or a typed time leaves. */
export const MIN_PART_SECONDS = 60
const DAY_SECONDS = 86_400

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value))
const minutesOf = (time: string) => {
  const [hours = 0, minutes = 0] = time.split(':').map(Number)
  return hours * 60 + minutes
}

export const durationOf = (span: Span) => span.to - span.from

export const nextKey = (parts: DayPart[]) => Math.max(0, ...parts.map((part) => part.key)) + 1

const emptyPart = (key: number, span: Span): DayPart => ({ key, ...span, projectId: null, taskId: null, newTaskTitle: null, comment: '' })

/** One part covering the whole day, nothing chosen yet. */
export function initialParts(total: number): DayPart[] {
  return total > 0 ? [emptyPart(1, { from: 0, to: total })] : []
}

/** What no part covers: not counted. */
export function gapsOf(parts: DayPart[], total: number): Span[] {
  const gaps: Span[] = []
  let cursor = 0
  for (const part of parts) {
    if (part.from > cursor) gaps.push({ from: cursor, to: part.from })
    cursor = part.to
  }
  if (cursor < total) gaps.push({ from: cursor, to: total })
  return gaps
}

export function fillGap(parts: DayPart[], gap: Span): DayPart[] {
  return [...parts, emptyPart(nextKey(parts), gap)].sort((left, right) => left.from - right.from)
}

/** Drags land on whole minutes of the wall clock (`startSecond` = seconds past the minute at the start). */
export function snapOffset(offset: number, startSecond: number): number {
  return Math.round((offset + startSecond) / 60) * 60 - startSecond
}

/** Arrow keys: to the next whole minute in that direction, `minutes` at a time. */
export function stepOffset(offset: number, minutes: number, startSecond: number): number {
  const wallMinutes = (offset + startSecond) / 60
  return ((minutes > 0 ? Math.floor(wallMinutes) : Math.ceil(wallMinutes)) + minutes) * 60 - startSecond
}

/** "+ Agregar parte": the biggest gap, else the second half of the longest part. */
export function addPart(parts: DayPart[], total: number, startSecond: number): DayPart[] {
  const gap = gapsOf(parts, total).reduce<Span | undefined>((best, span) => (!best || durationOf(span) > durationOf(best) ? span : best), undefined)
  if (gap) return fillGap(parts, gap)
  const longest = parts.reduce<DayPart | undefined>((best, part) => (!best || durationOf(part) > durationOf(best) ? part : best), undefined)
  if (!longest || durationOf(longest) < 2 * MIN_PART_SECONDS) return parts
  const middle = clamp(
    snapOffset(longest.from + durationOf(longest) / 2, startSecond),
    longest.from + MIN_PART_SECONDS,
    longest.to - MIN_PART_SECONDS,
  )
  const halved = parts.map((part) => (part.key === longest.key ? { ...part, to: middle } : part))
  return fillGap(halved, { from: middle, to: longest.to })
}

export function updatePart(parts: DayPart[], key: number, patch: Partial<Omit<DayPart, 'key'>>): DayPart[] {
  return parts.map((part) => (part.key === key ? { ...part, ...patch } : part))
}

export function removePart(parts: DayPart[], key: number): DayPart[] {
  return parts.filter((part) => part.key !== key)
}

export function handlesOf(parts: DayPart[]): Handle[] {
  const handles: Handle[] = []
  parts.forEach((part, index) => {
    if (parts[index - 1]?.to !== part.from) handles.push({ kind: 'from', index, value: part.from })
    handles.push({ kind: parts[index + 1]?.from === part.to ? 'cut' : 'to', index, value: part.to })
  })
  return handles
}

/** How far a handle can go: never over a neighbour, out of the day, or below a minute. */
export function boundsOf(parts: DayPart[], handle: Handle, total: number): { min: number; max: number } {
  const part = parts[handle.index]
  if (!part) return { min: handle.value, max: handle.value }
  if (handle.kind === 'from') return { min: parts[handle.index - 1]?.to ?? 0, max: part.to - MIN_PART_SECONDS }
  const max = handle.kind === 'cut' ? (parts[handle.index + 1]?.to ?? total) - MIN_PART_SECONDS : (parts[handle.index + 1]?.from ?? total)
  return { min: part.from + MIN_PART_SECONDS, max }
}

/** Drag/arrow keys. A cut moves both parts' edges. */
export function moveHandle(parts: DayPart[], handle: Handle, value: number, total: number): DayPart[] {
  const { min, max } = boundsOf(parts, handle, total)
  if (min > max) return parts
  const at = clamp(value, min, max)
  return parts.map((part, index) => {
    if (index === handle.index) return handle.kind === 'from' ? { ...part, from: at } : { ...part, to: at }
    if (handle.kind === 'cut' && index === handle.index + 1) return { ...part, from: at }
    return part
  })
}

/** A typed Desde/Hasta: it may push into the neighbour (which shrinks), never past it or out of the day. */
export function setPartEdge(parts: DayPart[], index: number, edge: 'from' | 'to', value: number, total: number): DayPart[] {
  const part = parts[index]
  if (!part) return parts
  const previous = parts[index - 1]
  const next = parts[index + 1]
  const min = edge === 'from' ? (previous ? previous.from + MIN_PART_SECONDS : 0) : part.from + MIN_PART_SECONDS
  const max = edge === 'from' ? part.to - MIN_PART_SECONDS : next ? next.to - MIN_PART_SECONDS : total
  if (min > max) return parts
  const at = clamp(value, min, max)
  return parts.map((current, position) => {
    if (position === index) return { ...current, [edge]: at }
    if (edge === 'from' && position === index - 1 && current.to > at) return { ...current, to: at }
    if (edge === 'to' && position === index + 1 && current.from < at) return { ...current, from: at }
    return current
  })
}

/** Seconds past the minute at the start (every timezone in use today has minute-aligned offsets). */
export const secondsPastMinute = (iso: string) => new Date(iso).getUTCSeconds()

/** `HH:mm` of `started_at + offset` in the profile timezone (exact, DST included). */
export function clockLabel(startedAt: string, offset: number, timeZone: string): string {
  return zonedPartsOf(new Date(Date.parse(startedAt) + offset * 1000), timeZone).time
}

/**
 * A typed `HH:mm` → offset, by wall-clock difference from the zoned start time. Before the start means
 * after midnight (a day cut at 00:00) when that still falls inside the day.
 */
export function offsetOfTime(time: string, startTime: string, startSecond: number, total: number): number {
  // ponytail: wall-clock difference; on a DST day a typed time can be off by the shift. The timeline drag is exact.
  const offset = (minutesOf(time) - minutesOf(startTime)) * 60 - startSecond
  return offset < 0 && offset + DAY_SECONDS <= total ? offset + DAY_SECONDS : offset
}

/** Offsets of each whole hour inside the day, for the timeline ticks. */
export function hourTicks(total: number, startTime: string, startSecond: number): number[] {
  const intoHour = ((minutesOf(startTime) % 60) * 60 + startSecond) % 3600
  const ticks: number[] = []
  for (let tick = intoHour === 0 ? 0 : 3600 - intoHour; tick <= total; tick += 3600) ticks.push(tick)
  return ticks
}

export function secondsByProject(parts: DayPart[]): Map<number, number> {
  const totals = new Map<number, number>()
  for (const part of parts) {
    if (part.projectId !== null) totals.set(part.projectId, (totals.get(part.projectId) ?? 0) + durationOf(part))
  }
  return totals
}

export function isCompletePart(part: DayPart): boolean {
  const hasTask = part.newTaskTitle !== null ? part.newTaskTitle.trim() !== '' : part.taskId !== null
  return part.projectId !== null && hasTask && isValidComment(part.comment)
}

export function toPartInput(part: DayPart): DayPartInput {
  const isNew = part.newTaskTitle !== null
  return {
    from: part.from,
    to: part.to,
    project_id: part.projectId ?? 0,
    task_id: isNew ? null : part.taskId,
    new_task_title: isNew ? (part.newTaskTitle?.trim() ?? '') : null,
    comment: part.comment.trim(),
  }
}

export interface PartColor {
  fill: string
  ink: string
}

/** Tokens in index.css (dark ink on each fill passes AA). */
const palette: PartColor[] = [
  { fill: 'bg-part-1', ink: 'text-on-part-1' },
  { fill: 'bg-part-2', ink: 'text-on-part-2' },
  { fill: 'bg-part-3', ink: 'text-on-part-3' },
  { fill: 'bg-part-4', ink: 'text-on-part-4' },
  { fill: 'bg-part-5', ink: 'text-on-part-5' },
  { fill: 'bg-part-6', ink: 'text-on-part-6' },
]

export const noProjectColor: PartColor = { fill: 'bg-surface-2', ink: 'text-muted' }

/** By first appearance, so each project of the day gets its own color (they repeat after six). */
export function colorsByProject(parts: DayPart[]): Map<number, PartColor> {
  const colors = new Map<number, PartColor>()
  for (const part of parts) {
    if (part.projectId === null || colors.has(part.projectId)) continue
    colors.set(part.projectId, palette[colors.size % palette.length] ?? noProjectColor)
  }
  return colors
}
