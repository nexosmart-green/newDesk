import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { isLocalDateString, parseLocalDate, weekOf } from './week'

/** The Monday–Sunday week in `?semana=<YYYY-MM-DD>` (bookmarkable), defaulting to the current one. */
export function useWeekParam() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [currentWeek] = useState(() => weekOf(new Date()))
  const value = searchParams.get('semana')
  const week = isLocalDateString(value) ? weekOf(parseLocalDate(value)) : currentWeek

  function goToWeek(monday: string | null) {
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)
        if (monday === null) next.delete('semana')
        else next.set('semana', monday)
        return next
      },
      { replace: true },
    )
  }

  return { week, isCurrentWeek: week.from === currentWeek.from, goToWeek }
}
