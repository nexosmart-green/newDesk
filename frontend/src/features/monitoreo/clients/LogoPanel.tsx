import { useState } from 'react'
import { apiUrl, errorMessage } from '../api/monitoringApi'
import { useProjectMutations } from '../api/useProjectMutations'
import type { ProjectAdmin } from '../api/types'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { ConfirmButton } from '../settings/ConfirmButton'
import { fileToBase64, logoProblem, LOGO_TYPES } from './logo'

export function LogoPanel({ project, readOnly }: { project: ProjectAdmin; readOnly: boolean }) {
  const { uploadLogo, removeLogo } = useProjectMutations(project.id)
  const [problem, setProblem] = useState<string | null>(null)
  const apiProblem = uploadLogo.error ? errorMessage(uploadLogo.error) : null

  const onFile = async (file: File | undefined) => {
    if (!file) return
    const issue = logoProblem(file)
    setProblem(issue)
    if (issue) return
    uploadLogo.mutate({ content_type: file.type, data_base64: await fileToBase64(file) })
  }

  return (
    <Panel title="Logo">
      <div className="space-y-3">
        <div className="grid h-28 place-items-center rounded-xl bg-surface-2">
          {project.has_logo ? (
            <img src={apiUrl(`api/portal-admin/projects/${project.id}/logo?v=${project.logo_updated_at ?? ''}`)} alt={`Logo de ${project.name}`} className="max-h-20 max-w-[80%] object-contain" />
          ) : (
            <span className="text-sm text-faint">Sin logo</span>
          )}
        </div>
        {!readOnly && (
          <div className="flex flex-wrap items-center gap-2">
            <label className="btn btn-sm cursor-pointer">
              {uploadLogo.isPending && <Spinner />}
              {project.has_logo ? 'Cambiar logo' : 'Subir logo'}
              <input
                type="file"
                accept={LOGO_TYPES.join(',')}
                className="sr-only"
                aria-label="Archivo del logo"
                onChange={(event) => {
                  void onFile(event.target.files?.[0])
                  event.target.value = ''
                }}
              />
            </label>
            {project.has_logo && <ConfirmButton label="Quitar" confirmLabel="Confirmar" onConfirm={() => removeLogo.mutate()} />}
          </div>
        )}
        <p className="text-xs text-muted">PNG, JPG o WebP, hasta 256 KB. Mejor cuadrado o con fondo transparente.</p>
        {(problem ?? apiProblem) && <ErrorNote>{problem ?? apiProblem}</ErrorNote>}
      </div>
    </Panel>
  )
}
