
import type { Metadata } from 'next';
import './globals.css';
import { Providers } from '@/components/providers';


export const metadata: Metadata = {
  title: 'Community Hub',
  description: 'Your one-stop solution for community management.',
};


export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
        <body className="font-body antialiased" suppressHydrationWarning>
          <Providers>
            {children}
          </Providers>
        </body>
    </html>
  );
}
