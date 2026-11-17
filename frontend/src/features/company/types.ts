export interface CompanySettings {
  manual_entry_days_back: number
  forgotten_timer_max_hours: number
  forgotten_timer_after_shift_hours: number
  /** [warning %, critical %] of a project's sold hours (RF-11). */
  hour_alert_thresholds: [number, number]
  /** RF-14.10: the AI assistant. With a subscription the cost is an estimate (a usage guard). */
  assistant_enabled: boolean
  assistant_model: string
  assistant_monthly_budget_usd: number
  assistant_turn_budget_usd: number
  /** RF-14.06: the Monday summary (off until an Admin turns it on) and who gets it by mail (empty = the Admins). */
  assistant_weekly_summary_enabled: boolean
  assistant_summary_emails: string[]
  /** monitoreo's Discord summary channel is server-wide: the company opts in. */
  assistant_summary_discord: boolean
}

export interface CompanyProfile {
  id: number
  name: string
  tax_id: string | null
  logo_url: string | null
  currency: string
  timezone: string
  settings: CompanySettings
}

export interface UpdateCompanyInput {
  name: string
  tax_id: string | null
  currency: string
  timezone: string
  settings: CompanySettings
}
