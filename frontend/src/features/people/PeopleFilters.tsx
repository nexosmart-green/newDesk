import { FilterBar } from '@/components/ui/filters/FilterBar'
import { FilterCheck } from '@/components/ui/filters/FilterCheck'
import { FilterSelect } from '@/components/ui/filters/FilterSelect'
import { SearchInput } from '@/components/ui/filters/SearchInput'
import { useCatalog } from '@/features/catalogs/useCatalogs'
import type { DirectoryFilters } from './types'

interface PeopleFiltersProps {
  filters: DirectoryFilters
  canSeeInactive: boolean
  onChange: (changes: Partial<DirectoryFilters>) => void
}

const levelOptions = [
  { value: '', label: 'Cualquiera' },
  ...[0, 25, 50, 75, 90].map((level) => ({ value: String(level), label: `${level} o más` })),
]
const toId = (value: string) => (value ? Number(value) : null)
const optionsOf = (none: string, items: { id: number; name: string }[] | undefined) => [
  { value: '', label: none },
  ...(items ?? []).map((item) => ({ value: String(item.id), label: item.name })),
]

/** "¿Quién sabe React?": name, area, and a skill with a minimum level (RF-01.06). */
export function PeopleFilters({ filters, canSeeInactive, onChange }: PeopleFiltersProps) {
  const areas = useCatalog('areas')
  const skills = useCatalog('skills')
  const moreActive = (filters.min_level !== null ? 1 : 0) + (filters.include_inactive ? 1 : 0)
  const active = moreActive + (filters.area_id !== null ? 1 : 0) + (filters.skill_id !== null ? 1 : 0)

  return (
    <FilterBar
      active={active}
      moreActive={moreActive}
      search={<SearchInput label="Buscar personas por nombre o email" placeholder="Nombre o email" value={filters.search} onChange={(search) => onChange({ search })} />}
      more={
        <>
          <FilterSelect
            label="Nivel mínimo"
            value={String(filters.min_level ?? '')}
            options={levelOptions}
            disabled={!filters.skill_id}
            onChange={(value) => onChange({ min_level: toId(value) })}
          />
          {canSeeInactive && (
            <FilterCheck
              label="Incluir personas dadas de baja"
              checked={filters.include_inactive}
              onChange={(include_inactive) => onChange({ include_inactive })}
            />
          )}
        </>
      }
    >
      <FilterSelect label="Área" value={String(filters.area_id ?? '')} options={optionsOf('Todas', areas.data)} onChange={(value) => onChange({ area_id: toId(value) })} />
      <FilterSelect
        label="Habilidad"
        value={String(filters.skill_id ?? '')}
        options={optionsOf('Cualquiera', skills.data)}
        onChange={(value) => onChange({ skill_id: toId(value) })}
      />
    </FilterBar>
  )
}
