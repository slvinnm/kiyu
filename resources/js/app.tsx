import { createInertiaApp } from '@inertiajs/react'
import { ThemeProvider } from '@/components/theme-provider'
import { TooltipProvider } from '@/components/ui/tooltip'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

void createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,

    withApp(app) {
        return (
            <ThemeProvider
                attribute="class"
                defaultTheme="system"
                enableSystem
                disableTransitionOnChange
            >
                <TooltipProvider>
                    {app}
                </TooltipProvider>
            </ThemeProvider>
        )
    },
    progress: {
        color: '#4B5563',
    },
})