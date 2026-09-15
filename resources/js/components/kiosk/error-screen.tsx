import { RefreshCw, RotateCcw, WifiOff, XCircle } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import type { Department } from '@/types/kiosk'

export function ErrorScreen({
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
          <Tooltip>
            <TooltipTrigger
              render={
                <div
                  tabIndex={0}
                  className="mx-auto flex size-12 cursor-help items-center justify-center rounded-xl border bg-muted outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                  <XCircle className="size-6" />
                </div>
              }
            />

            <TooltipContent>
              <p>Permintaan tidak berhasil diproses</p>
            </TooltipContent>
          </Tooltip>

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
              <Tooltip>
                <TooltipTrigger
                  render={
                    <div
                      tabIndex={0}
                      className="mt-0.5 flex size-8 shrink-0 cursor-help items-center justify-center rounded-lg border bg-background outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                      <WifiOff className="size-4" />
                    </div>
                  }
                />

                <TooltipContent>
                  <p>Periksa koneksi atau coba kembali</p>
                </TooltipContent>
              </Tooltip>

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
            <Tooltip>
              <TooltipTrigger
                render={
                  <Button
                    size="default"
                    variant="outline"
                    onClick={onReset}
                    className="h-10 flex-1 rounded-lg"
                  >
                    <RotateCcw className="mr-2 size-4" />
                    Kembali
                  </Button>
                }
              />

              <TooltipContent>
                <p>Kembali ke pilihan poliklinik</p>
              </TooltipContent>
            </Tooltip>

            <Tooltip>
              <TooltipTrigger
                render={
                  <Button
                    size="default"
                    onClick={onRetry}
                    className="h-10 flex-1 rounded-lg"
                  >
                    <RefreshCw className="mr-2 size-4" />
                    Coba Lagi
                  </Button>
                }
              />

              <TooltipContent>
                <p>Coba ambil nomor antrean kembali</p>
              </TooltipContent>
            </Tooltip>
          </div>
        </div>
      </Card>
    </div>
  )
}

