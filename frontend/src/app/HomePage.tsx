import { Link } from 'react-router'
import { useNavigation } from '@/components/layout/navigation'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { useCurrentUser } from '@/features/auth/useAuth'
import { MyTasksPage } from '@/features/tasks/MyTasksPage'

/** `/` is "Mis tareas"; a role without the projects module lands on a welcome with what it does have. */
export function HomePage() {
  const hasProjects = useHasModule(MODULES.projects)
  return hasProjects ? <MyTasksPage /> : <WelcomePage />
}

function WelcomePage() {
  const { data: user } = useCurrentUser()
  const { groups, footer } = useNavigation()
  const destinations = [...groups.flatMap((group) => group.items), ...footer].filter((item) => item.to !== '/')

  return (
    <section className="max-w-2xl space-y-6">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight">Hola, {user?.first_name}</h1>
        <p className="text-muted">Elegí por dónde seguir.</p>
      </header>
      {destinations.length === 0 ? (
        <p className="rounded-lg border border-border bg-surface p-4 text-sm text-muted">
          Tu rol todavía no tiene módulos habilitados. Pedile acceso a un administrador.
        </p>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2">
          {destinations.map((item) => {
            const Icon = item.icon
            return (
              <li key={item.to}>
                <Link
                  to={item.to}
                  className="flex min-h-16 items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3 font-medium transition-colors hover:border-brand/60 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent"
                >
                  <span className="grid size-10 place-items-center rounded-lg bg-brand/15 text-accent">
                    <Icon aria-hidden="true" className="size-5" strokeWidth={1.75} />
                  </span>
                  {item.label}
                </Link>
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}
