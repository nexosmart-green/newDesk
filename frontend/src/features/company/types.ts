export interface CompanySettings {
  manual_entry_days_back: number
  forgotten_timer_max_hours: number
  forgotten_timer_after_shift_hours: number
  /** [warning %, critical %] of a project's sold hours (RF-11). */
  hour_alert_thresholds: [number, number]
  /** RF-13.10: the AI assistant. With a subscription the cost is an estimate (a usage guard). */
  assistant_enabled: boolean
  assistant_model: string
  assistant_monthly_budget_usd: number
  assistant_turn_budget_usd: number
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
