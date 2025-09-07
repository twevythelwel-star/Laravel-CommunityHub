'use client';

import { createContext, useContext, useState, ReactNode, useMemo } from 'react';

type BillingContextType = {
  monthlyFee: number;
  setMonthlyFee: (fee: number) => void;
};

const BillingContext = createContext<BillingContextType | undefined>(undefined);

export function BillingProvider({ children }: { children: ReactNode }) {
  const [monthlyFee, setMonthlyFee] = useState(30); // Default fee

  const value = useMemo(() => ({ monthlyFee, setMonthlyFee }), [monthlyFee]);

  return (
    <BillingContext.Provider value={value}>
      {children}
    </BillingContext.Provider>
  );
}

export function useBilling() {
  const context = useContext(BillingContext);
  if (context === undefined) {
    throw new Error('useBilling must be used within a BillingProvider');
  }
  return context;
}
