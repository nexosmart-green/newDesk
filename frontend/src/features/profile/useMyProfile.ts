import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { currentUserKey } from '@/features/auth/useAuth'
import { fetchMyProfile, fetchMyTeam, saveMySkills, updateMyProfile, uploadMyAvatar } from '@/features/people/peopleApi'
import type { PersonProfile, UpdateOwnProfileInput } from '@/features/people/types'
import { peopleKey } from '@/features/people/usePeople'

/** Not under `['me', …]`: logout keeps only the `me` query, and the profile must not survive it. */
export const profileKey = ['profile'] as const
const teamKey = [...profileKey, 'team'] as const

export function useMyProfile() {
  return useQuery({ queryKey: profileKey, queryFn: fetchMyProfile })
}

export function useMyTeam() {
  return useQuery({ queryKey: teamKey, queryFn: fetchMyTeam })
}

function useStoreProfile() {
  const queryClient = useQueryClient()
  return async (profile: PersonProfile) => {
    queryClient.setQueryData(profileKey, profile)
    await Promise.all([
      // /me carries locale and timezone.
      queryClient.invalidateQueries({ queryKey: currentUserKey }),
      queryClient.invalidateQueries({ queryKey: peopleKey }),
    ])
  }
}

export function useUpdateMyProfile() {
  const storeProfile = useStoreProfile()
  return useMutation({ mutationFn: (input: UpdateOwnProfileInput) => updateMyProfile(input), onSuccess: storeProfile })
}

export function useUploadMyAvatar() {
  const storeProfile = useStoreProfile()
  return useMutation({
    mutationFn: ({ avatar, fileName }: { avatar: Blob; fileName: string }) => uploadMyAvatar(avatar, fileName),
    onSuccess: storeProfile,
  })
}

export function useSaveMySkills() {
  const storeProfile = useStoreProfile()
  return useMutation({
    mutationFn: (skills: { id: number; level: number }[]) => saveMySkills(skills),
    onSuccess: storeProfile,
  })
}
