import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { SelectField } from '@/components/ui/SelectField'
import { useCatalog } from '@/features/catalogs/useCatalogs'
import type { SkillLevel } from '@/features/people/types'
import { useSaveMySkills } from './useMyProfile'

const DEFAULT_LEVEL = 50

/** RF-01.06: skills from the catalog, each with a 0–100 level. Saving replaces the whole set. */
export function SkillsEditor({ skills: initialSkills }: { skills: SkillLevel[] }) {
  const [skills, setSkills] = useState(initialSkills)
  const [toAdd, setToAdd] = useState('')
  const catalog = useCatalog('skills')
  const mutation = useSaveMySkills()

  const available = (catalog.data ?? []).filter((item) => !skills.some((skill) => skill.id === item.id))

  function addSkill() {
    const item = available.find((candidate) => candidate.id === Number(toAdd))
    if (!item) return
    setSkills([...skills, { id: item.id, name: item.name, level: DEFAULT_LEVEL }])
    setToAdd('')
  }

  function setLevel(id: number, level: number) {
    setSkills(skills.map((skill) => (skill.id === id ? { ...skill, level } : skill)))
  }

  return (
    <div className="space-y-4">
      {skills.length === 0 ? (
        <p className="text-sm text-muted">Todavía no cargaste habilidades.</p>
      ) : (
        <ul className="space-y-3" aria-label="Mis habilidades">
          {skills.map((skill) => (
            <li key={skill.id} className="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1 sm:grid-cols-[10rem_1fr_3rem_auto]">
              <span className="text-sm font-medium">{skill.name}</span>
              <input
                id={`skill-${skill.id}`}
                type="range"
                min={0}
                max={100}
                step={5}
                value={skill.level}
                aria-label={`Nivel de ${skill.name}`}
                onChange={(event) => setLevel(skill.id, Number(event.target.value))}
                className="col-span-2 w-full accent-accent sm:col-span-1"
              />
              <output htmlFor={`skill-${skill.id}`} className="text-sm text-muted">
                {skill.level}
              </output>
              <Button
                variant="link"
                aria-label={`Quitar ${skill.name}`}
                onClick={() => setSkills(skills.filter((current) => current.id !== skill.id))}
              >
                Quitar
              </Button>
            </li>
          ))}
        </ul>
      )}
      <div className="flex items-end gap-2">
        <SelectField label="Agregar habilidad" className="flex-1" value={toAdd} onChange={(event) => setToAdd(event.target.value)}>
          <option value="">{catalog.isPending ? 'Cargando…' : 'Elegí una habilidad'}</option>
          {available.map((item) => (
            <option key={item.id} value={item.id}>
              {item.name}
            </option>
          ))}
        </SelectField>
        <Button variant="secondary" disabled={!toAdd} onClick={addSkill}>
          Agregar
        </Button>
      </div>
      <FieldErrorsAlert error={mutation.error} />
      <Button loading={mutation.isPending} onClick={() => mutation.mutate(skills.map(({ id, level }) => ({ id, level })))}>
        Guardar habilidades
      </Button>
    </div>
  )
}
