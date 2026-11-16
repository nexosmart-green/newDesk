import { useState } from 'react'
import { useSearchParamPatch } from './useSearchParamPatch'
import { isLocalDateString, parseLocalDate, weekOf } from './week'

/** The Monday–Sunday week in `?semana=<YYYY-MM-DD>` (bookmarkable), defaulting to the current one. */
export function useWeekParam() {
  const [searchParams, patch] = useSearchParamPatch()
  const [currentWeek] = useState(() => weekOf(new Date()))
  const value = searchParams.get('semana')
  const week = isLocalDateString(value) ? weekOf(parseLocalDate(value)) : currentWeek

  const goToWeek = (monday: string | null) => patch({ semana: monday })

  return { week, isCurrentWeek: week.from === currentWeek.from, goToWeek }
}
