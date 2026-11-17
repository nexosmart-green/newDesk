import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { logout } from '@/features/auth/authApi'
import { ensureCsrfCookie } from '@/lib/api'
import { monitoringApi as api } from './monitoringApi'
import { keys } from './useMonitoring'
import type { ClientReport, ClientReportDetail, PortalCard, PortalStatus, ReportCreated, ReportMessage } from './types'

// El portal del cliente (`api/portal/<ruta>` de monitoreo → `/api/v1/portal/<ruta>`). El /me es el de newDesk, con `client`.

// La base del proyecto; también la del gráfico de tiempos (useResponseTimes).
export const portalProjectPath = (slug: string) => `portal/projects/${encodeURIComponent(slug)}`
const project = portalProjectPath

// ---------- ingreso con código por mail (ADR-0002 punto 7) ----------
// El código vale solo en el navegador que lo pidió: la sesión (y la cookie XSRF) tiene que existir antes de pedirlo.

export async function requestCode(email: string) {
  await ensureCsrfCookie()
  return api.postEnvelope<null>('portal/auth/request-code', { email })
}

export async function verifyCode(email: string, code: string) {
  await ensureCsrfCookie()
  return api.post<unknown>('portal/auth/verify-code', { email, code })
}

// Como en monitoreo: se va todo lo del cliente (cache, Reverb) recargando en el login.
export function usePortalLogout() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: logout,
    onSettled: () => {
      client.clear()
      window.location.assign('/portal/login')
    },
  })
}

// ---------- estado ----------

// El estado del portal no viaja por Reverb (solo los reportes): se refresca cada minuto.
export function usePortalProjects() {
  return useQuery({ queryKey: keys.portal, queryFn: () => api.get<PortalCard[]>('portal/projects'), refetchInterval: 60_000 })
}

export function usePortalProject(slug: string) {
  return useQuery({ queryKey: keys.portalProject(slug), queryFn: () => api.get<PortalStatus>(project(slug)), refetchInterval: 60_000 })
}

// ---------- reportar un problema ----------

export function usePortalReports(slug: string) {
  return useQuery({ queryKey: keys.portalReports(slug), queryFn: () => api.get<ClientReport[]>(`${project(slug)}/reports`) })
}

// Abrirlo lo marca leído: la lista vuelve a pedirse para que se vaya "Respuesta nueva".
export function usePortalReport(slug: string, id: number) {
  const client = useQueryClient()
  return useQuery({
    queryKey: keys.portalReport(slug, id),
    queryFn: async () => {
      const detail = await api.get<ClientReportDetail>(`${project(slug)}/reports/${id}`)
      void client.invalidateQueries({ queryKey: keys.portalReports(slug), exact: true })
      return detail
    },
  })
}

export function usePortalReportMessage(slug: string, id: number) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (form: FormData) => api.post<ReportMessage>(`${project(slug)}/reports/${id}/messages`, form),
    // Escribir en uno resuelto lo reabre: se piden de nuevo el reporte y la lista.
    onSuccess: () => void client.invalidateQueries({ queryKey: keys.portalReports(slug) }),
  })
}

// El back chequea en vivo todo el proyecto antes de responder (hasta ~20 s).
export function useReportProblem(slug: string) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (form: FormData) => api.post<ReportCreated>(`${project(slug)}/reports`, form),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: keys.portalReports(slug) })
      void client.invalidateQueries({ queryKey: keys.portalProject(slug) })
    },
  })
}
