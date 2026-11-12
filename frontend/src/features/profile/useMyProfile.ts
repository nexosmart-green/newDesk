import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchMyProfile, fetchMyTeam, saveMySkills, updateMyProfile, uploadMyAvatar } from './profileApi'
import type { PersonProfile, UpdateOwnProfileInput } from '@/features/people/types'
import { currentUserKey, myTeamKey, peopleKey, profileKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'

export function useMyProfile() {
  return useQuery({ queryKey: profileKey, queryFn: fetchMyProfile })
}

export function useMyTeam() {
  return useQuery({ queryKey: myTeamKey, queryFn: fetchMyTeam })
}

function useStoreProfile() {
  const queryClient = useQueryClient()
  return (profile: PersonProfile) => {
    queryClient.setQueryData(profileKey, profile)
    // /me carries locale and timezone (dates on screen follow it); the directory follows behind.
    return refreshQueries(queryClient, { now: [currentUserKey], later: [peopleKey] })
  }
}

export function useUpdateMyProfile() {
  const storeProfile = useStoreProfile()
  return useMutation({
    mutationFn: (input: UpdateOwnProfileInput) => updateMyProfile(input),
    onSuccess: storeProfile,
    meta: { successMessage: 'Perfil actualizado.' },
  })
}

export function useUploadMyAvatar() {
  const storeProfile = useStoreProfile()
  return useMutation({
    mutationFn: ({ avatar, fileName }: { avatar: Blob; fileName: string }) => uploadMyAvatar(avatar, fileName),
    onSuccess: storeProfile,
    meta: { successMessage: 'Foto actualizada.' },
  })
}

export function useSaveMySkills() {
  const storeProfile = useStoreProfile()
  return useMutation({
    mutationFn: (skills: { id: number; level: number }[]) => saveMySkills(skills),
    onSuccess: storeProfile,
    meta: { successMessage: 'Habilidades guardadas.' },
  })
}
