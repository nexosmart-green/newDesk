import { useRef, type KeyboardEvent, type PointerEvent } from 'react'
import { formatDuration } from '@/lib/duration'
import {
  boundsOf,
  durationOf,
  gapsOf,
  handlesOf,
  hourTicks,
  moveHandle,
  snapOffset,
  stepOffset,
  type DayPart,
  type Handle,
  type PartColor,
  type Span,
} from './dayParts'

interface DayTimelineProps {
  parts: DayPart[]
  total: number
  /** Zoned `HH:mm` of the start and the seconds past that minute: drags snap to whole wall-clock minutes. */
  startTime: string
  startSecond: number
  labelAt: (offset: number) => string
  colorOf: (part: DayPart) => PartColor
  nameOf: (part: DayPart) => string
  activeKey: number | null
  onChange: (parts: DayPart[]) => void
  onSelect: (key: number) => void
  onFillGap: (gap: Span) => void
}

const DRAG_THRESHOLD_PX = 4
const STEP_KEYS: Record<string, number> = { ArrowRight: 1, ArrowUp: 1, ArrowLeft: -1, ArrowDown: -1 }
const hatched = 'bg-[repeating-linear-gradient(135deg,var(--color-surface-2)_0_6px,var(--color-border)_6px_12px)]'

function handleName({ kind, index }: Handle): string {
  if (kind === 'cut') return `Corte entre la parte ${index + 1} y la ${index + 2}`
  return kind === 'from' ? `Inicio de la parte ${index + 1}` : `Fin de la parte ${index + 1}`
}

/** RF-05.19 "Línea del día": the clock span as a bar, one colored segment per part, hatched gaps, draggable cuts. */
export function DayTimeline({ parts, total, startTime, startSecond, labelAt, colorOf, nameOf, activeKey, onChange, onSelect, onFillGap }: DayTimelineProps) {
  const track = useRef<HTMLDivElement>(null)
  const drag = useRef<{ handle: Handle; startX: number; moving: boolean } | null>(null)
  const percent = (offset: number) => `${(offset / total) * 100}%`
  const placed = (span: Span) => ({ left: percent(span.from), width: percent(durationOf(span)) })

  function offsetAt(clientX: number): number {
    const rect = track.current?.getBoundingClientRect()
    return rect && rect.width > 0 ? ((clientX - rect.left) / rect.width) * total : 0
  }

  const startDrag = (handle: Handle) => (event: PointerEvent<HTMLDivElement>) => {
    if (event.button !== 0) return
    event.currentTarget.setPointerCapture(event.pointerId)
    drag.current = { handle, startX: event.clientX, moving: false }
  }
  // On the track, not the handle: a handle that turns into a cut remounts, and its pointer events then bubble here.
  const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
    const current = drag.current
    if (!current || (!current.moving && Math.abs(event.clientX - current.startX) < DRAG_THRESHOLD_PX)) return
    current.moving = true
    onChange(moveHandle(parts, current.handle, snapOffset(offsetAt(event.clientX), startSecond), total))
  }
  const endDrag = () => {
    drag.current = null
  }

  const onKeyDown = (handle: Handle) => (event: KeyboardEvent<HTMLDivElement>) => {
    const direction = STEP_KEYS[event.key]
    if (!direction) return
    event.preventDefault()
    onChange(moveHandle(parts, handle, stepOffset(handle.value, direction * (event.shiftKey ? 15 : 1), startSecond), total))
  }

  return (
    <div className="space-y-1.5">
      <div className="flex flex-wrap justify-between gap-x-4 text-xs text-muted sm:text-sm">
        <span>Arrastrá los cortes o tocá un hueco para sumar una parte</span>
        <span className="hidden sm:inline">Lo rayado no se cuenta</span>
      </div>
      <div
        ref={track}
        onPointerMove={onPointerMove}
        onPointerUp={endDrag}
        onPointerCancel={endDrag}
        className="relative h-11 rounded-lg border border-border bg-bg sm:h-14"
      >
        {gapsOf(parts, total).map((gap) => (
          <button
            key={`gap-${gap.from}`}
            type="button"
            onClick={() => onFillGap(gap)}
            aria-label={`Sumar una parte de ${labelAt(gap.from)} a ${labelAt(gap.to)}`}
            title={`${labelAt(gap.from)}–${labelAt(gap.to)} sin asignar: tocá para sumar una parte`}
            className={`absolute inset-y-1 grid cursor-copy place-items-center overflow-hidden rounded-md text-xs text-muted transition-[filter] hover:brightness-125 focus-visible:outline-2 focus-visible:outline-accent sm:inset-y-1.5 ${hatched}`}
            style={placed(gap)}
          >
            <span className="hidden truncate px-1 sm:block">{formatDuration(durationOf(gap))}</span>
          </button>
        ))}
        {parts.map((part, index) => {
          const color = colorOf(part)
          const active = part.key === activeKey
          const label = `${nameOf(part)} · ${formatDuration(durationOf(part))}`
          return (
            <button
              key={part.key}
              type="button"
              onClick={() => onSelect(part.key)}
              aria-label={`Parte ${index + 1}: ${nameOf(part)}, de ${labelAt(part.from)} a ${labelAt(part.to)}`}
              aria-current={active || undefined}
              title={label}
              className={`absolute inset-y-1 flex cursor-pointer items-center overflow-hidden rounded-md px-2.5 text-left text-[13px] font-semibold ring-inset focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent sm:inset-y-1.5 ${color.fill} ${color.ink} ${active ? 'ring-2 ring-text/80' : 'ring-1 ring-black/15'}`}
              style={placed(part)}
            >
              <span className="hidden truncate sm:block">{label}</span>
            </button>
          )
        })}
        {handlesOf(parts).map((handle) => {
          const { min, max } = boundsOf(parts, handle, total)
          return (
            <div
              key={`${handle.kind}-${parts[handle.index]?.key}`}
              role="slider"
              tabIndex={0}
              aria-label={handleName(handle)}
              aria-orientation="horizontal"
              aria-valuemin={min}
              aria-valuemax={max}
              aria-valuenow={handle.value}
              aria-valuetext={labelAt(handle.value)}
              onPointerDown={startDrag(handle)}
              onKeyDown={onKeyDown(handle)}
              className="group absolute -inset-y-1.5 z-10 -ml-4 flex w-8 cursor-ew-resize touch-none select-none justify-center outline-none"
              style={{ left: percent(handle.value) }}
            >
              <span
                aria-hidden="true"
                className="w-1.5 rounded-full bg-text shadow-[0_0_0_3px_var(--color-surface)] transition-transform group-hover:scale-x-150 group-focus-visible:outline-2 group-focus-visible:outline-offset-2 group-focus-visible:outline-accent group-active:scale-x-150"
              />
            </div>
          )
        })}
      </div>
      <div aria-hidden="true" className="tabular relative h-4 text-[11px] text-muted">
        {hourTicks(total, startTime, startSecond).map((tick) => {
          const hour = labelAt(tick).slice(0, 2)
          // Phones get every third hour.
          return (
            <span key={tick} className={`absolute ${Number(hour) % 3 === 0 ? '' : 'hidden sm:block'}`} style={{ left: percent(tick) }}>
              {hour}
            </span>
          )
        })}
      </div>
    </div>
  )
}
