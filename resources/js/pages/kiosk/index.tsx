import { Head } from '@inertiajs/react'

import './kiosk-marquee.css'
import { useCallback, useEffect, useRef, useState } from 'react'
import {
  ChevronRight,
  Clock3,
  Loader2,
  MonitorSmartphone,
  Ticket,
} from 'lucide-react'

import { Card } from '@/components/ui/card'
import {
  API_BASE_URL,
  SUCCESS_RESET_DELAY,
  createIdempotencyKey,
  formatDate,
  formatTime,
  getErrorMessage,
} from '@/lib/kiosk'
import type {
  ApiSuccessResponse,
  Department,
  KioskState,
  QueueAcquisition,
} from '@/types/kiosk'
import { ModeToggle } from '@/components/kiosk/mode-toggle'
import { SuccessScreen } from '@/components/kiosk/success-screen'
import { ErrorScreen } from '@/components/kiosk/error-screen'
import { DepartmentLoading } from '@/components/kiosk/department-loading'
import { DepartmentError } from '@/components/kiosk/department-error'
import { EmptyDepartments } from '@/components/kiosk/empty-departments'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'

export default function Kiosk() {
  const [departments, setDepartments] = useState<Department[]>([])
  const [loadingDepartments, setLoadingDepartments] = useState(true)
  const [departmentError, setDepartmentError] = useState<string | null>(null)

  const [now, setNow] = useState<Date | null>(null)
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
    setNow(new Date())

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
                    {now ? formatTime(now) : '--:--:--'}
                  </div>

                  <div className="mt-1 text-sm font-medium capitalize text-muted-foreground">
                    {now ? formatDate(now) : 'Memuat waktu...'}
                  </div>
                </div>

                <ModeToggle />
              </div>
            </div>

            {/* Mobile Clock */}
            <div className="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-card px-4 py-3 shadow-sm sm:hidden">
              <Clock3 className="size-4 text-muted-foreground" />

              <span className="font-bold tabular-nums text-foreground">
                {now ? formatTime(now) : '--:--:--'}
              </span>

              <span className="text-border">
                •
              </span>

              <span className="text-sm capitalize text-muted-foreground">
                {now ? formatDate(now) : 'Memuat waktu...'}
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
                          <Tooltip key={department.id}>
                            <TooltipTrigger
                              render={
                                <button
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
                                  className="group w-full text-left disabled:cursor-not-allowed"
                                >
                                  <Card
                                    className="relative min-h-[190px] overflow-hidden rounded-3xl border border-border bg-card p-7 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-ring hover:shadow-xl active:translate-y-0 disabled:opacity-60"
                                  >
                                    <div className="flex h-full flex-col justify-between">
                                      <div className="flex items-start justify-between">
                                        <div className="flex size-14 items-center justify-center rounded-2xl bg-muted text-xl font-bold text-foreground">
                                          {department.code.slice(-2)}
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
                              }
                            />

                            <TooltipContent side='bottom'>
                              <p>
                                {isLoading
                                  ? 'Sedang mengambil nomor antrean'
                                  : `Ambil nomor antrean ${department.name}`}
                              </p>
                            </TooltipContent>
                          </Tooltip>
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
    </>
  )
}

