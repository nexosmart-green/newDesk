import { LoaderCircle } from 'lucide-react'
import { Navigate, Outlet, useLocation } from 'react-router'
import { mustSetUpTwoFactor } from './types'
import { useCurrentUser } from './useAuth'

export const SECURITY_PATH = '/seguridad'

export function RequireAuth() {
  const { data: user, isPending } = useCurrentUser()
  const location = useLocation()

  if (isPending) {
    return (
      <p role="status" className="flex min-h-dvh items-center justify-center gap-2 text-sm text-muted">
        <LoaderCircle aria-hidden="true" className="size-5 animate-spin text-accent" />
        Cargando…
      </p>
    )
  }
  // Keeps the query too, so a filtered list or an open tab survives a lost session.
  if (!user) return <Navigate to="/login" replace state={{ from: `${location.pathname}${location.search}` }} />
  // RF-01.02: until the mandatory 2FA is on, the backend rejects everything else (403).
  if (mustSetUpTwoFactor(user) && location.pathname !== SECURITY_PATH) return <Navigate to={SECURITY_PATH} replace />
  return <Outlet />
}
