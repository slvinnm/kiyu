import type { ApiErrorResponse } from '@/types/kiosk'

export const API_BASE_URL = '/api/v1/kiosk'
export const SUCCESS_RESET_DELAY = 5

export function createIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID()
  }

  return `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random()
    .toString(16)
    .slice(2)}`
}

export async function getErrorMessage(
  response: Response,
  fallbackMessage: string,
): Promise<string> {
  try {
    const payload = (await response.json()) as ApiErrorResponse

    if (payload.message) {
      return payload.message
    }

    if (payload.errors) {
      const firstError = Object.values(payload.errors).flat()[0]

      if (firstError) {
        return firstError
      }
    }
  } catch {
    // Response bukan JSON
  }

  return fallbackMessage
}

export function formatDate(date: Date): string {
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date)
}

export function formatTime(date: Date): string {
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).format(date)
}
