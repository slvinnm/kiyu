import { RefreshCw, WifiOff } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'

export function DepartmentError({
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

      <Tooltip>
        <TooltipTrigger
          render={
            <Button
              size="lg"
              onClick={onRetry}
              className="mt-6 rounded-xl"
            >
              <RefreshCw className="mr-2 size-4" />
              Muat Ulang
            </Button>
          }
        />

        <TooltipContent>
          <p>Muat ulang data poliklinik</p>
        </TooltipContent>
      </Tooltip>
    </Card>
  )
}

