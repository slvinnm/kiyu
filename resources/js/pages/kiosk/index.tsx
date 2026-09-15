import { Head } from '@inertiajs/react'
import { useTheme } from 'next-themes'
import { useCallback, useEffect, useRef, useState } from 'react'
import {
  CheckCircle2,
  ChevronRight,
  Clock3,
  Loader2,
  MonitorSmartphone,
  Moon,
  RefreshCw,
  RotateCcw,
  Sun,
  Ticket,
  WifiOff,
  XCircle,
} from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'

type Department = {
  id: number
  code: string
  name: string
}

type QueueTicket = {
  id: number
  queue_number: string
  status: string
  priority: number
  station_id: number
}

type Visit = {
  id: number
  visit_number: string
}

type QueueAcquisition = {
  id: number
  status: string
  channel: string
  acquired_at: string
  department: Department
  visit: Visit
  queue_ticket: QueueTicket
}

type ApiSuccessResponse<T> = {
  success: true
  message: string
  data: T
}

type ApiErrorResponse = {
  success?: false
  message?: string
  errors?: Record<string, string[]>
}

type KioskState =
  | { type: 'idle' }
  | { type: 'loading'; departmentCode: string }
  | {
    type: 'success'
    acquisition: QueueAcquisition
    message: string
  }
  | {
    type: 'error'
    message: string
    departmentCode?: string
  }

const API_BASE_URL = '/api/v1/kiosk'
const SUCCESS_RESET_DELAY = 10

function createIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID()
  }

  return `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random()
    .toString(16)
    .slice(2)}`
}

async function getErrorMessage(
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

function formatDate(date: Date): string {
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date)
}

function formatTime(date: Date): string {
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).format(date)
}

