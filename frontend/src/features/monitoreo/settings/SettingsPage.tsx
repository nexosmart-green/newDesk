import { Navigate, useNavigate, useParams } from 'react-router'
import { useCan } from '@/features/auth/useAuth'
import { Tabs } from '../components/Tabs'
import { ServersAdmin } from './ServersAdmin'

// Usuarios y sesiones son los de newDesk (RF-13, diferencia 5): acá quedan Servidores (admin).
// The personal MCP tokens live in Mi perfil → Conexiones de IA (/perfil?tab=ia), which also shows the monitoreo command.
const allTabs = [{ id: 'servers', label: 'Servidores', permission: 'monitoring.manage' }] as const

type SettingsTab = (typeof allTabs)[number]['id']

export function SettingsPage() {
  const allowed = { 'monitoring.manage': useCan('monitoring.manage') }
  const params = useParams()
  const navigate = useNavigate()
  const tabs = allTabs.filter((tab) => allowed[tab.permission])
  const active: SettingsTab | undefined = tabs.find((tab) => tab.id === params.tab)?.id ?? tabs[0]?.id

  if (!active) return <Navigate to="/monitoreo" replace />

  return (
    <div className="rise space-y-5">
      <h1 className="title">Configuración</h1>
      <Tabs label="Configuración" tabs={tabs} active={active} onChange={(id) => navigate(`/monitoreo/settings/${id}`)} />
      <div key={active} className="fade-in">
        {active === 'servers' && <ServersAdmin />}
      </div>
    </div>
  )
}
