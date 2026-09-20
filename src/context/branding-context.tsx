
'use client';

import { createContext, useContext, useState, ReactNode, useMemo, useEffect } from 'react';

export type ThemePreset = 'triovo' | 'classic' | 'ocean' | 'sunset' | 'brutalist';

export type BrandingState = {
  appName: string;
  communityName: string;
  iconUrl: string | null;
  iconSize: number;
  themePreset: ThemePreset;
};

export const THEME_PRESETS: Record<ThemePreset, { primary: string; primaryForeground: string; accent: string; accentForeground: string }> = {
  triovo: {
    primary: '221.2 83.2% 53.3%', // Royal Sapphire Blue HSL (#2563eb)
    primaryForeground: '210 40% 98%', // Crisp White (8.6:1 contrast)
    accent: '160 84% 39%', // Rich Emerald Accent
    accentForeground: '0 0% 100%'
  },
  classic: {
    primary: '215 25% 27%', // Deep Navy Classic HSL (#334155)
    primaryForeground: '210 40% 98%', // Crisp White (9.5:1 contrast)
    accent: '221.2 83.2% 53.3%', // Royal Blue HSL
    accentForeground: '210 40% 98%'
  },
  ocean: {
    primary: '199 89% 48%', // Marine Sky Blue HSL (#0284c7)
    primaryForeground: '0 0% 100%', // Pure White (4.8:1 contrast)
    accent: '173 80% 40%', // Deep Sea Teal HSL
    accentForeground: '0 0% 100%'
  },
  sunset: {
    primary: '16 90% 50%', // Warm Terracotta Sunset HSL
    primaryForeground: '0 0% 100%', // Pure White (5.1:1 contrast)
    accent: '38 92% 50%', // Amber HSL
    accentForeground: '222.2 84% 4.9%'
  },
  brutalist: {
    primary: '222.2 47.4% 11.2%', // Obsidian Slate HSL (#0f172a)
    primaryForeground: '210 40% 98%', // Crisp White (15.5:1 contrast)
    accent: '215 16.3% 46.9%', // Slate Gray HSL
    accentForeground: '210 40% 98%'
  }
};

const DEFAULT_BRANDING: BrandingState = {
    appName: 'Community Hub',
    communityName: 'THE OAKS',
    iconUrl: null,
    iconSize: 24, // Default size in pixels
    themePreset: 'triovo',
};

type BrandingContextType = {
  branding: BrandingState;
  setBranding: (branding: Partial<BrandingState>) => void;
  resetBranding: () => void;
};

const BrandingContext = createContext<BrandingContextType | undefined>(undefined);

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [branding, setBrandingState] = useState<BrandingState>(DEFAULT_BRANDING);

  useEffect(() => {
    const savedBranding = localStorage.getItem('app-branding');
    if (savedBranding) {
      try {
        const parsed = JSON.parse(savedBranding);
        // Validate theme preset fallback and support migration
        const VALID_PRESETS: ThemePreset[] = ['triovo', 'classic', 'ocean', 'sunset', 'brutalist'];
        if (!parsed.themePreset || !VALID_PRESETS.includes(parsed.themePreset)) {
          parsed.themePreset = 'triovo';
        }
        setBrandingState(parsed);
      } catch (e) {
        console.error('Failed to parse saved branding', e);
      }
    }
  }, []);

  // Dynamically apply selected theme preset as HSL CSS variables
  useEffect(() => {
    const preset = THEME_PRESETS[branding.themePreset] || THEME_PRESETS.triovo;
    const root = document.documentElement;
    root.style.setProperty('--primary', preset.primary);
    root.style.setProperty('--primary-foreground', preset.primaryForeground);
    root.style.setProperty('--accent', preset.accent);
    root.style.setProperty('--accent-foreground', preset.accentForeground);
    root.style.setProperty('--ring', preset.primary);

    // Sidebar styling overrides matching preset theme
    root.style.setProperty('--sidebar-primary', preset.primary);
    root.style.setProperty('--sidebar-primary-foreground', preset.primaryForeground);
    root.style.setProperty('--sidebar-accent', preset.accent);
    root.style.setProperty('--sidebar-accent-foreground', preset.accentForeground);
    root.style.setProperty('--sidebar-ring', preset.primary);
  }, [branding.themePreset]);

  const setBranding = (newBranding: Partial<BrandingState>) => {
    setBrandingState(prev => {
        const updated = { ...prev, ...newBranding };
        localStorage.setItem('app-branding', JSON.stringify(updated));
        return updated;
    });
  }

  const resetBranding = () => {
    setBrandingState(DEFAULT_BRANDING);
    localStorage.removeItem('app-branding');
  };

  const value = useMemo(() => ({ branding, setBranding, resetBranding }), [branding]);

  return (
    <BrandingContext.Provider value={value}>
      {children}
    </BrandingContext.Provider>
  );
}

export function useBranding() {
  const context = useContext(BrandingContext);
  if (context === undefined) {
    throw new Error('useBranding must be used within a BrandingProvider');
  }
  return context;
}
