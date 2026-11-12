import { ListChecks } from 'lucide-react'
import { useRef, useState } from 'react'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Panel } from '../TaskPanel'
import type { ChecklistItem, Task } from '../types'
import { ChecklistAddForm } from './ChecklistAddForm'
import { ChecklistItemRow } from './ChecklistItemRow'
import { ChecklistProgress } from './ChecklistProgress'
import { moveItem } from './moveItem'
import { progressOf, useDeleteChecklistItem, useReorderChecklist, useToggleChecklistItem } from './useChecklist'
import { useChecklistDrag } from './useChecklistDrag'

/** RF-04.08, under the description: steps to tick, with progress, inline rename and reorder. */
export function TaskChecklist({ task }: { task: Task }) {
  const items = task.checklist ?? []
  const toggle = useToggleChecklistItem(task.code)
  const reorder = useReorderChecklist(task.code)
  const remove = useDeleteChecklistItem(task.code)
  const [deleting, setDeleting] = useState<ChecklistItem | null>(null)
  const [announcement, setAnnouncement] = useState('')
  const addRef = useRef<HTMLInputElement>(null)
  const ids = items.map((item) => item.id)

  function move(id: number, toIndex: number) {
    const next = moveItem(ids, id, toIndex)
    if (next === ids) return
    reorder.mutate(next)
    const item = items.find((candidate) => candidate.id === id)
    setAnnouncement(`«${item?.text ?? ''}» quedó en el lugar ${next.indexOf(id) + 1} de ${next.length}.`)
  }
  const dragFor = useChecklistDrag(ids, move)

  function confirmDelete() {
    if (!deleting) return
    remove.mutate(deleting.id, {
      onSuccess: () => {
        setDeleting(null)
        // The row (and the trash button that had focus) is gone: continue where new steps are typed.
        requestAnimationFrame(() => addRef.current?.focus())
      },
    })
  }

  return (
    <Panel title="Subtareas" action={items.length > 0 ? <ChecklistProgress progress={progressOf(items)} /> : undefined}>
      {items.length === 0 ? (
        <div className="flex items-start gap-3 rounded-lg border border-dashed border-border p-3">
          <ListChecks aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-muted" />
          <p className="text-sm text-muted">Dividí la tarea en pasos y tildalos a medida que avanzan. Escribí el primero acá abajo.</p>
        </div>
      ) : (
        <ul aria-label="Subtareas" className="-mx-1.5 space-y-0.5">
          {items.map((item, index) => (
            <ChecklistItemRow
              key={item.id}
              code={task.code}
              item={item}
              index={index}
              count={items.length}
              drag={dragFor(item.id, index)}
              onToggle={(done) => toggle.mutate({ id: item.id, done })}
              onMove={(toIndex) => move(item.id, toIndex)}
              onDelete={() => setDeleting(item)}
            />
          ))}
        </ul>
      )}
      <ChecklistAddForm code={task.code} count={items.length} inputRef={addRef} />
      <p aria-live="polite" className="sr-only">
        {announcement}
      </p>
      {deleting && (
        <ConfirmDialog
          title={`¿Borrar «${deleting.text}»?`}
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={confirmDelete}
          onClose={() => {
            setDeleting(null)
            remove.reset()
          }}
        >
          <p>La subtarea sale de esta tarea para todos. El borrado queda registrado en el historial.</p>
        </ConfirmDialog>
      )}
    </Panel>
  )
}