export default function Kiosk() {
  const [departments, setDepartments] = useState<Department[]>([])
  const [loadingDepartments, setLoadingDepartments] = useState(true)
  const [departmentError, setDepartmentError] = useState<string | null>(null)

  const [now, setNow] = useState(new Date())
  const [secondsRemaining, setSecondsRemaining] = useState(SUCCESS_RESET_DELAY)

  const [state, setState] = useState<KioskState>({
    type: 'idle',
  })

  const activeIdempotencyKey = useRef<string | null>(null)

  const resetTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  /*
   * Realtime clock
   */
  useEffect(() => {
    const timer = window.setInterval(() => {
      setNow(new Date())
    }, 1000)

    return () => {
      window.clearInterval(timer)
    }
  }, [])

  useEffect(() => {
    if (state.type !== 'success') {
      return
    }

    const interval = window.setInterval(() => {
      setSecondsRemaining((seconds) => {
        if (seconds <= 1) {
          window.clearInterval(interval)
          return 0
        }

        return seconds - 1
      })
    }, 1000)

    return () => {
      window.clearInterval(interval)
    }
  }, [state.type])

  const clearResetTimer = useCallback(() => {
    if (resetTimer.current) {
      clearTimeout(resetTimer.current)
      resetTimer.current = null
    }
  }, [])

  const resetKiosk = useCallback(() => {
    clearResetTimer()

    activeIdempotencyKey.current = null
    setSecondsRemaining(SUCCESS_RESET_DELAY)

    setState({
      type: 'idle',
    })
  }, [clearResetTimer])

  const loadDepartments = useCallback(async () => {
    setLoadingDepartments(true)
    setDepartmentError(null)

    try {
      const response = await fetch(
        `${API_BASE_URL}/departments`,
        {
          method: 'GET',
          headers: {
            Accept: 'application/json',
          },
          credentials: 'same-origin',
        },
      )

      if (!response.ok) {
        const message = await getErrorMessage(
          response,
          'Gagal mengambil data poliklinik.',
        )

        throw new Error(message)
      }

      const payload =
        (await response.json()) as ApiSuccessResponse<Department[]>

      if (!payload.success) {
        throw new Error(
          payload.message ||
          'Gagal mengambil data poliklinik.',
        )
      }

      setDepartments(payload.data)
    } catch (error) {
      setDepartmentError(
        error instanceof Error
          ? error.message
          : 'Tidak dapat terhubung ke server.',
      )
    } finally {
      setLoadingDepartments(false)
    }
  }, [])

  useEffect(() => {
    loadDepartments()

    return () => {
      clearResetTimer()
    }
  }, [loadDepartments, clearResetTimer])

  const acquireQueue = async (
    department: Department,
    retry = false,
  ) => {
    /*
     * Request baru:
     * buat UUID baru.
     *
     * Retry:
     * tetap gunakan UUID sebelumnya.
     */
    if (!retry || !activeIdempotencyKey.current) {
      activeIdempotencyKey.current =
        createIdempotencyKey()
    }

    const idempotencyKey =
      activeIdempotencyKey.current

    setState({
      type: 'loading',
      departmentCode: department.code,
    })

    try {
      const params = new URLSearchParams({
        department_code: department.code,
        idempotency_key: idempotencyKey,
      })

      const controller = new AbortController()

      const timeout = setTimeout(() => {
        controller.abort()
      }, 15000)

      let response: Response

      try {
        response = await fetch(
          `${API_BASE_URL}/queue-acquisitions?${params.toString()}`,
          {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            signal: controller.signal,
          },
        )
      } finally {
        clearTimeout(timeout)
      }

      if (!response.ok) {
        const message = await getErrorMessage(
          response,
          'Gagal mengambil nomor antrean.',
        )

        throw new Error(message)
      }

      const payload =
        (await response.json()) as ApiSuccessResponse<QueueAcquisition>

      if (!payload.success) {
        throw new Error(
          payload.message ||
          'Nomor antrean gagal diperoleh.',
        )
      }

      setState({
        type: 'success',
        acquisition: payload.data,
        message: payload.message,
      })

      setSecondsRemaining(SUCCESS_RESET_DELAY)

      clearResetTimer()

      resetTimer.current = setTimeout(() => {
        resetKiosk()
      }, SUCCESS_RESET_DELAY * 1000)
    } catch (error) {
      let message =
        'Terjadi kesalahan saat mengambil nomor antrean.'

      if (
        error instanceof DOMException &&
        error.name === 'AbortError'
      ) {
        message =
          'Server tidak merespons. Silakan coba lagi.'
      } else if (error instanceof TypeError) {
        message =
          'Tidak dapat terhubung ke server. Periksa koneksi jaringan.'
      } else if (error instanceof Error) {
        message = error.message
      }

      setState({
        type: 'error',
        message,
        departmentCode: department.code,
      })
    }
  }

  const selectedDepartment =
    state.type === 'loading' ||
      state.type === 'error'
      ? departments.find(
        (department) =>
          department.code ===
          state.departmentCode,
      ) ?? null
      : null

  return (
    <>
      <Head title="Anjungan Kiosk" />

      <main className="min-h-screen bg-background">
        <div className="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-6 md:px-10 md:py-8">

          {/* Header */}
          <header className="mb-7">
            <div className="flex items-center justify-between gap-6">
              <div className="flex items-center gap-4">
                <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm">
                  <MonitorSmartphone className="size-6" />
                </div>

                <div>
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                    ANJUNGAN
                  </p>

                  <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                    Pengambilan Nomor Antrean
                  </h1>
                </div>
              </div>

              <div className="flex items-center gap-3">
                {/* Realtime Date & Clock */}
                <div className="hidden text-right sm:block">
                  <div className="text-2xl font-bold tabular-nums tracking-tight text-foreground md:text-3xl">
                    {formatTime(now)}
                  </div>

                  <div className="mt-1 text-sm font-medium capitalize text-muted-foreground">
                    {formatDate(now)}
                  </div>
                </div>

                <ModeToggle />
              </div>
            </div>

            {/* Mobile Clock */}
            <div className="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-card px-4 py-3 shadow-sm sm:hidden">
              <Clock3 className="size-4 text-muted-foreground" />

              <span className="font-bold tabular-nums text-foreground">
                {formatTime(now)}
              </span>

              <span className="text-border">
                •
              </span>

              <span className="text-sm capitalize text-muted-foreground">
                {formatDate(now)}
              </span>
            </div>
          </header>

          {/* Main */}
          <section className="flex flex-1 items-center justify-center">
            {state.type === 'success' ? (
              <SuccessScreen
                acquisition={
                  state.acquisition
                }
                message={state.message}
                onReset={resetKiosk}
                secondsRemaining={secondsRemaining}
              />
            ) : state.type === 'error' ? (
              <ErrorScreen
                message={state.message}
                department={
                  selectedDepartment
                }
                onRetry={() => {
                  if (!selectedDepartment) {
                    resetKiosk()
                    return
                  }

                  acquireQueue(
                    selectedDepartment,
                    true,
                  )
                }}
                onReset={resetKiosk}
              />
            ) : (
              <div className="w-full">
                <div className="mb-7 text-center">
                  <p className="mb-2 text-base font-medium text-muted-foreground">
                    Silakan pilih tujuan pelayanan Anda
                  </p>

                  <h2 className="text-3xl font-bold tracking-tight text-foreground md:text-4xl">
                    Pilih Poliklinik
                  </h2>
                </div>

                {loadingDepartments ? (
                  <DepartmentLoading />
                ) : departmentError ? (
                  <DepartmentError
                    message={
                      departmentError
                    }
                    onRetry={
                      loadDepartments
                    }
                  />
                ) : departments.length === 0 ? (
                  <EmptyDepartments />
                ) : (
                  <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {departments.map(
                      (department) => {
                        const isLoading =
                          state.type ===
                          'loading' &&
                          state.departmentCode ===
                          department.code

                        const anotherLoading =
                          state.type ===
                          'loading' &&
                          state.departmentCode !==
                          department.code

                        return (
                          <button
                            key={
                              department.id
                            }
                            type="button"
                            disabled={
                              isLoading ||
                              anotherLoading
                            }
                            onClick={() =>
                              acquireQueue(
                                department,
                              )
                            }
                            className="group text-left"
                          >
                            <Card
                              className="relative min-h-[190px] overflow-hidden rounded-3xl border border-border bg-card p-7 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-ring hover:shadow-xl active:translate-y-0 disabled:opacity-60"
                            >
                              <div className="flex h-full flex-col justify-between">
                                <div className="flex items-start justify-between">
                                  <div className="flex size-14 items-center justify-center rounded-2xl bg-background text-xl font-bold text-foreground">
                                    {department.code.slice(
                                      -2,
                                    )}
                                  </div>

                                  <div className="flex size-10 items-center justify-center rounded-full border border-border text-muted-foreground transition-all group-hover:border-primary group-hover:bg-primary group-hover:text-primary-foreground">
                                    {isLoading ? (
                                      <Loader2 className="size-5 animate-spin" />
                                    ) : (
                                      <ChevronRight className="size-5" />
                                    )}
                                  </div>
                                </div>

                                <div className="mt-8">
                                  <p className="mb-1 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                    {
                                      department.code
                                    }
                                  </p>

                                  <h3 className="text-xl font-bold leading-tight text-foreground md:text-2xl">
                                    {
                                      department.name
                                    }
                                  </h3>

                                  <div className="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
                                    {isLoading ? (
                                      <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Mengambil nomor...
                                      </>
                                    ) : (
                                      <>
                                        <Ticket className="size-4" />
                                        Tekan untuk mengambil antrean
                                      </>
                                    )}
                                  </div>
                                </div>
                              </div>
                            </Card>
                          </button>
                        )
                      },
                    )}
                  </div>
                )}
              </div>
            )}
          </section>

          {/* Running Text */}
          <div className="mt-7 overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div className="flex h-12 items-center">
              <div className="z-10 flex h-full shrink-0 items-center gap-2 bg-primary px-5 text-sm font-semibold text-primary-foreground">
                <Ticket className="size-4" />
                INFORMASI
              </div>

              <div className="relative min-w-0 flex-1 overflow-hidden">
                <div className="animate-kiosk-marquee flex min-w-max items-center whitespace-nowrap py-3 text-sm font-medium text-muted-foreground">
                  <span className="px-8">
                    Silakan pilih poliklinik sesuai
                    dengan tujuan pelayanan Anda.
                  </span>

                  <span className="text-border">
                    •
                  </span>

                  <span className="px-8">
                    Pastikan Anda mengambil satu nomor
                    antrean untuk setiap kunjungan.
                  </span>

                  <span className="text-border">
                    •
                  </span>

                  <span className="px-8">
                    Harap menunggu hingga nomor antrean
                    Anda dipanggil oleh petugas.
                  </span>

                  <span className="text-border">
                    •
                  </span>

                  <span className="px-8">
                    Terima kasih telah menggunakan
                    layanan anjungan antrean.
                  </span>

                  {/* Duplicate untuk seamless loop */}
                  <span className="px-8">
                    Silakan pilih poliklinik sesuai
                    dengan tujuan pelayanan Anda.
                  </span>

                  <span className="text-border">
                    •
                  </span>

                  <span className="px-8">
                    Pastikan Anda mengambil satu nomor
                    antrean untuk setiap kunjungan.
                  </span>

                  <span className="text-border">
                    •
                  </span>

                  <span className="px-8">
                    Harap menunggu hingga nomor antrean
                    Anda dipanggil oleh petugas.
                  </span>

                  <span className="text-border">
                    •
                  </span>

                  <span className="px-8">
                    Terima kasih telah menggunakan
                    layanan anjungan antrean.
                  </span>
                </div>
              </div>
            </div>
          </div>

          <footer className="flex items-center justify-center pt-4 text-xs text-muted-foreground">
            Sistem antrean pelayanan
          </footer>
        </div>
      </main>

      <style>{`
                @keyframes kiosk-marquee {
                    from {
                        transform: translateX(0);
                    }

                    to {
                        transform: translateX(-50%);
                    }
                }

                .animate-kiosk-marquee {
                    animation: kiosk-marquee 35s linear infinite;
                    will-change: transform;
                }

                @media (prefers-reduced-motion: reduce) {
                    .animate-kiosk-marquee {
                        animation: none;
                    }
                }
            `}</style>
    </>
  )
}

