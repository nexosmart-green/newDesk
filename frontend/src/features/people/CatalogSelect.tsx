import { SelectField } from '@/components/ui/SelectField'
import type { CatalogType } from '@/features/catalogs/types'
import { useCatalog } from '@/features/catalogs/useCatalogs'

interface CatalogSelectProps {
  label: string
  catalog: CatalogType
  value: string
  error?: string
  onChange: (value: string) => void
}

export function CatalogSelect({ label, catalog, value, error, onChange }: CatalogSelectProps) {
  const items = useCatalog(catalog)

  return (
    <SelectField label={label} value={value} error={error} onChange={(event) => onChange(event.target.value)}>
      <option value="">{items.isPending ? 'Cargando…' : 'Sin asignar'}</option>
      {items.data?.map((item) => (
        <option key={item.id} value={item.id}>
          {item.name}
        </option>
      ))}
    </SelectField>
  )
}
