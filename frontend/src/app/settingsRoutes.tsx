import { CatalogsTab } from '@/features/catalogs/CatalogsTab'
import { CompanyTab } from '@/features/company/CompanyTab'
import { RolesTab } from '@/features/roles/RolesTab'
import { SettingsIndexRedirect, SettingsPage, SettingsTabGuard } from '@/features/settings/SettingsPage'
import { UsersTab } from '@/features/users/UsersTab'

/** "Configuración" as one lazy chunk: only admins open it. */
export const SettingsLayout = SettingsPage
export const SettingsIndex = SettingsIndexRedirect

export function CompanySettings() {
  return (
    <SettingsTabGuard path="empresa">
      <CompanyTab />
    </SettingsTabGuard>
  )
}

export function RolesSettings() {
  return (
    <SettingsTabGuard path="roles">
      <RolesTab />
    </SettingsTabGuard>
  )
}

export function UsersSettings() {
  return (
    <SettingsTabGuard path="usuarios">
      <UsersTab />
    </SettingsTabGuard>
  )
}

export function CatalogsSettings() {
  return (
    <SettingsTabGuard path="catalogos">
      <CatalogsTab />
    </SettingsTabGuard>
  )
}
