import { useMutation, useQueryClient } from '@tanstack/react-query'
import { monitoringApi as api } from './monitoringApi'
import { keys } from './useMonitoring'
import type {
  ClientRow, ComponentAdmin, ComponentInput, FolderRef, IncidentNote, ProjectAdmin, ProjectCreateInput, ProjectInput, User,
} from './types'

// Cualquier cambio de clientes, usuarios o proyectos invalida todo el árbol de portal-admin: son pocas filas.
function useInvalidatePortalAdmin() {
  const client = useQueryClient()
  return () => client.invalidateQueries({ queryKey: keys.portalAdmin })
}

export function useClientMutations() {
  const invalidate = useInvalidatePortalAdmin()
  const queryClient = useQueryClient()
  return {
    create: useMutation({ mutationFn: (name: string) => api.post<ClientRow>('portal-admin/clients', { name }), onSuccess: invalidate }),
    update: useMutation({
      mutationFn: ({ id, patch }: { id: number; patch: { name?: string; active?: boolean } }) => api.put<ClientRow>(`portal-admin/clients/${id}`, patch),
      onSuccess: invalidate,
    }),
    // Un cliente con proyectos no se borra: el back responde 422 con el motivo.
    remove: useMutation({ mutationFn: (id: number) => api.del<null>(`portal-admin/clients/${id}`), onSuccess: invalidate }),
    addUser: useMutation({
      mutationFn: ({ clientId, email }: { clientId: number; email: string }) => api.post<User>(`portal-admin/clients/${clientId}/users`, { email }),
      onSuccess: invalidate,
    }),
    updateUser: useMutation({
      mutationFn: ({ clientId, userId, active }: { clientId: number; userId: number; active: boolean }) =>
        api.put<User>(`portal-admin/clients/${clientId}/users/${userId}`, { active }),
      onSuccess: invalidate,
    }),
    removeUser: useMutation({
      mutationFn: ({ clientId, userId }: { clientId: number; userId: number }) => api.del<null>(`portal-admin/clients/${clientId}/users/${userId}`),
      onSuccess: invalidate,
    }),
    // Devuelve el sobre entero: el message avisa si el bot no pudo crear el canal de Discord.
    createProject: useMutation({
      mutationFn: ({ clientId, ...input }: ProjectCreateInput & { clientId: number }) =>
        api.postEnvelope<ProjectAdmin>(`portal-admin/clients/${clientId}/projects`, input),
      // Si el bot creó un canal, la lista de canales también cambió.
      onSuccess: async (_, input) => {
        await invalidate()
        if (input.create_discord_channel) await queryClient.invalidateQueries({ queryKey: keys.discordChannels })
      },
    }),
  }
}

export function useProjectMutations(projectId: number) {
  const invalidate = useInvalidatePortalAdmin()
  const queryClient = useQueryClient()
  const base = `portal-admin/projects/${projectId}`
  return {
    update: useMutation({ mutationFn: (input: ProjectInput) => api.put<ProjectAdmin>(base, input), onSuccess: invalidate }),
    remove: useMutation({ mutationFn: () => api.del<null>(base), onSuccess: invalidate }),
    uploadLogo: useMutation({
      mutationFn: (logo: { content_type: string; data_base64: string }) => api.put<ProjectAdmin>(`${base}/logo`, logo),
      onSuccess: invalidate,
    }),
    removeLogo: useMutation({ mutationFn: () => api.del<null>(`${base}/logo`), onSuccess: invalidate }),
    addComponent: useMutation({ mutationFn: (input: ComponentInput) => api.post<ComponentAdmin>(`${base}/components`, input), onSuccess: invalidate }),
    updateComponent: useMutation({
      mutationFn: ({ id, input }: { id: number; input: ComponentInput }) => api.put<ComponentAdmin>(`portal-admin/components/${id}`, input),
      onSuccess: invalidate,
    }),
    removeComponent: useMutation({ mutationFn: (id: number) => api.del<null>(`portal-admin/components/${id}`), onSuccess: invalidate }),
    checkComponent: useMutation({ mutationFn: (id: number) => api.post<ComponentAdmin>(`portal-admin/components/${id}/check`), onSuccess: invalidate }),
    // El back mide en segundo plano (~30 s por estrategia): no hay nada que invalidar todavía.
    measurePageSpeed: useMutation({ mutationFn: (id: number) => api.post<null>(`portal-admin/components/${id}/pagespeed`) }),
    // Manda ya un mensaje de prueba al canal guardado; devuelve el mensaje del back para mostrarlo.
    testDiscord: useMutation({ mutationFn: () => api.postEnvelope<null>(`${base}/discord-test`).then((envelope) => envelope.message) }),
    addNote: useMutation({
      mutationFn: ({ incidentId, body }: { incidentId: number; body: string }) => api.post<IncidentNote>(`portal-admin/incidents/${incidentId}/notes`, { body }),
      onSuccess: invalidate,
    }),
    removeNote: useMutation({ mutationFn: (id: number) => api.del<null>(`portal-admin/incident-notes/${id}`), onSuccess: invalidate }),
    // Reemplaza el conjunto. También cambia la etiqueta "Cliente" del buscador y de la pestaña Proyectos.
    saveFolders: useMutation({
      mutationFn: (folders: FolderRef[]) => api.put<ProjectAdmin>(`${base}/folders`, { folders }),
      onSuccess: () => {
        void invalidate()
        void queryClient.invalidateQueries({ queryKey: keys.inventory })
      },
    }),
    // Uno por uno, en orden: si uno falla, los anteriores ya quedaron agregados.
    addSuggestions: useMutation({
      mutationFn: async (inputs: ComponentInput[]) => {
        for (const input of inputs) await api.post<ComponentAdmin>(`${base}/components`, input)
      },
      onSettled: invalidate,
    }),
  }
}
