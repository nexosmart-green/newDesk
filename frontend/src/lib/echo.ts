import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { api, toApiError } from './api'

const env = import.meta.env

export const isRealtimeConfigured = (): boolean => Boolean(env.VITE_REVERB_APP_KEY)

/**
 * Reverb connection. Private channels authorize through the same axios instance as the API
 * (session cookie + XSRF header), at `/api/v1/broadcasting/auth`.
 * Returns null when Reverb isn't configured (VITE_REVERB_APP_KEY empty).
 */
export function createEcho(): Echo<'reverb'> | null {
  const key = env.VITE_REVERB_APP_KEY
  if (!key) return null
  const port = Number(env.VITE_REVERB_PORT) || undefined
  return new Echo({
    broadcaster: 'reverb',
    Pusher,
    key,
    wsHost: env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port ?? 80,
    wssPort: port ?? 443,
    forceTLS: (env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    channelAuthorization: {
      customHandler: ({ socketId, channelName }, callback) => {
        api
          .post<{ auth: string }>('/broadcasting/auth', { socket_id: socketId, channel_name: channelName })
          .then((response) => callback(null, response.data))
          .catch((error: unknown) => callback(toApiError(error), null))
      },
    },
  })
}
