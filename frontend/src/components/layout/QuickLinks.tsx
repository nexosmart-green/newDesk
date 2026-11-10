import { Link } from 'react-router'
import { useNavigation } from './navigation'

/** The role's destinations as big links: the home of roles whose Panel has nothing to show. */
export function QuickLinks() {
  const { groups, footer } = useNavigation()
  const destinations = [...groups.flatMap((group) => group.items), ...footer].filter((item) => item.to !== '/')

  if (destinations.length === 0) {
    return (
      <p className="rounded-lg border border-border bg-surface p-4 text-sm text-muted">
        Tu rol todavía no tiene módulos habilitados. Pedile acceso a un administrador.
      </p>
    )
  }

  return (
    <ul className="grid max-w-2xl gap-3 sm:grid-cols-2">
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
  )
}
