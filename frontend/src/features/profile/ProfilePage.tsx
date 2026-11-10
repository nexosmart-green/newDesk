import { useSearchParams } from 'react-router'
import { PageState } from '@/components/ui/PageState'
import { Tabs, type TabItem } from '@/components/ui/Tabs'
import { AiConnectionsTab } from '@/features/aiConnections/AiConnectionsTab'
import { NotificationPreferences } from '@/features/notifications/NotificationPreferences'
import { AvatarUpload } from './AvatarUpload'
import { MyTeamList } from './MyTeamList'
import { OwnProfileForm } from './OwnProfileForm'
import { SkillsEditor } from './SkillsEditor'
import { useMyProfile } from './useMyProfile'

type ProfileTab = 'datos' | 'habilidades' | 'equipo' | 'notificaciones' | 'ia'

const tabs: readonly TabItem<ProfileTab>[] = [
  { id: 'datos', label: 'Mis datos' },
  { id: 'habilidades', label: 'Habilidades' },
  { id: 'equipo', label: 'Mi equipo' },
  { id: 'notificaciones', label: 'Notificaciones' },
  { id: 'ia', label: 'Conexiones de IA' },
]

/** "Mi perfil": what each person edits about themself (RF-01.05/06/07/09). */
const isProfileTab = (value: string | null): value is ProfileTab => tabs.some((tab) => tab.id === value)

export function ProfilePage() {
  const profile = useMyProfile()
  // Each tab has its own URL (`/perfil?tab=ia`), so menus and notifications can link straight to it.
  const [searchParams, setSearchParams] = useSearchParams()
  const requested = searchParams.get('tab')
  const tab: ProfileTab = isProfileTab(requested) ? requested : 'datos'
  const setTab = (next: ProfileTab) => setSearchParams(next === 'datos' ? {} : { tab: next }, { replace: true })

  if (!profile.data) return <PageState isPending={profile.isPending} error={profile.error} shape="detail" onRetry={() => void profile.refetch()} />
  const person = profile.data

  return (
    <section className="max-w-3xl space-y-4">
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
        {tab === 'notificaciones' && <NotificationPreferences />}
        {tab === 'ia' && <AiConnectionsTab />}
      </Tabs>
    </section>
  )
}
