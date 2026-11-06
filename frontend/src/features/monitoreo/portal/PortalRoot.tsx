import '@fontsource-variable/inter/wght.css'
import '../skin.css'

import { Outlet } from 'react-router'
import { usePortalThemeRoot } from '../lib/theme'

// Todo /portal (también el login) va con la piel de monitoreo y su propio claro/oscuro, fuera del layout de newDesk.
// Aparte de PortalPages: el login no baja la página de estado (ni uPlot).
export function PortalRoot() {
  usePortalThemeRoot()
  return (
    <div data-skin="monitoreo" className="min-h-dvh">
      <Outlet />
    </div>
  )
}
