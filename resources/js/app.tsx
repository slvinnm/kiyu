import { createInertiaApp } from '@inertiajs/react';
import { ThemeProvider } from "@/components/theme-provider"

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    withApp(app) {
        return (
            <ThemeProvider defaultTheme="dark" storageKey="vite-ui-theme">
                {app}
            </ThemeProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
