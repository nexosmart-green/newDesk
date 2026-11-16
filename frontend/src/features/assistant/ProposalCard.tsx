import { CircleAlert, CircleCheck, CircleX, Clock } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import type { AssistantProposal, ProposalStatus } from './types'
import { useResolveProposal } from './useAssistant'

const ENDED: Record<Exclude<ProposalStatus, 'pending'>, { icon: LucideIcon; text: string; tone: string }> = {
  confirmed: { icon: CircleCheck, text: 'Hecho', tone: 'text-success' },
  cancelled: { icon: CircleX, text: 'Cancelada, no se cambió nada', tone: 'text-muted' },
  failed: { icon: CircleAlert, text: 'No se pudo hacer', tone: 'text-danger' },
  expired: { icon: Clock, text: 'Venció, pedíselo de nuevo', tone: 'text-muted' },
}

interface ProposalCardProps {
  proposal: AssistantProposal
  conversationId: number
}

/**
 * RF-13.02: what the assistant wants to change, waiting for this person. Nothing happened yet, and the card
 * says so; once resolved it stays in the thread as a one-line record of how it ended.
 */
export function ProposalCard({ proposal, conversationId }: ProposalCardProps) {
  const resolve = useResolveProposal(conversationId)
  const running = resolve.isPending ? resolve.variables.action : null

  if (proposal.status !== 'pending') {
    const ended = ENDED[proposal.status]
    return (
      <div className="flex items-start gap-2 text-sm">
        <ended.icon aria-hidden="true" className={`mt-0.5 size-4 shrink-0 ${ended.tone}`} strokeWidth={1.75} />
        <p className="min-w-0">
          <span className={`font-medium ${ended.tone}`}>{ended.text}:</span> {proposal.summary}
          {proposal.error && <span className="mt-0.5 block text-danger">{proposal.error}</span>}
        </p>
      </div>
    )
  }

  return (
    <div role="group" aria-label="Cambio para confirmar" className="space-y-3 border-l-2 border-brand py-1 pl-4">
      <div className="space-y-0.5">
        <p className="text-[13px] text-muted">Todavía no se hizo. Confirmalo para que pase.</p>
        <p className="text-[15px] leading-snug">{proposal.summary}</p>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button loading={running === 'confirm'} disabled={running !== null} onClick={() => resolve.mutate({ proposal, action: 'confirm' })}>
          Confirmar
        </Button>
        <Button variant="secondary" loading={running === 'cancel'} disabled={running !== null} onClick={() => resolve.mutate({ proposal, action: 'cancel' })}>
          Cancelar
        </Button>
      </div>
    </div>
  )
}
