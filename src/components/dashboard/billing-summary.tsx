

"use client";

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Info, Landmark } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useEffect, useState } from "react";
import { useBilling, MOCK_EXCHANGE_RATES } from "@/context/billing-context";
import { format } from "date-fns";


type FormattedRates = {
    usd: string;
    cad: string;
    gbp: string;
    eur: string;
}

const mockPayments = [
    { date: new Date('2025-01-15'), amount: 5000.00 },
    { date: new Date('2025-02-15'), amount: 5000.00 },
    { date: new Date('2025-03-15'), amount: 5000.00 },
    { date: new Date('2025-04-15'), amount: 5000.00 },
];

export function BillingSummary() {
    const { monthlyFee } = useBilling();
    const [rates, setRates] = useState<FormattedRates | null>(null);
    const [totalPaidYTD, setTotalPaidYTD] = useState(0);
    const [isClient, setIsClient] = useState(false);
    const [currentMonthYear, setCurrentMonthYear] = useState('');

    useEffect(() => {
        setIsClient(true);
    }, []);

    useEffect(() => {
        if (isClient) {
            setCurrentMonthYear(format(new Date(), 'MMMM yyyy'));
            
            const usd = (monthlyFee * MOCK_EXCHANGE_RATES.JMD_TO_USD);
            const cad = (monthlyFee * MOCK_EXCHANGE_RATES.JMD_TO_CAD);
            const gbp = (monthlyFee * MOCK_EXCHANGE_RATES.JMD_TO_GBP);
            const eur = (monthlyFee * MOCK_EXCHANGE_RATES.JMD_TO_EUR);
            
            setRates({
                usd: new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(usd),
                cad: new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(cad),
                gbp: new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(gbp),
                eur: new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(eur),
            });
    
            const ytd = mockPayments.reduce((acc, p) => acc + p.amount, 0);
            setTotalPaidYTD(ytd);
        }
    }, [isClient, monthlyFee]);
    
    if (!isClient) {
        return null;
    }

    return (
        <>
        <Card>
            <CardHeader>
                <CardTitle>Monthly Dues</CardTitle>
                <CardDescription>
                    Your upcoming HOA payment for {currentMonthYear} is detailed below.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex flex-col md:flex-row gap-4 items-baseline">
                    <div className="text-4xl font-bold">
                        {monthlyFee.toLocaleString('en-JM', { style: 'currency', currency: 'JMD' })}
                    </div>
                    {rates && (
                        <div className="flex gap-4 text-muted-foreground flex-wrap">
                            <span>≈ {rates.usd}</span>
                            <span>≈ {rates.cad}</span>
                            <span>≈ {rates.gbp}</span>
                            <span>≈ {rates.eur}</span>
                        </div>
                    )}
                </div>
                 <Alert className="bg-blue-50 border-blue-200 text-blue-800">
                    <Info className="h-4 w-4 !text-blue-800" />
                    <AlertDescription>
                        Exchange rates are for estimation purposes only. All transactions will be processed in JMD.
                    </AlertDescription>
                </Alert>
            </CardContent>
        </Card>
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Year-to-Date Payments (2025)</CardTitle>
                    <CardDescription>
                        Summary of your payments since Jan 1, 2025, shown in JMD.
                    </CardDescription>
                </div>
                <Landmark className="h-6 w-6 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{totalPaidYTD.toLocaleString('en-JM', { style: 'currency', currency: 'JMD' })}</p>
                <p className="text-sm text-muted-foreground">Total paid across {mockPayments.length} transactions.</p>
            </CardContent>
        </Card>
        </>
    )
}
