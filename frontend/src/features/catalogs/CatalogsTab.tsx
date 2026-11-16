import { MODULES, useHasModule } from '@/features/auth/modules'
import { useCan } from '@/features/auth/useAuth'
import { TaskCategoriesPanel } from '@/features/taskCatalogs/TaskCategoriesPanel'
import { TaskLabelsPanel } from '@/features/taskCatalogs/TaskLabelsPanel'
import { CatalogList } from './CatalogList'
import { catalogTypes } from './types'

/**
 * RF-01.08: puestos, áreas, tipos de empleo y habilidades (`users.manage`), plus the task
 * categories and labels (`projects.manage`, module projects). Each block shows only to who edits it.
 */
export function CatalogsTab() {
  const canManagePeople = useCan('users.manage')
  const hasProjects = useHasModule(MODULES.projects)
  const canManageTasks = useCan('projects.manage') && hasProjects

  return (
    <div className="space-y-8">
      {canManageTasks && (
        <div className="space-y-3">
          <h2 className="text-base font-semibold">Tareas</h2>
          <div className="grid gap-6 md:grid-cols-2">
            <TaskCategoriesPanel />
            <TaskLabelsPanel />
          </div>
        </div>
      )}
      {canManagePeople && (
        <div className="space-y-3">
          {canManageTasks && <h2 className="text-base font-semibold">Personas</h2>}
          <div className="grid gap-6 md:grid-cols-2">
            {catalogTypes.map((type) => (
              <CatalogList key={type} type={type} />
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
