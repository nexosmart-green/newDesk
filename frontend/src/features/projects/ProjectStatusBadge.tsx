import { Badge, type BadgeTone } from '@/components/ui/Badge'
import { projectStatusLabels, type ProjectStatus } from './types'

const tones: Record<ProjectStatus, BadgeTone> = {
  active: 'success',
  paused: 'warning',
  finished: 'accent',
  cancelled: 'neutral',
}

export function ProjectStatusBadge({ status }: { status: ProjectStatus }) {
  return <Badge tone={tones[status]}>{projectStatusLabels[status]}</Badge>
}
