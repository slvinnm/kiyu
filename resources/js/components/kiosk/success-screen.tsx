import { CheckCircle2, Clock3, RotateCcw } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import type { QueueAcquisition } from '@/types/kiosk'

export function SuccessScreen({
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
        <div className="px-2 py-2">
          <div className="text-center">
            <Tooltip>
              <TooltipTrigger
                render={
                  <div
                    tabIndex={0}
                    className="mx-auto flex size-12 cursor-help items-center justify-center rounded-xl border bg-muted outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                  >
                    <CheckCircle2 className="size-6" />
                  </div>
                }
              />

              <TooltipContent>
                <p>Nomor antrean berhasil dibuat</p>
              </TooltipContent>
            </Tooltip>

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

              <Tooltip>
                <TooltipTrigger
                  render={
                    <span
                      tabIndex={0}
                      className="mt-1 inline-flex cursor-help font-mono text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                      {acquisition.queue_ticket.status}
                    </span>
                  }
                />

                <TooltipContent>
                  <p>Status nomor antrean saat ini</p>
                </TooltipContent>
              </Tooltip>
            </div>
          </div>

          <div className="mx-auto mt-6 max-w-xl border-t pt-5">
            <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
              <Tooltip>
                <TooltipTrigger
                  render={
                    <div
                      tabIndex={0}
                      className="flex cursor-help items-center gap-2 text-sm text-muted-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                      <Clock3 className="size-4 shrink-0" />

                      <span>
                        Kembali ke awal dalam{' '}
                        <span className="font-semibold text-foreground">
                          {secondsRemaining} detik
                        </span>
                      </span>
                    </div>
                  }
                />

                <TooltipContent>
                  <p>Halaman akan kembali otomatis setelah hitungan selesai</p>
                </TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger
                  render={
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={onReset}
                      className="h-10 rounded-lg"
                    >
                      <RotateCcw className="mr-2 size-4" />
                      Kembali sekarang
                    </Button>
                  }
                />

                <TooltipContent>
                  <p>Kembali ke halaman pemilihan poliklinik</p>
                </TooltipContent>
              </Tooltip>
            </div>
          </div>
        </div>
      </Card>
    </div>
  )
}

