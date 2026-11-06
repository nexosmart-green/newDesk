import { Navigate, Outlet, useLocation } from 'react-router'
import { useCurrentUser } from './useAuth'

export function RequireAuth() {
  const { data: user, isPending } = useCurrentUser()
  const location = useLocation()

  if (isPending) return <p className="p-6 text-muted">Cargando…</p>
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  // A client's user only has the monitoring portal (D-037); the portal sends the team back here.
  if (user.client) return <Navigate to="/portal" replace />
  return <Outlet />
}
