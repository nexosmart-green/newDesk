import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { peopleKey, peopleListsKey, personKey, personRatesKey, projectsKey } from '@/lib/queryKeys'
import { allExcept, refreshQueries } from '@/lib/refreshQueries'
import { addRate, deactivatePerson, fetchPeople, fetchPerson, fetchRates, reactivatePerson, updatePerson } from './peopleApi'
import type { AddRateInput, DirectoryFilters, PersonProfile, UpdateEmployeeInput } from './types'

/** `enabled: false` for roles without the people module (selectors that would 403). */
export function usePeople(filters: DirectoryFilters, enabled = true) {
  return useQuery({
    enabled,
    queryKey: [...peopleListsKey, filters],
    queryFn: () => fetchPeople(filters),
    placeholderData: keepPreviousData,
  })
}

export function usePerson(id: number) {
  return useQuery({ queryKey: personKey(id), queryFn: () => fetchPerson(id) })
}

/**
 * A full profile answer is written to the person's page at once (`now` waits for it otherwise); the
 * directory and the projects' member lists (people cards) follow in the background.
 */
function useStorePerson(id: number) {
  const queryClient = useQueryClient()
  return (profile?: PersonProfile) => {
    if (!profile) return refreshQueries(queryClient, { now: [personKey(id)], later: [peopleKey, projectsKey] })
    queryClient.setQueryData(personKey(id), profile)
    return refreshQueries(queryClient, { later: [allExcept(peopleKey, personKey(id)), projectsKey] })
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
  return useQuery({ queryKey: personRatesKey(personId), queryFn: () => fetchRates(personId) })
}

export function useAddRate(personId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: AddRateInput) => addRate(personId, input),
    onSuccess: () => refreshQueries(queryClient, { now: [personRatesKey(personId)] }),
    meta: { successMessage: 'Tarifa registrada.' },
  })
}
