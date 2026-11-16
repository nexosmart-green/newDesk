import { ChevronLeft } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router'

/** "Back to the list" above a detail page: where it goes, with a chevron (never a typed arrow). */
export function BackLink({ to, children }: { to: string; children: ReactNode }) {
  return (
    <Link
      to={to}
      className="-ml-1 inline-flex min-h-9 items-center gap-0.5 rounded-md pr-2 text-sm text-accent hover:underline focus-visible:outline-2 focus-visible:outline-accent coarse:min-h-11"
    >
      <ChevronLeft aria-hidden="true" className="size-4" />
      {children}
    </Link>
  )
}
