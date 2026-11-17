import { useId, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { Switch } from '@/components/ui/Switch'
import { inputClassName, TextField } from '@/components/ui/TextField'
import type { CompanyProfile, CompanySettings } from '@/features/company/types'
import { useCompany, useUpdateCompany } from '@/features/company/useCompany'
import { fieldErrorOf } from '@/lib/api'
import { parseEmailList } from '@/lib/emailList'
import type { AssistantUsage } from './types'
import { useAssistantUsage } from './useAssistant'
import { WeeklySummaryFields } from './WeeklySummaryFields'

type AssistantSettings = Pick<
  CompanySettings,
  | 'assistant_enabled'
  | 'assistant_model'
  | 'assistant_monthly_budget_usd'
  | 'assistant_turn_budget_usd'
  | 'assistant_weekly_summary_enabled'
  | 'assistant_summary_discord'
>

const usd = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 })

/** RF-14.10 (`company.manage`): turn the assistant on or off, its model and its usage caps. */
export function AssistantSettingsTab() {
  const company = useCompany()
  const usage = useAssistantUsage(true)

  if (!company.data || !usage.data) {
    const failed = company.error ?? usage.error
    return <PageState isPending={!failed} error={failed} shape="detail" onRetry={() => void Promise.all([company.refetch(), usage.refetch()])} />
  }

  return <AssistantSettingsForm company={company.data} usage={usage.data} />
}

function AssistantSettingsForm({ company, usage }: { company: CompanyProfile; usage: AssistantUsage }) {
  const { assistant_enabled, assistant_model, assistant_monthly_budget_usd, assistant_turn_budget_usd, assistant_weekly_summary_enabled, assistant_summary_discord } =
    company.settings
  const [draft, setDraft] = useState<AssistantSettings>({
    assistant_enabled,
    assistant_model,
    assistant_monthly_budget_usd,
    assistant_turn_budget_usd,
    assistant_weekly_summary_enabled,
    assistant_summary_discord,
  })
  const [emails, setEmails] = useState(company.settings.assistant_summary_emails.join(', '))
  const mutation = useUpdateCompany()
  const modelId = useId()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const { name, tax_id, currency, timezone, settings } = company
    mutation.mutate({ name, tax_id, currency, timezone, settings: { ...settings, ...draft, assistant_summary_emails: parseEmailList(emails) } })
  }

  const error = (field: keyof AssistantSettings) => fieldErrorOf(mutation.error, `settings.${field}`)

  return (
    <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
      <Switch
        label="Asistente de IA"
        description="Los roles con el permiso «Usar el asistente de IA» le piden reportes, resúmenes y cambios en lenguaje natural."
        checked={draft.assistant_enabled}
        onChange={(assistant_enabled) => setDraft({ ...draft, assistant_enabled })}
      />
      <p className="text-sm">
        Usado este mes: <span className="tabular font-medium">{usd.format(usage.month_spent_usd)}</span> de{' '}
        <span className="tabular">{usd.format(usage.monthly_budget_usd)}</span>. Es la estimación que informa Claude Code.
      </p>
      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 font-medium">Modelo y topes</legend>
        <div className="space-y-1 sm:col-span-2">
          <label htmlFor={modelId} className="block text-sm text-muted">
            Modelo
          </label>
          <select
            id={modelId}
            value={draft.assistant_model}
            onChange={(event) => setDraft({ ...draft, assistant_model: event.target.value })}
            aria-invalid={error('assistant_model') ? true : undefined}
            className={inputClassName}
          >
            {usage.models.map((model) => (
              <option key={model.value} value={model.value}>
                {model.label}
              </option>
            ))}
          </select>
          {error('assistant_model') && <p className="text-sm text-danger">{error('assistant_model')}</p>}
        </div>
        <TextField
          label="Tope mensual (USD)"
          type="number"
          min={0}
          max={10000}
          step={1}
          required
          hint="Al llegar, el asistente deja de responder hasta el mes siguiente."
          value={draft.assistant_monthly_budget_usd}
          error={error('assistant_monthly_budget_usd')}
          onChange={(event) => setDraft({ ...draft, assistant_monthly_budget_usd: Number(event.target.value) })}
        />
        <TextField
          label="Tope por pedido (USD)"
          type="number"
          min={0.1}
          max={50}
          step={0.1}
          required
          hint="Un pedido que lo supera se corta y avisa."
          value={draft.assistant_turn_budget_usd}
          error={error('assistant_turn_budget_usd')}
          onChange={(event) => setDraft({ ...draft, assistant_turn_budget_usd: Number(event.target.value) })}
        />
      </fieldset>
      <WeeklySummaryFields
        enabled={draft.assistant_weekly_summary_enabled}
        discord={draft.assistant_summary_discord}
        onDiscordChange={(assistant_summary_discord) => setDraft({ ...draft, assistant_summary_discord })}
        emails={emails}
        emailsError={fieldErrorOf(mutation.error, 'settings.assistant_summary_emails')}
        onEnabledChange={(assistant_weekly_summary_enabled) => setDraft({ ...draft, assistant_weekly_summary_enabled })}
        onEmailsChange={setEmails}
      />
      <Button type="submit" loading={mutation.isPending}>
        Guardar cambios
      </Button>
    </form>
  )
}
