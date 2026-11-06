import { Icon } from '../../components/Icon'
import { useId, useMemo, useState } from 'react'
import type { Proxy } from '../../api/contract'
import { Badge } from '../../components/Status'

// Cuántas locations se muestran sin expandir. El vhost de clientes tiene ~150.
const COLLAPSED = 5

// Lista de locations de un vhost: primero las que no tienen proceso escuchando,
// colapsada si son muchas y con filtro por location o upstream.
export function SiteProxies({ proxies }: { proxies: readonly Proxy[] }) {
  const [expanded, setExpanded] = useState(false)
  const [filter, setFilter] = useState('')
  const filterId = useId()

  const sorted = useMemo(
    () => [...proxies].sort((a, b) => Number(a.listening !== false) - Number(b.listening !== false) || a.location.localeCompare(b.location)),
    [proxies],
  )
  const query = filter.trim().toLowerCase()
  const visible = expanded
    ? sorted.filter((proxy) => !query || proxy.location.toLowerCase().includes(query) || proxy.upstream.toLowerCase().includes(query))
    : sorted.slice(0, COLLAPSED)
  const hidden = sorted.length - COLLAPSED

  return (
    <div className="space-y-1">
      {expanded && sorted.length > COLLAPSED && (
        <>
          <label htmlFor={filterId} className="sr-only">Filtrar locations</label>
          <input
            id={filterId}
            className="input h-8 text-xs"
            placeholder="Filtrar location o upstream…"
            value={filter}
            onChange={(event) => setFilter(event.target.value)}
          />
        </>
      )}
      <ul className={expanded ? 'fade-in max-h-72 space-y-0.5 overflow-y-auto pr-1' : 'space-y-0.5'} aria-label="Locations con proxy">
        {visible.map((proxy) => (
          <li key={`${proxy.location}→${proxy.upstream}`} className={`flex flex-wrap items-center gap-x-2 ${proxy.listening === false ? 'text-crit' : 'text-muted'}`}>
            <span className="break-all">{proxy.location} → {proxy.upstream}</span>
            {proxy.listening === false && <Badge tone="crit">sin proceso (502)</Badge>}
          </li>
        ))}
        {expanded && visible.length === 0 && <li className="text-faint">Ninguna location coincide.</li>}
      </ul>
      {hidden > 0 && (
        <button type="button" className="link inline-flex items-center gap-1 font-sans text-xs" aria-expanded={expanded} onClick={() => setExpanded((value) => !value)}>
          {expanded ? 'Mostrar menos' : `Ver las ${sorted.length} locations (+${hidden})`}
          <Icon name="chevron" size={10} className={`transition-transform duration-[var(--dur-2)] ${expanded ? '-rotate-90' : 'rotate-90'}`} />
        </button>
      )}
    </div>
  )
}
