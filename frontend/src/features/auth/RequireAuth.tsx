import { Navigate, Outlet, useLocation } from 'react-router'
import { useCurrentUser } from './useAuth'

export function RequireAuth() {
  const { data: user, isPending } = useCurrentUser()
  const location = useLocation()

  if (isPending) return <p className="p-6 text-muted">Cargando…</p>
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  return <Outlet />
}
