import { useOutletContext } from 'react-router'
import type { ProjectDetail } from './types'

/** The project page loads the project once and hands it to its tabs through the outlet. */
export function useProjectFromOutlet(): ProjectDetail {
  return useOutletContext<ProjectDetail>()
}
