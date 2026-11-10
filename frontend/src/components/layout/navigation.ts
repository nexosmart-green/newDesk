import {
  BarChart3,
  Building2,
  CalendarDays,
  CalendarRange,
  CheckSquare,
  Clock,
  FolderKanban,
  Settings,
  Users,
  type LucideIcon,
} from 'lucide-react'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { useCan, useCanAny } from '@/features/auth/useAuth'
import { CLIENTS_READ_PERMISSIONS } from '@/features/clients/types'
import { REPORTS_PERMISSION } from '@/features/reports/types'
import { SETTINGS_PERMISSIONS } from '@/features/settings/settingsTabs'
import { SHIFTS_PERMISSION } from '@/features/shifts/types'

export interface NavItem {
  to: string
  label: string
  icon: LucideIcon
  /** `end` matching: only the exact path is active (the home route). */
  exact?: boolean
}

export interface NavGroup {
  label: string
  items: NavItem[]
}

/**
 * Feature modules register their top-level entries here. Visibility is a UI hint only;
 * the backend policies decide (RNF-05).
 */
export function useNavigation(): { groups: NavGroup[]; footer: NavItem[] } {
  // Module switches (per role) first; then the finer permission inside the module.
  const hasProjects = useHasModule(MODULES.projects)
  const hasTime = useHasModule(MODULES.time)
  const hasCalendar = useHasModule(MODULES.calendar)
  const hasPeople = useHasModule(MODULES.people)
  const hasClients = useHasModule(MODULES.clients)
  const hasReports = useHasModule(MODULES.reports)
  const canReadClients = useCanAny(CLIENTS_READ_PERMISSIONS)
  const canManageShifts = useCan(SHIFTS_PERMISSION)
  const canSeeReports = useCan(REPORTS_PERMISSION)
  const canSeeSettings = useCanAny(SETTINGS_PERMISSIONS)

  const visible = (items: Array<NavItem | false>) => items.filter((item): item is NavItem => item !== false)

  return {
    groups: [
      {
        label: 'Mi trabajo',
        items: visible([
          hasProjects && { to: '/', label: 'Mis tareas', icon: CheckSquare, exact: true },
          hasTime && { to: '/mi-semana', label: 'Mi semana', icon: Clock },
          hasCalendar && { to: '/calendario', label: 'Calendario', icon: CalendarDays },
        ]),
      },
      {
        label: 'Gestión',
        items: visible([
          hasProjects && { to: '/proyectos', label: 'Proyectos', icon: FolderKanban },
          hasClients && canReadClients && { to: '/clientes', label: 'Clientes', icon: Building2 },
          hasPeople && { to: '/personas', label: 'Personas', icon: Users },
          hasCalendar && canManageShifts && { to: '/turnos', label: 'Turnos', icon: CalendarRange },
          hasReports && canSeeReports && { to: '/reportes/horas', label: 'Reportes', icon: BarChart3 },
        ]),
      },
    ],
    footer: visible([canSeeSettings && { to: '/configuracion', label: 'Configuración', icon: Settings }]),
  }
}

/** Title for the header: the nav entry that owns the current path (longest prefix wins). */
export function sectionTitle(pathname: string, groups: NavGroup[], footer: NavItem[]): string {
  const items = [...groups.flatMap((group) => group.items), ...footer]
  const extra: NavItem[] = [
    { to: '/tareas', label: 'Tarea', icon: CheckSquare },
    { to: '/perfil', label: 'Mi perfil', icon: Users },
    { to: '/seguridad', label: 'Seguridad', icon: Settings },
  ]
  const match = [...items, ...extra]
    .filter((item) => (item.to === '/' ? pathname === '/' : pathname === item.to || pathname.startsWith(`${item.to}/`)))
    .sort((a, b) => b.to.length - a.to.length)[0]

  return match?.label ?? 'newDesk'
}
