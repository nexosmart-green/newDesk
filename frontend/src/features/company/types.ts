export interface CompanySettings {
  manual_entry_days_back: number
  forgotten_timer_max_hours: number
  forgotten_timer_after_shift_hours: number
  /** [warning %, critical %] of a project's sold hours (RF-11). */
  hour_alert_thresholds: [number, number]
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
