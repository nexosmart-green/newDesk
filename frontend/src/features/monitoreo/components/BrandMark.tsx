import { Icon } from './Icon'

// La marca de monitoreo: la del menú del equipo y la del login del cliente.
export function BrandMark({ className = '', iconSize = 18 }: { className?: string; iconSize?: number }) {
  return (
    <span className={`grid shrink-0 place-items-center bg-accent text-accent-ink ${className}`} aria-hidden="true">
      <Icon name="bars" size={iconSize} className="stroke-[2.25]" />
    </span>
  )
}
