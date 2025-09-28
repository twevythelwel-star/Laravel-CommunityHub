

'use client';

import { AuthProvider } from '@/context/auth-context';
import { BillingProvider } from '@/context/billing-context';
import { BrandingProvider } from '@/context/branding-context';
import { ThemeProvider, useTheme } from '@/context/theme-context';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useEffect, useState } from 'react';
import { Toaster } from './ui/toaster';

function FontLoader() {
    const { theme } = useTheme();

    useEffect(() => {
        if (theme?.font) {
            const fontUrl = `https://fonts.googleapis.com/css2?family=${theme.font.replace(/ /g, '+')}:wght@400;700&display=swap`;
            
            let link = document.getElementById('app-font') as HTMLLinkElement;
            if (link) {
                link.href = fontUrl;
            } else {
                link = document.createElement('link');
                link.id = 'app-font';
                link.rel = 'stylesheet';
                link.href = fontUrl;
                document.head.appendChild(link);
            }
        }
    }, [theme?.font]);

    return (
         <>
            <link rel="preconnect" href="https://fonts.googleapis.com" />
            <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        </>
    )
}

export function Providers({ children }: { children: React.ReactNode }) {
    const [isClient, setIsClient] = useState(false);

    useEffect(() => {
        setIsClient(true);
    }, []);

    if (!isClient) {
        return null;
    }

    return (
        <ThemeProvider>
            <TooltipProvider>
                <FontLoader />
                <AuthProvider>
                    <BrandingProvider>
                        <BillingProvider>
                            {children}
                            <Toaster />
                        </BillingProvider>
                    </BrandingProvider>
                </AuthProvider>
            </TooltipProvider>
        </ThemeProvider>
    )
}
