'use client';

import { createContext, useContext, useState, ReactNode, useEffect, useMemo, useCallback } from 'react';

type Theme = {
  primary: string;
  background: string;
  accent: string;
  font: string;
};

// Enterprise high-contrast default theme
const DEFAULT_THEME: Theme = {
  primary: '#2563eb',   // Royal Blue (high contrast against white & dark)
  background: '#f8fafc', // Clean Slate Light
  accent: '#0d9488',     // Teal
  font: 'Inter',
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
};

type ThemeContextType = {
  theme: Theme;
  setTheme: (theme: Theme) => void;
  resetTheme: () => void;
  availableFonts: string[];
};

const availableFonts = [
    'Inter',
    'PT Sans',
    'Roboto',
    'Open Sans',
    'Lato',
    'Montserrat',
];

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setTheme] = useState<Theme>(DEFAULT_THEME);

  useEffect(() => {
    const savedTheme = localStorage.getItem('app-theme');
    if (savedTheme) {
      try {
        const parsed = JSON.parse(savedTheme);
        // Clean up legacy washed out pastel cyan presets
        if (parsed.primary === '#a7d7d0' || parsed.background === '#f0f9ff') {
          setTheme(DEFAULT_THEME);
          localStorage.setItem('app-theme', JSON.stringify(DEFAULT_THEME));
        } else {
          setTheme(parsed);
        }
      } catch {
        setTheme(DEFAULT_THEME);
      }
    }
  }, []);

  const resetTheme = useCallback(() => {
    setTheme(DEFAULT_THEME);
    localStorage.removeItem('app-theme');
  }, []);

  useEffect(() => {
    localStorage.setItem('app-theme', JSON.stringify(theme));
    
    const root = document.documentElement;
    root.style.setProperty('--font-family', theme.font);

    // Apply primary & accent colors if customized
    if (theme.primary && theme.primary !== DEFAULT_THEME.primary) {
      root.style.setProperty('--primary', hexToHsl(theme.primary));
    }
    if (theme.accent && theme.accent !== DEFAULT_THEME.accent) {
      root.style.setProperty('--accent', hexToHsl(theme.accent));
    }

    // Never allow light inline background/card overrides to clobber dark mode
    const observer = new MutationObserver(() => {
      const isDark = root.classList.contains('dark');
      if (isDark) {
        root.style.removeProperty('--background');
        root.style.removeProperty('--card');
        root.style.removeProperty('--sidebar-background');
        root.style.removeProperty('--sidebar-accent');
      }
    });

    observer.observe(root, { attributes: true, attributeFilter: ['class'] });

    return () => observer.disconnect();
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
