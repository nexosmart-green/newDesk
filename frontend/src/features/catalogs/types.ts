/** URL slug of each administrable catalog (RF-01.08 + skills of RF-01.06). */
export type CatalogType = 'job-titles' | 'areas' | 'employment-types' | 'skills'

export interface CatalogItem {
  id: number
  name: string
}

interface CatalogLabels {
  title: string
  /** Capitalized, for the start of a toast: "Área «Diseño» guardada." */
  item: string
  /** "Nueva área" / "Nuevo puesto", "guardada" / "guardado". */
  feminine: boolean
}

export const catalogLabels: Record<CatalogType, CatalogLabels> = {
  'job-titles': { title: 'Puestos', item: 'Puesto', feminine: false },
  areas: { title: 'Áreas', item: 'Área', feminine: true },
  'employment-types': { title: 'Tipos de empleo', item: 'Tipo de empleo', feminine: false },
  skills: { title: 'Habilidades', item: 'Habilidad', feminine: true },
}

/** "Área «Diseño» guardada." — the toast names the item and agrees with its gender. */
export function catalogItemDone(type: CatalogType, name: string, participle: 'agregad' | 'guardad' | 'borrad'): string {
  const labels = catalogLabels[type]
  return `${labels.item} «${name}» ${participle}${labels.feminine ? 'a' : 'o'}.`
}

export function newCatalogItemLabel(type: CatalogType): string {
  const labels = catalogLabels[type]
  return `${labels.feminine ? 'Nueva' : 'Nuevo'} ${labels.item.toLowerCase()}`
}

export const catalogTypes = Object.keys(catalogLabels) as CatalogType[]
