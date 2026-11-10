import { NavLink } from 'react-router'
import type { NavGroup, NavItem } from './navigation'

interface SidebarNavProps {
  groups: NavGroup[]
  footer: NavItem[]
  collapsed: boolean
  onNavigate?: () => void
}

function NavEntry({ item, collapsed, onNavigate }: { item: NavItem; collapsed: boolean; onNavigate?: () => void }) {
  const Icon = item.icon

  return (
    <li>
      <NavLink
        to={item.to}
        end={item.exact}
        onClick={onNavigate}
        title={collapsed ? item.label : undefined}
        aria-label={collapsed ? item.label : undefined}
        className={({ isActive }) =>
          [
            'group relative flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm transition-colors duration-150',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
            collapsed ? 'justify-center' : '',
            isActive
              ? 'bg-brand/15 font-semibold text-white'
              : 'text-sidebar-text hover:bg-sidebar-hover hover:text-white',
          ].join(' ')
        }
      >
        {({ isActive }) => (
          <>
            {/* The active page is marked by shape too, not only by color (a11y). */}
            <span
              aria-hidden="true"
              className={`absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand transition-opacity ${isActive ? 'opacity-100' : 'opacity-0'}`}
            />
            <Icon aria-hidden="true" className={`size-5 shrink-0 ${isActive ? 'text-accent' : 'text-sidebar-muted group-hover:text-sidebar-text'}`} strokeWidth={1.75} />
            {!collapsed && <span className="truncate">{item.label}</span>}
          </>
        )}
      </NavLink>
    </li>
  )
}

/** Shared by the desktop sidebar and the mobile drawer. */
export function SidebarNav({ groups, footer, collapsed, onNavigate }: SidebarNavProps) {
  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        {groups
          .filter((group) => group.items.length > 0)
          .map((group) => (
            <section key={group.label} aria-label={group.label}>
              {collapsed ? (
                <div aria-hidden="true" className="mx-3 mb-2 border-t border-white/10" />
              ) : (
                <h2 className="mb-2 px-3 text-xs font-medium text-sidebar-muted">{group.label}</h2>
              )}
              <ul className="space-y-1">
                {group.items.map((item) => (
                  <NavEntry key={item.to} item={item} collapsed={collapsed} onNavigate={onNavigate} />
                ))}
              </ul>
            </section>
          ))}
      </div>
      {footer.length > 0 && (
        <ul className="space-y-1 border-t border-white/10 px-3 py-3">
          {footer.map((item) => (
            <NavEntry key={item.to} item={item} collapsed={collapsed} onNavigate={onNavigate} />
          ))}
        </ul>
      )}
    </div>
  )
}
