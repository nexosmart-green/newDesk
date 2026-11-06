import { useMutation } from '@tanstack/react-query'
import { useEffect, useState, type FormEvent } from 'react'
import { ApiError } from '@/lib/api'
import { requestCode, verifyCode } from '../../api/usePortal'
import { ErrorNote, Spinner } from '../../components/Panel'
import { CodeInput } from './CodeInput'

const resendSeconds = 60

// Cuenta regresiva en segundos; start(n) la reinicia.
function useCooldown(): [number, (seconds: number) => void] {
  const [remaining, setRemaining] = useState(0)

  useEffect(() => {
    if (remaining <= 0) return
    const timer = window.setTimeout(() => setRemaining((value) => value - 1), 1000)
    return () => window.clearTimeout(timer)
  }, [remaining])

  return [remaining, setRemaining]
}

// Mail → "Enviar código" → aparece el campo del código en la misma pantalla. El cliente termina acá (sin autenticador).
export function EmailCodeStep({ onVerified, onCodeSent }: { onVerified: () => void; onCodeSent: (sent: boolean) => void }) {
  const [email, setEmail] = useState('')
  const [code, setCode] = useState('')
  const [sentTo, setSentTo] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [cooldown, startCooldown] = useCooldown()

  const sendCode = useMutation({
    mutationFn: requestCode,
    onSuccess: (envelope, address) => {
      setSentTo(address)
      onCodeSent(true)
      setNotice(envelope.message || 'Si el mail está habilitado, te enviamos un código.')
      setCode('')
      startCooldown(resendSeconds)
    },
  })

  const checkCode = useMutation({
    mutationFn: () => verifyCode(sentTo ?? '', code),
    onSuccess: () => onVerified(),
  })

  const submitEmail = (event: FormEvent) => {
    event.preventDefault()
    const address = email.trim().toLowerCase()
    if (address) sendCode.mutate(address)
  }
  const submitCode = (event: FormEvent) => {
    event.preventDefault()
    if (code.length === 6) checkCode.mutate()
  }
  const changeEmail = () => {
    setSentTo(null)
    onCodeSent(false)
    setCode('')
    setNotice(null)
    checkCode.reset()
  }

  const codeError = checkCode.error instanceof ApiError ? (checkCode.error.field('code') ?? checkCode.error.message) : null
  const emailError = sendCode.error instanceof ApiError ? (sendCode.error.field('email') ?? sendCode.error.message) : null

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-[1.375rem] leading-7 font-semibold tracking-tight">Ingresar</h1>
        <p className="mt-1 text-sm text-muted">Sin contraseña: te mandamos un código al mail.</p>
      </div>

      <form onSubmit={submitEmail} className="space-y-3" noValidate>
        <label className="block">
          <span className="label mb-1.5 block">Mail</span>
          <input
            className="input"
            type="email"
            name="email"
            autoComplete="email"
            autoFocus
            required
            value={sentTo ?? email}
            disabled={sentTo !== null}
            aria-invalid={emailError ? 'true' : 'false'}
            placeholder="vos@nexosmart.com.ar"
            onChange={(event) => setEmail(event.target.value)}
          />
        </label>
        {sentTo === null ? (
          <button type="submit" className="btn btn-primary btn-lg w-full" disabled={!email.trim() || sendCode.isPending}>
            {sendCode.isPending ? (
              <>
                <Spinner /> Enviando…
              </>
            ) : (
              'Enviar código'
            )}
          </button>
        ) : (
          <div className="flex items-center justify-between text-footnote">
            <button type="button" className="link" onClick={changeEmail}>
              Cambiar mail
            </button>
            <button
              type="button"
              className="link num disabled:cursor-default disabled:text-faint disabled:no-underline"
              disabled={cooldown > 0 || sendCode.isPending}
              onClick={() => sendCode.mutate(sentTo)}
            >
              {cooldown > 0 ? `Reenviar código en ${cooldown} s` : 'Reenviar código'}
            </button>
          </div>
        )}
        {emailError && <ErrorNote>{emailError}</ErrorNote>}
      </form>

      {sentTo !== null && (
        <form onSubmit={submitCode} className="rise space-y-4 border-t border-line pt-5">
          {notice && <p className="text-sm text-muted">{notice}</p>}
          <CodeInput label="Código de 6 dígitos" value={code} onChange={setCode} autoFocus invalid={Boolean(codeError)} />
          {codeError && <ErrorNote>{codeError}</ErrorNote>}
          <button type="submit" className="btn btn-primary btn-lg w-full" disabled={code.length !== 6 || checkCode.isPending}>
            {checkCode.isPending ? (
              <>
                <Spinner /> Verificando…
              </>
            ) : (
              'Continuar'
            )}
          </button>
        </form>
      )}
    </div>
  )
}
