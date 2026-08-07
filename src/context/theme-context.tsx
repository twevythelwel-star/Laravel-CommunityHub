
'use client';

import { createContext, useContext, useState, ReactNode, useEffect, useMemo, useCallback } from 'react';

type Theme = {
  primary: string;
  background: string;
  accent: string;
  font: string;
};

// Default theme matching globals.css HSL values converted to HEX
const DEFAULT_THEME: Theme = {
  primary: '#a7d7d0',   // hsl(188, 55%, 72%)
  background: '#f0f9ff', // hsl(208, 100%, 97%)
  accent: '#a5d9af',     // hsl(140, 44%, 73%)
  font: 'PT Sans',
};

// Function to convert HEX to HSL string for CSS variables
const hexToHsl = (hex: string): string => {
    let r = 0, g = 0, b = 0;
    if (hex.length === 4) {
        r = parseInt(hex[1] + hex[1], 16);
        g = parseInt(hex[2] + hex[2], 16);
        b = parseInt(hex[3] + hex[3], 16);
    } else if (hex.length === 7) {
        r = parseInt(hex.substring(1, 3), 16);
        g = parseInt(hex.substring(3, 5), 16);
        b = parseInt(hex.substring(5, 7), 16);
    }

    r /= 255;
    g /= 255;
    b /= 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0, s = 0, l = (max + min) / 2;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
    }

    h = Math.round(h * 360);
    s = Math.round(s * 100);
    l = Math.round(l * 100);
    
    return `${h} ${s}% ${l}%`;
}


type ThemeContextType = {
  theme: Theme;
  setTheme: (theme: Theme) => void;
  resetTheme: () => void;
  availableFonts: string[];
};

const availableFonts = [
    'PT Sans',
    'Roboto',
    'Open Sans',
    'Lato',
    'Montserrat',
    'Oswald',
    'Raleway',
];

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setTheme] = useState<Theme>(DEFAULT_THEME);

  useEffect(() => {
    const savedTheme = localStorage.getItem('app-theme');
    if (savedTheme) {
      setTheme(JSON.parse(savedTheme));
    }
  }, []);

  const resetTheme = useCallback(() => {
    setTheme(DEFAULT_THEME);
  }, []);

  useEffect(() => {
    localStorage.setItem('app-theme', JSON.stringify(theme));
    
    const root = document.documentElement;
    root.style.setProperty('--primary', hexToHsl(theme.primary));
    root.style.setProperty('--background', hexToHsl(theme.background));
    root.style.setProperty('--accent', hexToHsl(theme.accent));
    root.style.setProperty('--font-family', theme.font);

    // For simplicity, we'll derive other colors from these base colors.
    // This part can be expanded to allow full control.
    root.style.setProperty('--card', hexToHsl(theme.background)); // Card is same as background in default light theme
    root.style.setProperty('--sidebar-background', `color-mix(in srgb, ${theme.background} 95%, white)`);
    root.style.setProperty('--sidebar-accent', `color-mix(in srgb, ${theme.primary} 20%, ${theme.background})`);

  }, [theme]);

  const value = useMemo(() => ({ theme, setTheme, resetTheme, availableFonts }), [theme, resetTheme]);

  return (
    <ThemeContext.Provider value={value}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (context === undefined) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
}
