import { Switch } from '@/components/ui/Switch'
import { TextField } from '@/components/ui/TextField'

interface WeeklySummaryFieldsProps {
  enabled: boolean
  discord: boolean
  emails: string
  emailsError?: string
  onEnabledChange: (enabled: boolean) => void
  onDiscordChange: (discord: boolean) => void
  onEmailsChange: (emails: string) => void
}

/** RF-14.06 / CA-14.09: the Monday summary and where it goes (it carries clients and hours per person). */
export function WeeklySummaryFields({ enabled, discord, emails, emailsError, onEnabledChange, onDiscordChange, onEmailsChange }: WeeklySummaryFieldsProps) {
  return (
    <fieldset className="space-y-4">
      <legend className="mb-2 font-medium">Resumen de la semana</legend>
      <Switch
        label="Mandar el resumen los lunes a las 9"
        description="Horas de la semana anterior, bolsas por agotarse, vencidas y ampliaciones. Va por mail y queda en el chat del asistente de quienes ven el reporte de horas."
        checked={enabled}
        onChange={onEnabledChange}
      />
      <TextField
        label="Mails que lo reciben"
        hint="Separados por coma. Vacío: los administradores de la empresa."
        placeholder="socios@empresa.com, contabilidad@empresa.com"
        value={emails}
        error={emailsError}
        disabled={!enabled}
        onChange={(event) => onEmailsChange(event.target.value)}
      />
      <Switch
        label="Publicarlo también en Discord"
        description="En el canal de resumen de monitoreo. Lo ve todo el que esté en ese canal."
        checked={discord}
        disabled={!enabled}
        onChange={onDiscordChange}
      />
    </fieldset>
  )
}
