import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { PageState } from '@/components/ui/PageState'
import { Tabs, type TabItem } from '@/components/ui/Tabs'
import { useCan } from '@/features/auth/useAuth'
import { ApiError } from '@/lib/api'
import { EmployeeProfileForm } from './EmployeeProfileForm'
import { PersonHeader } from './PersonHeader'
import { PersonProfileView } from './PersonProfileView'
import { PersonRates } from './PersonRates'
import { usePerson } from './usePeople'

type PersonTab = 'perfil' | 'tarifas'

export function PersonPage() {
  const personId = Number(useParams().personId)
  const person = usePerson(personId)
  const canManage = useCan('users.manage')
  const canViewRates = useCan('rates.view')
  const [tab, setTab] = useState<PersonTab>('perfil')

  if (person.error instanceof ApiError && person.error.status === 404) {
    return (
      <section className="space-y-2">
        <h1 className="text-lg font-semibold">Persona no encontrada</h1>
        <Link to="/personas" className="text-sm text-accent">
          Volver a Personas
        </Link>
      </section>
    )
  }
  if (!person.data) return <PageState isPending={person.isPending} error={person.error} />

  // CA-01.09 / RNF-06: rates are admin-only; the backend enforces it, the tab is a hint.
  const tabs: TabItem<PersonTab>[] = [{ id: 'perfil', label: 'Perfil' }]
  if (canViewRates) tabs.push({ id: 'tarifas', label: 'Tarifas' })
  const currentTab = canViewRates ? tab : 'perfil'

  return (
    <section className="max-w-3xl space-y-4">
      <Link to="/personas" className="text-sm text-accent">
        ← Personas
      </Link>
      <PersonHeader person={person.data} canManage={canManage} />
      <Tabs label="Secciones de la persona" tabs={tabs} selected={currentTab} onSelect={setTab}>
        {currentTab === 'perfil' &&
          (canManage ? <EmployeeProfileForm person={person.data} /> : <PersonProfileView person={person.data} />)}
        {currentTab === 'tarifas' && <PersonRates personId={person.data.id} canAdd={canManage} />}
      </Tabs>
    </section>
  )
}