function ModeToggle() {
  const { resolvedTheme, setTheme } = useTheme()

  const isDark = resolvedTheme === 'dark'

  const toggleTheme = () => {
    setTheme(isDark ? 'light' : 'dark')
  }

  return (
    <Button
      type="button"
      variant="outline"
      size="icon"
      onClick={toggleTheme}
      aria-label={isDark ? 'Gunakan mode terang' : 'Gunakan mode gelap'}
      aria-pressed={isDark}
      title={isDark ? 'Mode terang' : 'Mode gelap'}
      className="size-10 rounded-xl"
    >
      {isDark ? (
        <Sun className="size-4" />
      ) : (
        <Moon className="size-4" />
      )}

      <span className="sr-only">
        {isDark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'}
      </span>
    </Button>
  )
}

function SuccessScreen({
  acquisition,
  message,
  onReset,
  secondsRemaining,
}: {
  acquisition: QueueAcquisition
  message: string
  onReset: () => void
  secondsRemaining: number
}) {
  const acquiredAt = new Date(acquisition.acquired_at)

  return (
    <div className="flex w-full max-w-3xl items-center justify-center">
      <Card className="w-full overflow-hidden rounded-3xl">
        <div className="px-6 py-8 md:px-10 md:py-10">
          <div className="text-center">
            <div className="mx-auto flex size-12 items-center justify-center rounded-xl border bg-muted">
              <CheckCircle2 className="size-6" />
            </div>

            <p className="mt-4 text-sm font-medium text-muted-foreground">
              Pengambilan nomor antrean berhasil
            </p>

            <h2 className="mt-1.5 text-2xl font-semibold tracking-tight">
              Silakan menunggu panggilan
            </h2>
          </div>

          <div className="mx-auto mt-7 max-w-md text-center">
            <p className="text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground">
              Nomor Antrean
            </p>

            <div className="mt-2 rounded-2xl border bg-muted/30 px-6 py-5">
              <p className="text-6xl font-black leading-none tracking-tight tabular-nums md:text-7xl">
                {acquisition.queue_ticket.queue_number}
              </p>
            </div>

            <p className="mt-4 font-semibold">
              {acquisition.department.name}
            </p>

            <p className="mt-1 text-sm text-muted-foreground">
              {message}
            </p>
          </div>

          <div className="mx-auto mt-7 grid max-w-xl divide-y rounded-xl border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div className="px-4 py-3 text-center">
              <p className="text-xs text-muted-foreground">
                Kunjungan
              </p>

              <p className="mt-1 font-mono text-sm font-semibold">
                {acquisition.visit.visit_number}
              </p>
            </div>

            <div className="px-4 py-3 text-center">
              <p className="text-xs text-muted-foreground">
                Waktu
              </p>

              <p className="mt-1 text-sm font-semibold tabular-nums">
                {new Intl.DateTimeFormat('id-ID', {
                  hour: '2-digit',
                  minute: '2-digit',
                  second: '2-digit',
                }).format(acquiredAt)}
              </p>
            </div>

            <div className="px-4 py-3 text-center">
              <p className="text-xs text-muted-foreground">
                Status
              </p>

              <p className="mt-1 text-sm font-semibold">
                {acquisition.queue_ticket.status}
              </p>
            </div>
          </div>

          <div className="mx-auto mt-6 max-w-xl border-t pt-5">
            <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Clock3 className="size-4 shrink-0" />

                <span>
                  Kembali ke awal dalam {' '}
                  <span className="font-semibold text-foreground">
                    {secondsRemaining} detik
                  </span>
                </span>
              </div>

              <Button
                size="sm"
                variant="outline"
                onClick={onReset}
                className="h-10 rounded-lg"
              >
                <RotateCcw className="mr-2 size-4" />
                Kembali sekarang
              </Button>
            </div>
          </div>
        </div>
      </Card>
    </div>
  )
}

function ErrorScreen({
  message,
  department,
  onRetry,
  onReset,
}: {
  message: string
  department: Department | null
  onRetry: () => void
  onReset: () => void
}) {
  return (
    <div className="flex w-full max-w-xl items-center justify-center">
      <Card className="w-full rounded-3xl">
        <div className="px-6 py-8 text-center md:px-10 md:py-10">
          <div className="mx-auto flex size-12 items-center justify-center rounded-xl border bg-muted">
            <XCircle className="size-6" />
          </div>

          <p className="mt-4 text-sm font-medium text-muted-foreground">
            Permintaan tidak dapat diproses
          </p>

          <h2 className="mt-1.5 text-2xl font-semibold tracking-tight">
            Nomor antrean belum berhasil diambil
          </h2>

          {department && (
            <p className="mt-2 text-sm text-muted-foreground">
              {department.name}
            </p>
          )}

          <div className="mx-auto mt-6 max-w-md rounded-xl border bg-muted/30 px-5 py-4 text-left">
            <div className="flex items-start gap-3">
              <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg border bg-background">
                <WifiOff className="size-4" />
              </div>

              <div>
                <p className="text-sm font-medium">
                  Terjadi kendala
                </p>

                <p className="mt-1 text-sm leading-5 text-muted-foreground">
                  {message}
                </p>
              </div>
            </div>
          </div>

          <p className="mx-auto mt-5 max-w-md text-xs leading-5 text-muted-foreground">
            Silakan coba kembali. Jika masalah tetap terjadi,
            hubungi petugas untuk mendapatkan bantuan.
          </p>

          <div className="mx-auto mt-7 flex max-w-md flex-col-reverse gap-2 sm:flex-row">
            <Button
              size="default"
              variant="outline"
              onClick={onReset}
              className="h-10 flex-1 rounded-lg"
            >
              <RotateCcw className="mr-2 size-4" />
              Kembali
            </Button>

            <Button
              size="default"
              onClick={onRetry}
              className="h-10 flex-1 rounded-lg"
            >
              <RefreshCw className="mr-2 size-4" />
              Coba Lagi
            </Button>
          </div>
        </div>
      </Card>
    </div>
  )
}

function DepartmentLoading() {
  return (
    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: 3 }).map((_, index) => (
        <div
          key={index}
          className="min-h-[190px] animate-pulse rounded-3xl border border-border bg-card p-7 shadow-sm"
        >
          <div className="size-14 rounded-2xl bg-muted" />

          <div className="mt-10 h-3 w-20 rounded bg-muted" />

          <div className="mt-3 h-7 w-3/4 rounded bg-muted" />

          <div className="mt-4 h-4 w-1/2 rounded bg-background" />
        </div>
      ))}
    </div>
  )
}

function DepartmentError({
  message,
  onRetry,
}: {
  message: string
  onRetry: () => void
}) {
  return (
    <Card className="mx-auto max-w-xl rounded-3xl border-0 bg-card p-8 text-center shadow-lg">
      <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-muted">
        <WifiOff className="size-8 text-foreground" />
      </div>

      <h3 className="text-xl font-bold text-foreground">
        Data poliklinik tidak dapat dimuat
      </h3>

      <p className="mt-2 text-muted-foreground">
        {message}
      </p>

      <Button
        size="lg"
        onClick={onRetry}
        className="mt-6 rounded-xl"
      >
        <RefreshCw className="mr-2 size-4" />
        Muat Ulang
      </Button>
    </Card>
  )
}

function EmptyDepartments() {
  return (
    <Card className="mx-auto max-w-xl rounded-3xl border-0 bg-card p-10 text-center shadow-lg">
      <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-background">
        <Ticket className="size-8 text-muted-foreground" />
      </div>

      <h3 className="text-xl font-bold text-foreground">
        Belum ada poliklinik
      </h3>

      <p className="mt-2 text-muted-foreground">
        Data poliklinik belum tersedia untuk pengambilan antrean.
      </p>
    </Card>
  )
}
