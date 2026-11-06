import { Navigate, Outlet, useLocation } from 'react-router'
import { mustSetUpTwoFactor } from './types'
import { useCurrentUser } from './useAuth'

export const SECURITY_PATH = '/seguridad'

export function RequireAuth() {
  const { data: user, isPending } = useCurrentUser()
  const location = useLocation()

  if (isPending) return <p className="p-6 text-muted">Cargando…</p>
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  // RF-01.02: until the mandatory 2FA is on, the backend rejects everything else (403).
  if (mustSetUpTwoFactor(user) && location.pathname !== SECURITY_PATH) return <Navigate to={SECURITY_PATH} replace />
  return <Outlet />
}
