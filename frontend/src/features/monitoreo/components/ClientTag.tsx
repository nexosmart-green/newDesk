import { Link } from 'react-router'
import type { FolderLink } from '../api/types'

// De qué cliente es una carpeta del inventario (D-044); lleva a su proyecto en Clientes.
export function ClientTag({ link }: { link: FolderLink }) {
  return (
    <Link
      to={`/monitoreo/clientes/${link.client_id}/proyectos/${link.project_id}`}
      className="tag transition-colors hover:text-text"
      title={`Proyecto "${link.project_name}" de ${link.client_name}`}
    >
      Cliente: {link.client_name}
    </Link>
  )
}
