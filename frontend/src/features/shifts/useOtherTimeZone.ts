import { useProfileTimeZone } from '@/features/auth/useAuth'

/** True when the viewer's own clock is not the company's one (shift times are always company-local). */
export function useOtherTimeZone(companyTimeZone: string): boolean {
  return useProfileTimeZone() !== companyTimeZone
}
