
'use client';

import { AuthProvider } from '@/context/auth-context';
import { BillingProvider } from '@/context/billing-context';
import { ThemeProvider, useTheme } from '@/context/theme-context';

function FontLoader() {
    const { theme } = useTheme();

    if (!theme?.font) {
        return null;
    }

    const fontUrl = `https://fonts.googleapis.com/css2?family=${theme.font.replace(/ /g, '+')}:wght@400;700&display=swap`;

    return (
        <>
            <link rel="preconnect" href="https://fonts.googleapis.com" />
            <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
            <link
              id="app-font"
              href={fontUrl}
              rel="stylesheet"
            />
        </>
    )
}

function AppBody({ children }: { children: React.ReactNode }) {
    const { theme } = useTheme();
    return (
        <div className="font-body antialiased" style={{ fontFamily: `'${theme.font}', sans-serif` }}>
           {children}
        </div>
    )
}


export function Providers({ children }: { children: React.ReactNode }) {
    return (
        <ThemeProvider>
            <FontLoader />
            <AuthProvider>
                <BillingProvider>
                    <AppBody>
                        {children}
                    </AppBody>
                </BillingProvider>
            </AuthProvider>
        </ThemeProvider>
    )
}
