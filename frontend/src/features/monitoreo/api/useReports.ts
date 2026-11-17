import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { RepoCommits } from '../lib/githubCommits'
import { monitoringApi as api } from './monitoringApi'
import { keys } from './useMonitoring'
import type { DailyReport, DailyReportStatus, ReportDetail, ReportMessage, ReportRow, ReportStatus } from './types'

// Reportes de los clientes (D-049..D-051): la cola del equipo, cada reporte con su chat y el roadmap de cada dev.

export function useReports(status: ReportStatus | null, page: number) {
  const params = new URLSearchParams({ page: String(page) })
  if (status) params.set('status', status)
  return useQuery({
    queryKey: keys.reports(status, page),
    queryFn: () => api.getEnvelope<ReportRow[]>(`portal-admin/reports?${params}`),
    placeholderData: keepPreviousData,
  })
}

// Para el número del menú: reportes nuevos y los que tienen mensajes del cliente sin leer.
// Lo actualiza Reverb; el intervalo es solo un respaldo.
export function useReportsAttention() {
  return useQuery({
    queryKey: keys.reportsAttention,
    queryFn: () => api.get<{ count: number }>('portal-admin/reports/attention'),
    select: (data) => data.count,
    refetchInterval: 5 * 60_000,
  })
}

// Abrirlo lo marca leído: el número del menú, las listas y el roadmap se piden de nuevo. Los detalles no:
// con varias ventanas flotantes abiertas, cada una invalidaría a las otras sin fin.
export function useReport(id: number) {
  const client = useQueryClient()
  return useQuery({
    queryKey: keys.report(id),
    queryFn: async () => {
      const report = await api.get<ReportDetail>(`portal-admin/reports/${id}`)
      void client.invalidateQueries({ queryKey: keys.allReports, predicate: (query) => typeof query.queryKey[3] !== 'number' })
      return report
    },
  })
}

export function useReportMessage(id: number) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (form: FormData) => api.post<ReportMessage>(`portal-admin/reports/${id}/messages`, form),
    onSuccess: () => void client.invalidateQueries({ queryKey: keys.allReports }),
  })
}

export function useReportStatus(id: number) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (status: ReportStatus) => api.put<ReportDetail>(`portal-admin/reports/${id}`, { status }),
    onSuccess: (report) => {
      client.setQueryData(keys.report(id), report)
      void client.invalidateQueries({ queryKey: keys.allReports })
    },
  })
}

// ---------- roadmap de reportes de cada dev (D-064, D-066) ----------
// Es personal: el back solo devuelve el de quien pregunta. Cada escritura devuelve el roadmap entero,
// así que la respuesta reemplaza el cache.

const getRoadmap = () => api.get<ReportRow[]>('portal-admin/roadmap')

export function useRoadmap() {
  return useQuery({ queryKey: keys.roadmap, queryFn: getRoadmap })
}

// Lo que falta resolver de mi roadmap: el número del botón.
export function useRoadmapPending() {
  return useQuery({ queryKey: keys.roadmap, queryFn: getRoadmap, select: (items) => items.filter((item) => item.status !== 'resolved').length })
}

export function useRoadmapAdd() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (reportId: number) => api.post<ReportRow[]>('portal-admin/roadmap', { report_id: reportId }),
    onSuccess: (items) => client.setQueryData(keys.roadmap, items),
  })
}

export function useRoadmapRemove() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (reportId: number) => api.del<ReportRow[]>(`portal-admin/roadmap/${reportId}`),
    onSuccess: (items) => client.setQueryData(keys.roadmap, items),
  })
}

// Optimista: el orden nuevo se ve al soltar; si el back lo rechaza (409: cambió en otra pestaña), vuelve el de antes y se pide de nuevo.
export function useRoadmapReorder() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (reportIds: number[]) => api.put<ReportRow[]>('portal-admin/roadmap', { report_ids: reportIds }),
    onMutate: async (reportIds) => {
      await client.cancelQueries({ queryKey: keys.roadmap })
      const previous = client.getQueryData<ReportRow[]>(keys.roadmap)
      client.setQueryData<ReportRow[]>(keys.roadmap, (current) =>
        current && reportIds.map((id) => current.find((item) => item.id === id)).filter((item) => item !== undefined),
      )
      return { previous }
    },
    onError: (_error, _ids, context) => {
      client.setQueryData(keys.roadmap, context?.previous)
      void client.invalidateQueries({ queryKey: keys.roadmap })
    },
    onSuccess: (items) => client.setQueryData(keys.roadmap, items),
  })
}

// ---------- "Generar reporte" del día (D-067, D-068, D-074) ----------

// Si se puede generar: depende solo de que el Claude de root del server esté conectado al panel (el token de GitHub lo pone cada uno).
// Se vuelve a preguntar cada minuto: alguien puede instalar el socket en el server mientras tanto.
export function useDailyReportStatus() {
  return useQuery({ queryKey: keys.dailyReportStatus, queryFn: () => api.get<DailyReportStatus>('daily-report/status'), refetchInterval: 60_000 })
}

export function useGenerateDailyReport() {
  const client = useQueryClient()
  return useMutation({
    // Solo los mensajes de los commits: el token de GitHub se queda en el navegador (lib/githubCommits).
    mutationFn: (repos: RepoCommits[]) => api.post<DailyReport>('daily-report', { repos }),
    // Un 409 puede ser que Claude se deslogueó: el botón se actualiza.
    onError: () => void client.invalidateQueries({ queryKey: keys.dailyReportStatus }),
  })
}
