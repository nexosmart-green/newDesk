import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchCompany, updateCompany, uploadCompanyLogo } from './companyApi'
import { companyKey, currentUserKey } from '@/lib/queryKeys'
import type { CompanyProfile, UpdateCompanyInput } from './types'

export function useCompany() {
  return useQuery({ queryKey: companyKey, queryFn: fetchCompany })
}

function useStoreCompany() {
  const queryClient = useQueryClient()
  return (company: CompanyProfile) => {
    queryClient.setQueryData(companyKey, company)
    // /me embeds the company name/currency/timezone shown in the header.
    void queryClient.invalidateQueries({ queryKey: currentUserKey })
  }
}

export function useUpdateCompany() {
  const storeCompany = useStoreCompany()
  return useMutation({
    mutationFn: (input: UpdateCompanyInput) => updateCompany(input),
    onSuccess: storeCompany,
    meta: { successMessage: 'Configuración guardada.' },
  })
}

export function useUploadCompanyLogo() {
  const storeCompany = useStoreCompany()
  return useMutation({
    mutationFn: (logo: File) => uploadCompanyLogo(logo),
    onSuccess: storeCompany,
    meta: { successMessage: 'Logo actualizado.' },
  })
}
