import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { useCatalog } from '@/features/catalogs/useCatalogs'
import type { DirectoryFilters } from './types'

interface PeopleFiltersProps {
  filters: DirectoryFilters
  canSeeInactive: boolean
  onChange: (changes: Partial<DirectoryFilters>) => void
}

const levelOptions = [0, 25, 50, 75, 90]
const toId = (value: string) => (value ? Number(value) : null)

/** "¿Quién sabe React?": name, area, and a skill with a minimum level (RF-01.06). */
export function PeopleFilters({ filters, canSeeInactive, onChange }: PeopleFiltersProps) {
  const areas = useCatalog('areas')
  const skills = useCatalog('skills')

  return (
    <div className="space-y-3">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <TextField
          label="Buscar"
          type="search"
          placeholder="Nombre o email"
          value={filters.search}
          onChange={(event) => onChange({ search: event.target.value })}
        />
        <SelectField label="Área" value={filters.area_id ?? ''} onChange={(event) => onChange({ area_id: toId(event.target.value) })}>
          <option value="">Todas</option>
          {areas.data?.map((area) => (
            <option key={area.id} value={area.id}>
              {area.name}
            </option>
          ))}
        </SelectField>
        <SelectField
          label="Habilidad"
          value={filters.skill_id ?? ''}
          onChange={(event) => onChange({ skill_id: toId(event.target.value) })}
        >
          <option value="">Cualquiera</option>
          {skills.data?.map((skill) => (
            <option key={skill.id} value={skill.id}>
              {skill.name}
            </option>
          ))}
        </SelectField>
        <SelectField
          label="Nivel mínimo"
          value={filters.min_level ?? ''}
          disabled={!filters.skill_id}
          onChange={(event) => onChange({ min_level: toId(event.target.value) })}
        >
          <option value="">Cualquiera</option>
          {levelOptions.map((level) => (
            <option key={level} value={level}>
              {level} o más
            </option>
          ))}
        </SelectField>
      </div>
      {canSeeInactive && (
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={filters.include_inactive}
            onChange={(event) => onChange({ include_inactive: event.target.checked })}
          />
          Incluir personas dadas de baja
        </label>
      )}
    </div>
  )
}
