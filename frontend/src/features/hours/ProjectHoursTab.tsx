import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { useCan } from '@/features/auth/useAuth'
import { useProjectFromOutlet } from '@/features/projects/projectContext'
import { AllocationFormDialog } from './AllocationFormDialog'
import { AllocationHistory } from './AllocationHistory'
import { ExtensionRequestsPanel } from './ExtensionRequestsPanel'
import { HoursBar } from './HoursBar'
import { ProjectEntries } from './ProjectEntries'
import { useAllocateHours, useProjectHours } from './useHours'

/** RF-05.11 "Horas": the bag, its movements, pending extension requests and every entry. */
export function ProjectHoursTab() {
  const project = useProjectFromOutlet()
  const canAllocate = useCan('hours.allocate')
  const hours = useProjectHours(project.id)
  const allocate = useAllocateHours(project.id)
  const [allocating, setAllocating] = useState(false)

  const close = () => {
    allocate.reset()
    setAllocating(false)
  }

  return (
    <div className="space-y-6">
      <section className="space-y-3 rounded-lg border border-border bg-surface p-4" aria-labelledby="hour-bag">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 id="hour-bag" className="font-semibold">
            Bolsa de horas
          </h2>
          {canAllocate && project.type === 'bag' && <Button onClick={() => setAllocating(true)}>Cargar horas</Button>}
        </div>
        {hours.data ? <HoursBar bag={hours.data} /> : <PageState isPending={hours.isPending} error={hours.error} shape="detail" onRetry={() => void hours.refetch()} />}
      </section>
      {canAllocate && <ExtensionRequestsPanel projectId={project.id} />}
      {hours.data && hours.data.has_cap && (
        <section className="space-y-2" aria-labelledby="allocation-history">
          <h2 id="allocation-history" className="font-semibold">
            Movimientos de la bolsa
          </h2>
          <AllocationHistory allocations={hours.data.allocations} />
        </section>
      )}
      <ProjectEntries project={project} />
      {allocating && (
        <AllocationFormDialog
          title="Cargar horas"
          submitLabel="Cargar"
          allowSubtract
          pending={allocate.isPending}
          error={allocate.error}
          onSubmit={(input) => allocate.mutate(input, { onSuccess: close })}
          onClose={close}
        />
      )}
    </div>
  )
}
