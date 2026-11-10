import { Link } from 'react-router'
import { useCurrentUser } from '@/features/auth/useAuth'

/** Product mark + the company name (RF-11.01: never "Default Company"). */
export function Brand({ collapsed = false }: { collapsed?: boolean }) {
  const companyName = useCurrentUser().data?.company.name

  return (
    <Link
      to="/"
      aria-label={`newDesk — ${companyName ?? 'inicio'}`}
      className="flex min-h-11 items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
    >
      <span
        aria-hidden="true"
        className="grid size-9 shrink-0 place-items-center rounded-[10px] bg-brand text-lg font-bold leading-none text-white shadow-[inset_0_-2px_0_rgba(0,0,0,0.18)]"
      >
        n
      </span>
      {!collapsed && (
        <span className="min-w-0 leading-tight">
          <span className="block text-[15px] font-semibold tracking-tight text-white">newDesk</span>
          <span className="block truncate text-xs text-sidebar-muted">{companyName}</span>
        </span>
      )}
    </Link>
  )
}
