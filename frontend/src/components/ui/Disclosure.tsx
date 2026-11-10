import { ChevronDown } from 'lucide-react'
import { useId, useState, type ReactNode } from 'react'

interface DisclosureProps {
  label: string
  /** One line telling what is inside while it's closed (e.g. "Vence 12/11"). */
  summary?: string
  defaultOpen?: boolean
  children: ReactNode
}

/** Progressive disclosure for optional fields: closed, it still says what it holds. */
export function Disclosure({ label, summary, defaultOpen = false, children }: DisclosureProps) {
  const [open, setOpen] = useState(defaultOpen)
  const panelId = useId()

  return (
    <div className="rounded-xl border border-border">
      <button
        type="button"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((current) => !current)}
        className="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-left text-sm transition-colors hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent"
      >
        <span className="font-medium">{label}</span>
        {!open && summary && <span className="min-w-0 flex-1 truncate text-muted">{summary}</span>}
        <ChevronDown
          aria-hidden="true"
          className={`ml-auto size-4 shrink-0 text-muted transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
        />
      </button>
      <div id={panelId} hidden={!open} className="space-y-4 border-t border-border px-4 pb-4 pt-3">
        {children}
      </div>
    </div>
  )
}
