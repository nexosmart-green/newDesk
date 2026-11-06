import type { Dependencies, Dependency, Packages } from '../../api/contract'
import { DataTable } from '../../components/DataTable'
import { Panel, Stat } from '../../components/Panel'
import { Badge, YesNo } from '../../components/Status'

export function DependenciesTab({ data }: { data: Dependencies }) {
  const items = [...(data.items ?? [])].sort((a, b) => Number(b.installed) - Number(a.installed) || a.name.localeCompare(b.name))
  return (
    <Panel title={`Dependencias · ${items.filter((d) => d.installed).length} instaladas`} bodyClassName="">
      <DataTable<Dependency>
        noun="dependencias"
        rows={items}
        rowKey={(row) => `${row.name}-${row.path ?? ''}`}
        rowClassName={(row) => (row.broken ? 'bg-crit/5' : row.installed ? '' : 'opacity-50')}
        columns={[
          { header: 'Herramienta', cell: (row) => <span className="font-medium">{row.name}</span> },
          {
            header: 'Versión',
            cell: (row) =>
              row.broken ? <Badge tone="crit">rota</Badge> : row.installed ? <span className="font-mono text-xs">{row.version ?? '?'}</span> : <span className="text-faint">no instalado</span>,
          },
          {
            header: 'Ruta',
            cell: (row) => (
              <div className="max-w-[420px] font-mono text-xs">
                <div className="break-all">{row.path ?? '—'}</div>
                {row.resolves_to && <div className="break-all text-faint">→ {row.resolves_to}</div>}
              </div>
            ),
          },
          { header: 'Origen', cell: (row) => <span className="num text-xs text-muted">{row.source ?? ''}</span> },
          { header: 'Nota', cell: (row) => <span className="text-xs text-muted">{row.note ?? ''}</span> },
        ]}
      />
    </Panel>
  )
}

export function PackagesTab({ data }: { data: Packages }) {
  return (
    <div className="space-y-5">
      <div className="panel grid grid-cols-2 gap-5 p-4 sm:grid-cols-3 lg:grid-cols-6">
        <Stat label="Gestor" value={data.manager} />
        <Stat label="Actualizables" value={<span className={data.upgradable > 0 ? 'text-warn' : ''}>{data.upgradable}</span>} />
        <Stat label="De seguridad" value={<span className={data.security > 0 ? 'text-crit' : ''}>{data.security}</span>} />
        <div>
          <div className="label mb-1">Reinicio pendiente</div>
          <YesNo value={data.reboot_required} invert />
        </div>
        <div>
          <div className="label mb-1">Auto-updates</div>
          <YesNo value={data.unattended_upgrades} yes="Activos" no="Inactivos" />
        </div>
        <div>
          <div className="label mb-1">Repo de seguridad</div>
          <YesNo value={data.security_suite_configured} yes="Configurado" no="Falta" />
        </div>
      </div>
      {!data.security_suite_configured && (
        <p className="rounded-xl bg-crit/10 px-3.5 py-3 text-sm text-crit">
          Sin repositorio de seguridad: "0 de seguridad" no significa que esté al día, sino que no llegan parches.
        </p>
      )}
      <Panel title="Paquetes con actualización">
        {(data.upgradable_list ?? []).length === 0 ? (
          <p className="text-sm text-muted">Nada pendiente.</p>
        ) : (
          <ul className="flex flex-wrap gap-1.5 font-mono text-xs">
            {data.upgradable_list?.map((name) => (
              <li key={name} className="rounded-md bg-surface-2 px-2 py-0.5">{name}</li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  )
}
