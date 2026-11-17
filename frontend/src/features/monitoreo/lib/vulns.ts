import type { VulnCounts } from '../api/contract'

// Total de hallazgos de dependencias (D-053).
export const vulnTotal = (counts: VulnCounts) => counts.critical + counts.high + counts.moderate + counts.low
