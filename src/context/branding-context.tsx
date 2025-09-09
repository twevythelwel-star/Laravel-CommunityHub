
'use client';

import { createContext, useContext, useState, ReactNode, useMemo, useEffect } from 'react';

type BrandingState = {
  communityName: string;
  iconUrl: string | null;
  iconSize: number;
};

const DEFAULT_BRANDING: BrandingState = {
    communityName: 'Community Hub',
    iconUrl: null,
    iconSize: 24, // Default size in pixels (approx 1 inch on a 96dpi screen)
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
        setBrandingState(JSON.parse(savedBranding));
    }
  }, []);

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
