import type { ReactNode } from 'react'
import type { ServerDetail } from '../../api/types'
import { SectionFrame } from '../SectionFrame'
import { CronTab } from './CronTab'
import { DatabasesTab } from './DatabasesTab'
import { DependenciesTab, PackagesTab } from './InventoryTabs'
import { LargeFilesPanel } from './LargeFilesPanel'
import { PortsTab } from './PortsTab'
import { Pm2Panel } from './ProcessesTab'
import { ProjectsTab } from './ProjectsTab'
import { SecurityTab } from './SecurityTab'
import { ServicesTab } from './ServicesTab'
import { SitesTab } from './SitesTab'
import { SummaryTab } from './SummaryTab'
import { SupervisorPanel } from './SupervisorPanel'

export const tabDefs = [
  { id: 'resumen', label: 'Resumen' },
  { id: 'proyectos', label: 'Proyectos' },
  { id: 'sitios', label: 'Sitios' },
  { id: 'procesos', label: 'Procesos' },
  { id: 'servicios', label: 'Servicios' },
  { id: 'cron', label: 'Crons y timers' },
  { id: 'dependencias', label: 'Dependencias' },
  { id: 'paquetes', label: 'Paquetes' },
  { id: 'bases', label: 'Bases de datos' },
  { id: 'puertos', label: 'Puertos' },
  { id: 'seguridad', label: 'Seguridad' },
] as const

export type TabId = (typeof tabDefs)[number]['id']

export function renderTab(tab: TabId, detail: ServerDetail): ReactNode {
  const id = detail.server.id
  const s = detail.sections
  switch (tab) {
    case 'resumen':
      return <SummaryTab detail={detail} />
    case 'proyectos':
      return (
        <div className="space-y-8">
          <SectionFrame serverId={id} section="projects" snapshot={s.projects}>{(data) => <ProjectsTab data={data} largeFiles={s.large_files?.data} serverId={id} />}</SectionFrame>
          <SectionFrame serverId={id} section="large_files" snapshot={s.large_files}>{(data) => <LargeFilesPanel data={data} projects={s.projects?.data?.items} />}</SectionFrame>
        </div>
      )
    case 'sitios':
      return <SectionFrame serverId={id} section="sites" snapshot={s.sites}>{(data) => <SitesTab serverId={id} data={data} />}</SectionFrame>
    case 'procesos':
      return (
        <div className="space-y-8">
          <SectionFrame serverId={id} section="pm2" snapshot={s.pm2}>{(data) => <Pm2Panel data={data} />}</SectionFrame>
          <SectionFrame serverId={id} section="supervisor" snapshot={s.supervisor}>{(data) => <SupervisorPanel data={data} projects={s.projects?.data?.items} />}</SectionFrame>
        </div>
      )
    case 'servicios':
      return <SectionFrame serverId={id} section="services" snapshot={s.services}>{(data) => <ServicesTab data={data} />}</SectionFrame>
    case 'cron':
      return <SectionFrame serverId={id} section="cron" snapshot={s.cron}>{(data) => <CronTab data={data} />}</SectionFrame>
    case 'dependencias':
      return <SectionFrame serverId={id} section="dependencies" snapshot={s.dependencies}>{(data) => <DependenciesTab data={data} />}</SectionFrame>
    case 'paquetes':
      return <SectionFrame serverId={id} section="packages" snapshot={s.packages}>{(data) => <PackagesTab data={data} />}</SectionFrame>
    case 'bases':
      return <SectionFrame serverId={id} section="databases" snapshot={s.databases}>{(data) => <DatabasesTab data={data} />}</SectionFrame>
    case 'puertos':
      return <SectionFrame serverId={id} section="ports" snapshot={s.ports}>{(data) => <PortsTab data={data} />}</SectionFrame>
    case 'seguridad':
      return <SectionFrame serverId={id} section="security" snapshot={s.security}>{(data) => <SecurityTab data={data} />}</SectionFrame>
  }
}
