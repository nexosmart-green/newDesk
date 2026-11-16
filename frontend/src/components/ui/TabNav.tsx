import { NavLink } from 'react-router'

interface TabLink {
  path: string
  label: string
}

interface TabNavProps {
  label: string
  tabs: readonly TabLink[]
}

/** Route-backed tabs: each tab is a link, so it can be bookmarked and opened in a new tab. */
export function TabNav({ label, tabs }: TabNavProps) {
  return (
    <nav aria-label={label} className="flex gap-1 overflow-x-auto border-b border-border">
      {tabs.map((tab) => (
        <NavLink
          key={tab.path}
          to={tab.path}
          end
          className={({ isActive }) =>
            `-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm ${isActive ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-text'}`
          }
        >
          {tab.label}
        </NavLink>
      ))}
    </nav>
  )
}
