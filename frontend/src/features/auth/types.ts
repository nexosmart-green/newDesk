export interface Company {
  id: number
  name: string
  currency: string
  timezone: string
}

export interface CurrentUser {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email: string
  locale: string
  timezone: string
  role: { id: number; name: string }
  permissions: string[]
  company: Company
  /** Set for a client's user (monitoring portal, RF-13.07): they only use /portal. Null for the team. */
  client: { id: number; name: string } | null
}
