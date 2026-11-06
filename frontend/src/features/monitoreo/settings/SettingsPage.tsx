import { Navigate, useNavigate, useParams } from 'react-router'
import { useCan } from '@/features/auth/useAuth'
import { Tabs } from '../components/Tabs'
import { ServersAdmin } from './ServersAdmin'

// Usuarios, sesiones y tokens son los de newDesk (RF-13, diferencia 5): acá queda solo Servidores (admin).
const allTabs = [{ id: 'servers', label: 'Servidores', admin: true }] as const

type SettingsTab = (typeof allTabs)[number]['id']

export function SettingsPage() {
  const isAdmin = useCan('monitoring.manage')
  const params = useParams()
  const navigate = useNavigate()
  const tabs = allTabs.filter((tab) => isAdmin || !tab.admin)
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
