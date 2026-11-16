import { ArrowLeftRight, Building2, House } from 'lucide-react'
import { workModeLabels, type WorkMode } from '@/features/shifts/types'

const icons = { office: Building2, remote: House, hybrid: ArrowLeftRight } as const

/** Oficina / Remoto / Híbrido as an icon, with the word for screen readers and on hover. */
export function WorkModeIcon({ mode, withLabel = false }: { mode: WorkMode; withLabel?: boolean }) {
  const Icon = icons[mode]
  return (
    <span className="relative inline-flex items-center gap-1 text-muted" title={workModeLabels[mode]}>
      <Icon aria-hidden="true" className="size-3.5 shrink-0" />
      <span className={withLabel ? 'text-xs' : 'sr-only'}>{workModeLabels[mode]}</span>
    </span>
  )
}
