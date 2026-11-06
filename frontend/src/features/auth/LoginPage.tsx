import { useState } from 'react'
import { Navigate, useLocation } from 'react-router'
import { AuthCard } from '@/components/ui/AuthCard'
import { SuccessNotice } from '@/components/ui/ErrorAlert'
import { ApiError } from '@/lib/api'
import type { LoginInput } from './authApi'
import { LoginPasswordForm } from './LoginPasswordForm'
import { TwoFactorChallengeForm } from './TwoFactorChallengeForm'
import type { TwoFactorChallengeInput } from './types'
import { useCurrentUser, useLogin, useTwoFactorChallenge } from './useAuth'

type Step = 'password' | 'two-factor'

export function LoginPage() {
  const [step, setStep] = useState<Step>('password')
  const [expiredNotice, setExpiredNotice] = useState<string | null>(null)
  const loginMutation = useLogin()
  const challengeMutation = useTwoFactorChallenge()
  const { data: user } = useCurrentUser()
  const location = useLocation()
  const locationState = location.state as { from?: string; notice?: string } | null
  const redirectTo = locationState?.from ?? '/'

  if (user) return <Navigate to={redirectTo} replace />

  function handleLogin(input: LoginInput) {
    setExpiredNotice(null)
    loginMutation.mutate(input, {
      onSuccess: (result) => {
        if (result.status === 'two_factor_required') setStep('two-factor')
      },
    })
  }

  function handleChallenge(input: TwoFactorChallengeInput) {
    challengeMutation.mutate(input, {
      onError: (error) => {
        // The pending login expired: the password has to be entered again.
        if (error instanceof ApiError && error.status === 401) backToPassword(error.message)
      },
    })
  }

  function backToPassword(notice: string | null = null) {
    challengeMutation.reset()
    loginMutation.reset()
    setExpiredNotice(notice)
    setStep('password')
  }

  return (
    <AuthCard title={step === 'password' ? 'Ingresar' : 'Verificación en dos pasos'}>
      <SuccessNotice message={locationState?.notice} />
      {expiredNotice && (
        <p role="alert" className="text-sm text-warning">
          {expiredNotice}
        </p>
      )}
      {step === 'password' ? (
        <LoginPasswordForm onSubmit={handleLogin} isPending={loginMutation.isPending} error={loginMutation.error} />
      ) : (
        <TwoFactorChallengeForm
          onSubmit={handleChallenge}
          onCancel={() => backToPassword()}
          isPending={challengeMutation.isPending}
          error={challengeMutation.error}
        />
      )}
    </AuthCard>
  )
}
