import type { ChecklistProgress as Progress } from '../types'

/** "3 de 5" and a bar that fills (green when every step is done). Scales, so it never reflows. */
export function ChecklistProgress({ progress }: { progress: Progress }) {
  const { done, total } = progress
  const complete = total > 0 && done === total
  const ratio = total === 0 ? 0 : done / total
  return (
    <div className="flex items-center gap-3">
      <span className={`tabular shrink-0 text-sm font-medium ${complete ? 'text-success' : 'text-muted'}`}>
        {done} de {total}
      </span>
      <div
        role="progressbar"
        aria-label="Subtareas hechas"
        aria-valuemin={0}
        aria-valuemax={total}
        aria-valuenow={done}
        aria-valuetext={`${done} de ${total}`}
        className="h-1.5 w-24 overflow-hidden rounded-full bg-surface-2 sm:w-32"
      >
        <div
          className={`h-full origin-left rounded-full transition-[transform,background-color] duration-300 ease-out motion-reduce:transition-none ${complete ? 'bg-success' : 'bg-brand'}`}
          style={{ transform: `scaleX(${ratio})` }}
        />
      </div>
    </div>
  )
}
