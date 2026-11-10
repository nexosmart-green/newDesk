import { Switch } from '@/components/ui/Switch'
import type { PermissionOption } from './types'

interface ModuleSwitchesProps {
  modules: PermissionOption[]
  enabled: string[]
  disabled: boolean
  onToggle: (value: string, checked: boolean) => void
}

/** "Módulo Horas y cronómetro" → "Horas y cronómetro": the section title already says "Módulos". */
const shortLabel = (label: string) => label.replace(/^Módulo\s+/i, '')

/** Whole areas on/off for the role; without one, its menu entry and endpoints are gone for that role. */
export function ModuleSwitches({ modules, enabled, disabled, onToggle }: ModuleSwitchesProps) {
  return (
    <section aria-labelledby="role-modules" className="space-y-2">
      <div>
        <h3 id="role-modules" className="text-sm font-semibold">
          Módulos
        </h3>
        <p className="text-xs text-muted">Qué áreas del sistema ve este rol. Apagado, el módulo desaparece del menú.</p>
      </div>
      <div className="grid gap-2 sm:grid-cols-2">
        {modules.map((module) => (
          <Switch
            key={module.value}
            label={shortLabel(module.label)}
            checked={enabled.includes(module.value)}
            disabled={disabled}
            onChange={(checked) => onToggle(module.value, checked)}
          />
        ))}
      </div>
    </section>
  )
}
