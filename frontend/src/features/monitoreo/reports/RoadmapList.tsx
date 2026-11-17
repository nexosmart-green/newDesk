import { useRef, useState, type CSSProperties, type MouseEvent, type PointerEvent } from 'react'
import { errorMessage } from '../api/monitoringApi'
import { useRoadmapRemove, useRoadmapReorder } from '../api/useReports'
import type { ReportRow } from '../api/types'
import { Icon } from '../components/Icon'
import { ErrorNote } from '../components/Panel'
import { Badge } from '../components/Status'
import { reportStatusMeta, urgencyMeta } from '../lib/reports'
import { useFloatingReports } from './floating'
import { dropIndex, moveItem, settleOffset, shiftOf, type Slot } from './sortable'

// La lista ordenable del roadmap (D-064): se arrastra con Pointer Events, o con las flechas.

// El arrastre en curso. pending: apretó pero todavía no se movió lo suficiente (puede ser un clic).
interface Drag { from: number; startY: number; dy: number; over: number; slots: Slot[]; settling: boolean }

const DRAG_THRESHOLD = 4 // px antes de que un clic pase a ser arrastre
const SETTLE_MS = 180

export function RoadmapList({ roadmap }: { roadmap: ReportRow[] }) {
  const reorder = useRoadmapReorder()
  const remove = useRoadmapRemove()
  const list = useRef<HTMLOListElement>(null)
  const pending = useRef<{ index: number; startY: number } | null>(null)
  const justDragged = useRef(false)
  const [drag, setDrag] = useState<Drag | null>(null)
  // Al soltar se muestra el orden nuevo enseguida, hasta que llegan los datos (optimistas) de la mutación.
  const [local, setLocal] = useState<{ base: ReportRow[]; ids: number[] } | null>(null)
  const items =
    local && local.base === roadmap ? local.ids.map((id) => roadmap.find((item) => item.id === id)).filter((item) => item !== undefined) : roadmap

  if (items.length === 0) {
    return (
      <p className="px-1 py-6 text-center text-sm text-muted">
        Sumá reportes desde la lista de Reportes o desde cada reporte, y ordenalos acá.
      </p>
    )
  }

  const commit = (from: number, to: number) => {
    if (from === to || to < 0 || to >= items.length) return
    const ids = moveItem(items.map((item) => item.id), from, to)
    setLocal({ base: roadmap, ids })
    reorder.mutate(ids)
  }

  // Con mouse se arrastra la tarjeta entera; con el dedo, solo desde el ícono (si no, no se podría scrollear).
  const onPointerDown = (index: number) => (event: PointerEvent<HTMLLIElement>) => {
    const target = event.target as HTMLElement
    if (drag || event.button !== 0 || target.closest('[data-no-drag]')) return
    if (event.pointerType !== 'mouse' && !target.closest('[data-grip]')) return
    pending.current = { index, startY: event.clientY }
  }
  const onPointerMove = (event: PointerEvent<HTMLLIElement>) => {
    if (drag && !drag.settling) {
      const dy = event.clientY - drag.startY
      setDrag({ ...drag, dy, over: dropIndex(drag.slots, drag.from, dy) })
      return
    }
    const start = pending.current
    if (!start || Math.abs(event.clientY - start.startY) < DRAG_THRESHOLD) return
    pending.current = null
    const slots = Array.from(list.current?.children ?? [], (child) => {
      const rect = child.getBoundingClientRect()
      return { top: rect.top, height: rect.height }
    })
    event.currentTarget.setPointerCapture(event.pointerId)
    const dy = event.clientY - start.startY
    setDrag({ from: start.index, startY: start.startY, dy, over: dropIndex(slots, start.index, dy), slots, settling: false })
  }
  // Al soltar, la tarjeta se acomoda en su lugar y recién ahí cambia el orden.
  const onPointerUp = () => {
    pending.current = null
    if (!drag || drag.settling) return
    justDragged.current = true
    setDrag({ ...drag, dy: settleOffset(drag.from, drag.over, drag.slots), settling: true })
    window.setTimeout(() => {
      commit(drag.from, drag.over)
      setDrag(null)
    }, SETTLE_MS)
  }
  const onPointerCancel = () => {
    pending.current = null
    if (drag && !drag.settling) setDrag(null)
  }
  // El clic que sigue a un arrastre no abre el reporte.
  const onClickCapture = (event: MouseEvent) => {
    if (!justDragged.current) return
    justDragged.current = false
    event.preventDefault()
    event.stopPropagation()
  }

  // Mientras se arrastra, los números ya muestran cómo va a quedar.
  const previewOrder = drag ? moveItem(items.map((_, index) => index), drag.from, drag.over) : null

  const styleOf = (index: number): CSSProperties | undefined => {
    if (!drag) return undefined
    const easing = `transform ${SETTLE_MS}ms cubic-bezier(0.2, 0, 0, 1)`
    if (index === drag.from) return { transform: `translateY(${drag.dy}px) scale(${drag.settling ? 1 : 1.02})`, transition: drag.settling ? easing : 'none', zIndex: 10, position: 'relative' }
    return { transform: `translateY(${shiftOf(index, drag.from, drag.over, drag.slots)}px)`, transition: easing }
  }

  return (
    <div className="space-y-2">
      {reorder.isError && <ErrorNote>{errorMessage(reorder.error)}</ErrorNote>}
      {remove.isError && <ErrorNote>{errorMessage(remove.error)}</ErrorNote>}
      <ol ref={list} aria-label="Mi roadmap" className="space-y-1.5 pb-6">
        {items.map((item, index) => (
          <li
            key={item.id}
            style={styleOf(index)}
            onPointerDown={onPointerDown(index)}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerCancel}
            onClickCapture={onClickCapture}
            className={`group flex items-start gap-1.5 rounded-xl bg-surface p-2 select-none ${
              drag?.from === index ? 'cursor-grabbing shadow-[var(--shadow-pop)]' : 'shadow-[var(--shadow)]'
            } ${item.status === 'resolved' && drag?.from !== index ? 'opacity-60' : ''}`}
          >
            <span data-grip className="-m-1 grid shrink-0 cursor-grab touch-none place-items-center p-1 text-faint" aria-hidden="true">
              <Icon name="grip" size={14} />
            </span>
            <span className="num mt-0.5 w-4 shrink-0 text-xs font-semibold text-muted">{(previewOrder ? previewOrder.indexOf(index) : index) + 1}</span>
            <RoadmapItem item={item} />
            <span data-no-drag className="flex shrink-0 flex-col">
              <button type="button" className="btn btn-ghost size-6 px-0" aria-label={`Subir el #${item.id}`} disabled={index === 0 || reorder.isPending} onClick={() => commit(index, index - 1)}>
                <Icon name="up" size={12} />
              </button>
              <button type="button" className="btn btn-ghost size-6 px-0" aria-label={`Bajar el #${item.id}`} disabled={index === items.length - 1 || reorder.isPending} onClick={() => commit(index, index + 1)}>
                <Icon name="down" size={12} />
              </button>
              <button type="button" className="btn btn-ghost size-6 px-0" aria-label={`Sacar el #${item.id} del roadmap`} disabled={remove.isPending} onClick={() => remove.mutate(item.id)}>
                <Icon name="close" size={12} />
              </button>
            </span>
          </li>
        ))}
      </ol>
    </div>
  )
}

// Un clic lo abre en una ventana flotante.
function RoadmapItem({ item }: { item: ReportRow }) {
  const floating = useFloatingReports()
  const resolved = item.status === 'resolved'
  return (
    <button type="button" className="min-w-0 flex-1 rounded-lg text-left" onClick={() => floating.open(item.id)} title="Abrir en una ventana flotante">
      <span className="flex flex-wrap items-center gap-1.5">
        {resolved ? <Icon name="check" size={12} className="text-ok" /> : <Badge tone={urgencyMeta[item.urgency].tone}>{urgencyMeta[item.urgency].label}</Badge>}
        <span className={`text-sm font-medium ${resolved ? 'line-through' : ''}`}>#{item.id} {item.client_name}</span>
        {item.unread && <span className="size-1.5 rounded-full bg-info" aria-label="Mensaje nuevo" />}
      </span>
      <span className="mt-0.5 block truncate text-xs text-muted">{item.project_name} · {item.description}</span>
      {!resolved && item.status !== 'received' && <span className="mt-1 block text-[0.6875rem] text-faint">{reportStatusMeta[item.status].label}</span>}
    </button>
  )
}
