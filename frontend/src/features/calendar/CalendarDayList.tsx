import { DayList } from '@/components/ui/DayList'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { todayIn } from '@/lib/timezone'
import { useNow } from './useNow'
import { CalendarDayItems } from './CalendarDayItems'
import { isEmptyDay } from './calendarDays'
import type { CalendarDay } from './types'

interface CalendarDayListProps {
  dates: readonly string[]
  days: ReadonlyMap<string, CalendarDay>
}

/** My calendar as a list: one row per day, empty days collapsed ("Mié 11 a vie 13: nada agendado"). */
export function CalendarDayList({ dates, days }: CalendarDayListProps) {
  // The person's day (profile timezone), moving on by itself past midnight.
  const today = todayIn(useProfileTimeZone(), useNow())
  return (
    <DayList
      label="Días"
      dates={dates}
      today={today}
      emptyText="nada agendado"
      isEmpty={(date) => isEmptyDay(days.get(date))}
      renderDay={(date) => {
        const day = days.get(date)
        return day && <CalendarDayItems day={day} />
      }}
    />
  )
}
