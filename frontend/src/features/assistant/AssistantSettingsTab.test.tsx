import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as companyApi from '@/features/company/companyApi'
import type { CompanyProfile } from '@/features/company/types'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as assistantApi from './assistantApi'
import { AssistantSettingsTab } from './AssistantSettingsTab'

const company: CompanyProfile = {
  id: 1,
  name: 'NexoSmart',
  tax_id: null,
  logo_url: null,
  currency: 'ARS',
  timezone: 'America/Argentina/Buenos_Aires',
  settings: {
    manual_entry_days_back: 7,
    forgotten_timer_max_hours: 10,
    forgotten_timer_after_shift_hours: 1,
    hour_alert_thresholds: [80, 100],
    assistant_enabled: true,
    assistant_model: 'claude-opus-5-5',
    assistant_monthly_budget_usd: 200,
    assistant_turn_budget_usd: 2,
    assistant_weekly_summary_enabled: false,
    assistant_summary_emails: [],
    assistant_summary_discord: false,
  },
}

describe('AssistantSettingsTab', () => {
  it('turns the Monday summary on and saves its recipients as a list (RF-14.06, CA-14.09)', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions: ['company.manage'] }))
    vi.spyOn(companyApi, 'fetchCompany').mockResolvedValue(company)
    vi.spyOn(assistantApi, 'fetchAssistantUsage').mockResolvedValue({
      month_spent_usd: 12.4,
      monthly_budget_usd: 200,
      models: [{ value: 'claude-opus-5-5', label: 'Opus (el más capaz)' }],
    })
    const update = vi.spyOn(companyApi, 'updateCompany').mockResolvedValue(company)
    renderRoutes([{ path: '/configuracion/asistente', element: <AssistantSettingsTab /> }], '/configuracion/asistente')

    expect(await screen.findByText(/Usado este mes/)).toHaveTextContent('Usado este mes: US$ 12,40 de US$ 200,00.')
    await userEvent.click(screen.getByRole('switch', { name: /Mandar el resumen los lunes a las 9/ }))
    await userEvent.type(screen.getByLabelText('Mails que lo reciben'), 'socios@nexosmart.com, conta@nexosmart.com ;  ')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({
        settings: expect.objectContaining({
          assistant_weekly_summary_enabled: true,
          assistant_summary_emails: ['socios@nexosmart.com', 'conta@nexosmart.com'],
        }),
      }),
    )
  })
})
