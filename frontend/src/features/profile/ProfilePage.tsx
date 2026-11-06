import { useState } from 'react'
import { PageState } from '@/components/ui/PageState'
import { Tabs, type TabItem } from '@/components/ui/Tabs'
import { AvatarUpload } from './AvatarUpload'
import { MyTeamList } from './MyTeamList'
import { OwnProfileForm } from './OwnProfileForm'
import { SkillsEditor } from './SkillsEditor'
import { useMyProfile } from './useMyProfile'

type ProfileTab = 'datos' | 'habilidades' | 'equipo'

const tabs: readonly TabItem<ProfileTab>[] = [
  { id: 'datos', label: 'Mis datos' },
  { id: 'habilidades', label: 'Habilidades' },
  { id: 'equipo', label: 'Mi equipo' },
]

/** "Mi perfil": what each person edits about themself (RF-01.05/06/07/09). */
export function ProfilePage() {
  const profile = useMyProfile()
  const [tab, setTab] = useState<ProfileTab>('datos')

  if (!profile.data) return <PageState isPending={profile.isPending} error={profile.error} />
  const person = profile.data

  return (
    <section className="mx-auto max-w-3xl space-y-4">
      <header className="flex flex-wrap items-center gap-4">
        <AvatarUpload profile={person} />
        <div>
          <h1 className="text-lg font-semibold">{person.full_name}</h1>
          <p className="text-sm text-muted">
            {[person.job_title?.name, person.area?.name].filter(Boolean).join(' · ') || person.email}
          </p>
          {person.reports_to && <p className="text-sm text-muted">Reporta a {person.reports_to.full_name}</p>}
        </div>
      </header>
      <Tabs label="Secciones del perfil" tabs={tabs} selected={tab} onSelect={setTab}>
        {tab === 'datos' && <OwnProfileForm profile={person} />}
        {tab === 'habilidades' && <SkillsEditor skills={person.skills ?? []} />}
        {tab === 'equipo' && <MyTeamList />}
      </Tabs>
    </section>
  )
}
