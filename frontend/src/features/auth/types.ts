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
}
