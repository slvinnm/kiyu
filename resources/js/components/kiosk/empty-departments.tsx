import { Ticket } from 'lucide-react'

import { Card } from '@/components/ui/card'

export function EmptyDepartments() {
  return (
    <Card className="mx-auto max-w-xl rounded-3xl border-0 bg-card p-10 text-center shadow-lg">
      <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-background">
        <Ticket className="size-8 text-muted-foreground" />
      </div>

      <h3 className="text-xl font-bold text-foreground">
        Belum ada departemen
      </h3>

      <p className="mt-2 text-muted-foreground">
        Data departemen belum tersedia untuk pengambilan antrean.
      </p>
    </Card>
  )
}
