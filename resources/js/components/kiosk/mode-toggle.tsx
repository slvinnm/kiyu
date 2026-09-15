import { useTheme } from 'next-themes'
import { Moon, Sun } from 'lucide-react'

import { Button } from '@/components/ui/button'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'

export function ModeToggle() {
  const { theme, setTheme } = useTheme()

  const isDark = theme === 'dark'

  const toggleTheme = () => {
    setTheme(isDark ? 'light' : 'dark')
  }

  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <Button
            type="button"
            variant="outline"
            size="icon"
            onClick={toggleTheme}
            aria-label={
              isDark
                ? 'Gunakan mode terang'
                : 'Gunakan mode gelap'
            }
            aria-pressed={isDark}
            className="size-10 rounded-xl"
          >
            {isDark ? (
              <Sun className="size-4" />
            ) : (
              <Moon className="size-4" />
            )}
          </Button>
        }
      />

      <TooltipContent side="bottom">
        <p>
          {isDark
            ? 'Gunakan mode terang'
            : 'Gunakan mode gelap'}
        </p>
      </TooltipContent>
    </Tooltip>
  )
}

