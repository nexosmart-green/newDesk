/** URL slug of each administrable catalog (RF-01.08 + skills of RF-01.06). */
export type CatalogType = 'job-titles' | 'areas' | 'employment-types' | 'skills'

export interface CatalogItem {
  id: number
  name: string
}

export const catalogLabels: Record<CatalogType, { title: string; singular: string }> = {
  'job-titles': { title: 'Puestos', singular: 'puesto' },
  areas: { title: 'Áreas', singular: 'área' },
  'employment-types': { title: 'Tipos de empleo', singular: 'tipo de empleo' },
  skills: { title: 'Habilidades', singular: 'habilidad' },
}

export const catalogTypes = Object.keys(catalogLabels) as CatalogType[]
