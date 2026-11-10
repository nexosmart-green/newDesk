import { Tag } from 'lucide-react'
import type { TaskCategory, TaskLabel } from '@/features/tasks/types'

/**
 * Label colors are chosen by people, so they never carry the text: a tinted fill + dot of the
 * color, and the name in the regular text color (AA on any choice).
 */
export function LabelChip({ label, size = 'sm' }: { label: Pick<TaskLabel, 'name' | 'color'>; size?: 'sm' | 'md' }) {
  return (
    <span
      className={`inline-flex max-w-full items-center gap-1.5 rounded-full border font-medium text-text ${size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm'}`}
      style={{ backgroundColor: `${label.color}26`, borderColor: `${label.color}73` }}
    >
      <span aria-hidden="true" className="size-2 shrink-0 rounded-full" style={{ backgroundColor: label.color }} />
      <span className="truncate">{label.name}</span>
    </span>
  )
}

export function LabelChips({ labels }: { labels: TaskLabel[] | undefined }) {
  if (!labels || labels.length === 0) return null
  return (
    <ul className="flex flex-wrap gap-1" aria-label="Etiquetas">
      {labels.map((label) => (
        <li key={label.id} className="min-w-0">
          <LabelChip label={label} />
        </li>
      ))}
    </ul>
  )
}

/** The category reads as a quiet tag: it groups work, it isn't a state. */
export function CategoryTag({ category }: { category: Pick<TaskCategory, 'name'> | null | undefined }) {
  if (!category) return null
  return (
    <span className="inline-flex items-center gap-1 text-xs text-muted">
      <Tag aria-hidden="true" className="size-3.5" strokeWidth={1.75} />
      <span className="sr-only">Categoría:</span>
      {category.name}
    </span>
  )
}
