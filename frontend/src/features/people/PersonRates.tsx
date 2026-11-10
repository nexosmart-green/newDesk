import { PageState } from '@/components/ui/PageState'
import { formatDate } from '@/lib/dates'
import { formatMoney } from '@/lib/money'
import { AddRateForm } from './AddRateForm'
import { useRates } from './usePeople'

/** RF-01.11: rate history with validity, so a raise never rewrites past profitability. */
export function PersonRates({ personId, canAdd }: { personId: number; canAdd: boolean }) {
  const rates = useRates(personId)

  return (
    <div className="space-y-4">
      {!rates.data ? (
        <PageState isPending={rates.isPending} error={rates.error} />
      ) : rates.data.length === 0 ? (
        <p className="text-sm text-muted">Todavía no tiene tarifa cargada.</p>
      ) : (
        <table className="w-full text-left text-sm">
          <caption className="sr-only">Historial de tarifas por hora</caption>
          <thead className="text-xs text-muted">
            <tr>
              <th className="pb-2 font-normal">Desde</th>
              <th className="pb-2 font-normal">Hasta</th>
              <th className="pb-2 text-right font-normal">Por hora</th>
            </tr>
          </thead>
          <tbody>
            {rates.data.map((rate) => (
              <tr key={rate.id} className="border-t border-border">
                <td className="py-2">{formatDate(rate.valid_from)}</td>
                <td className="py-2">{rate.valid_to ? formatDate(rate.valid_to) : 'Vigente'}</td>
                <td className="py-2 text-right tabular-nums">{formatMoney(rate.amount_cents, rate.currency)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {canAdd && <AddRateForm personId={personId} />}
    </div>
  )
}
