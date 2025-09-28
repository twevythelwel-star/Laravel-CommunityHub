'use client';

import { createContext, useContext, useState, ReactNode, useMemo } from 'react';

// Mock exchange rates. In a real app, this would come from an API.
export const MOCK_EXCHANGE_RATES = {
    JMD_TO_USD: 0.0064,
    JMD_TO_GBP: 0.0051,
    JMD_TO_EUR: 0.0060,
    JMD_TO_CAD: 0.0088,
    USD_TO_JMD: 155.50,
    GBP_TO_JMD: 196.80,
    EUR_TO_JMD: 167.90,
    CAD_TO_JMD: 114.10,
};


type BillingContextType = {
  monthlyFee: number;
  setMonthlyFee: (fee: number) => void;
};

const BillingContext = createContext<BillingContextType | undefined>(undefined);

export function BillingProvider({ children }: { children: ReactNode }) {
  const [monthlyFee, setMonthlyFee] = useState(5000); // Default fee in JMD

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
