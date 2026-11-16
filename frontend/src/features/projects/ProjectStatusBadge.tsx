import { Badge } from '@/components/ui/Badge'
import { projectStatusLabels, projectStatusTones, type ProjectStatus } from './types'

export function ProjectStatusBadge({ status }: { status: ProjectStatus }) {
  return <Badge tone={projectStatusTones[status]}>{projectStatusLabels[status]}</Badge>
}
