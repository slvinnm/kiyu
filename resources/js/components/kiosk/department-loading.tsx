export function DepartmentLoading() {
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

