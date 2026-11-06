import { CatalogList } from './CatalogList'
import { catalogTypes } from './types'

/** RF-01.08: puestos, áreas, tipos de empleo y habilidades, editables por `users.manage`. */
export function CatalogsTab() {
  return (
    <div className="grid gap-6 md:grid-cols-2">
      {catalogTypes.map((type) => (
        <CatalogList key={type} type={type} />
      ))}
    </div>
  )
}
