import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  addRate,
  deactivatePerson,
  fetchPeople,
  fetchPerson,
  fetchRates,
  reactivatePerson,
  updatePerson,
} from './peopleApi'
import { toast } from '@/components/ui/toast/toastStore'
import type { AddRateInput, DirectoryFilters, PersonProfile, UpdateEmployeeInput } from './types'

export const peopleKey = ['people'] as const
const personKey = (id: number) => [...peopleKey, 'detail', id] as const
const ratesKey = (id: number) => [...peopleKey, 'rates', id] as const

export function usePeople(filters: DirectoryFilters) {
  return useQuery({
    queryKey: [...peopleKey, 'list', filters],
    queryFn: () => fetchPeople(filters),
    placeholderData: keepPreviousData,
  })
}

export function usePerson(id: number) {
  return useQuery({ queryKey: personKey(id), queryFn: () => fetchPerson(id) })
}

function useStorePerson(id: number) {
  const queryClient = useQueryClient()
  return async (profile?: PersonProfile) => {
    if (profile) queryClient.setQueryData(personKey(id), profile)
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: peopleKey }),
      // Members lists embed people cards.
      queryClient.invalidateQueries({ queryKey: ['projects'] }),
    ])
  }
}

export function useUpdatePerson(id: number) {
  const storePerson = useStorePerson(id)
  return useMutation({
    mutationFn: (input: UpdateEmployeeInput) => updatePerson(id, input),
    onSuccess: storePerson,
    meta: { successMessage: 'Perfil actualizado.' },
  })
}

export function useSetPersonActive(id: number) {
  const storePerson = useStorePerson(id)
  return useMutation({
    mutationFn: (active: boolean) => (active ? reactivatePerson(id) : deactivatePerson(id)),
    onSuccess: (_result, active) => {
      toast.success(active ? 'Persona reactivada.' : 'Persona dada de baja.')
      return storePerson()
    },
  })
}

export function useRates(personId: number) {
  return useQuery({ queryKey: ratesKey(personId), queryFn: () => fetchRates(personId) })
}

export function useAddRate(personId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: AddRateInput) => addRate(personId, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ratesKey(personId) }),
    meta: { successMessage: 'Tarifa registrada.' },
  })
}
