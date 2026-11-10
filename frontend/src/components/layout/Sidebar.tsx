import { PanelLeftClose, PanelLeftOpen } from 'lucide-react'
import { Brand } from './Brand'
import type { NavGroup, NavItem } from './navigation'
import { SidebarNav } from './SidebarNav'

interface SidebarProps {
  groups: NavGroup[]
  footer: NavItem[]
  collapsed: boolean
  onToggleCollapsed: () => void
}

/** Desktop (≥ lg): fixed, collapsible to icons. Always dark, like desk. */
export function Sidebar({ groups, footer, collapsed, onToggleCollapsed }: SidebarProps) {
  return (
    <aside
      aria-label="Navegación"
      className={`sticky top-0 hidden h-dvh shrink-0 flex-col bg-sidebar transition-[width] duration-200 ease-out lg:flex ${collapsed ? 'w-[76px]' : 'w-64'}`}
    >
      <div className={`flex h-16 items-center border-b border-white/10 ${collapsed ? 'justify-center px-2' : 'px-5'}`}>
        <Brand collapsed={collapsed} />
      </div>
      <nav aria-label="Principal" className="flex min-h-0 flex-1 flex-col">
        <SidebarNav groups={groups} footer={footer} collapsed={collapsed} />
      </nav>
      <div className="border-t border-white/10 p-3">
        <button
          type="button"
          onClick={onToggleCollapsed}
          aria-label={collapsed ? 'Expandir menú' : 'Contraer menú'}
          title={collapsed ? 'Expandir menú' : 'Contraer menú'}
          className={`flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-sm text-sidebar-muted transition-colors hover:bg-sidebar-hover hover:text-white focus-visible:outline-2 focus-visible:outline-accent ${collapsed ? 'justify-center' : ''}`}
        >
          {collapsed ? <PanelLeftOpen aria-hidden="true" className="size-5" strokeWidth={1.75} /> : <PanelLeftClose aria-hidden="true" className="size-5" strokeWidth={1.75} />}
          {!collapsed && <span>Contraer menú</span>}
        </button>
      </div>
    </aside>
  )
}
