
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
    primary: '262 83% 58%', // Violet HSL
    primaryForeground: '0 0% 100%',
    accent: '188 95% 42%', // Cyan HSL
    accentForeground: '0 0% 100%'
  },
  classic: {
    primary: '188 55% 72%', // Classic Light Blue HSL
    primaryForeground: '188 100% 10%',
    accent: '140 44% 73%', // Soft Green HSL
    accentForeground: '140 100% 10%'
  },
  ocean: {
    primary: '217 91% 60%', // Ocean Blue HSL
    primaryForeground: '0 0% 100%',
    accent: '197 100% 46%', // Sky Accent HSL
    accentForeground: '0 0% 100%'
  },
  sunset: {
    primary: '16 90% 55%', // Sunset Red-Orange HSL
    primaryForeground: '0 0% 100%',
    accent: '43 96% 56%', // Gold Accent HSL
    accentForeground: '224 71.4% 4.1%'
  },
  brutalist: {
    primary: '240 5.9% 10%', // Dark Charcoal HSL
    primaryForeground: '0 0% 98%',
    accent: '240 5.9% 50%', // Slate Gray HSL
    accentForeground: '0 0% 98%'
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
