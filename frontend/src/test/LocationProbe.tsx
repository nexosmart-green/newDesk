import { useLocation } from 'react-router'

/** Placeholder route element that shows where the app ended up. */
export function LocationProbe() {
  const location = useLocation()
  const state = location.state as { notice?: string } | null
  return (
    <p data-testid="location">
      {location.pathname}
      {state?.notice ? ` | ${state.notice}` : ''}
    </p>
  )
}
