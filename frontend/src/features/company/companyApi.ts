import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { CompanyProfile, UpdateCompanyInput } from './types'

export async function fetchCompany(): Promise<CompanyProfile> {
  return dataOf(api.get<ApiEnvelope<CompanyProfile>>('/company'))
}

export async function updateCompany(input: UpdateCompanyInput): Promise<CompanyProfile> {
  return dataOf(api.put<ApiEnvelope<CompanyProfile>>('/company', input))
}

export async function uploadCompanyLogo(logo: File): Promise<CompanyProfile> {
  const body = new FormData()
  body.append('logo', logo)
  return dataOf(api.post<ApiEnvelope<CompanyProfile>>('/company/logo', body))
}
