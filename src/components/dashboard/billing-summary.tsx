

"use client";

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Info, Landmark } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useEffect, useState } from "react";
import { useBilling } from "@/context/billing-context";
import { format } from "date-fns";

// Mock exchange rates. In a real app, this would come from an API.
const MOCK_EXCHANGE_RATES = {
    USD_TO_JMD: 155.50,
    USD_TO_GBP: 0.79,
    USD_TO_EUR: 0.92,
};

type FormattedRates = {
    jmd: string;
    gbp: string;
    eur: string;
}

const mockPayments = [
    { date: new Date('2025-01-15'), amount: 30.00 },
    { date: new Date('2025-02-15'), amount: 30.00 },
    { date: new Date('2025-03-15'), amount: 30.00 },
    { date: new Date('2025-04-15'), amount: 30.00 },
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
            
            const jmd = (monthlyFee * MOCK_EXCHANGE_RATES.USD_TO_JMD);
            const gbp = (monthlyFee * MOCK_EXCHANGE_RATES.USD_TO_GBP);
            const eur = (monthlyFee * MOCK_EXCHANGE_RATES.USD_TO_EUR);
            
            setRates({
                jmd: new Intl.NumberFormat('en-JM', { style: 'currency', currency: 'JMD' }).format(jmd),
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
                        ${monthlyFee.toFixed(2)}
                        <span className="text-lg font-normal text-muted-foreground ml-1">USD</span>
                    </div>
                    {rates && (
                        <div className="flex gap-4 text-muted-foreground">
                            <span>≈ {rates.jmd}</span>
                            <span>≈ {rates.gbp}</span>
                            <span>≈ {rates.eur}</span>
                        </div>
                    )}
                </div>
                 <Alert className="bg-blue-50 border-blue-200 text-blue-800">
                    <Info className="h-4 w-4 !text-blue-800" />
                    <AlertDescription>
                        Exchange rates are for estimation purposes only. All transactions will be processed in USD.
                    </AlertDescription>
                </Alert>
            </CardContent>
        </Card>
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Year-to-Date Payments (2025)</CardTitle>
                    <CardDescription>
                        Summary of your payments since Jan 1, 2025.
                    </CardDescription>
                </div>
                <Landmark className="h-6 w-6 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">${totalPaidYTD.toFixed(2)}</p>
                <p className="text-sm text-muted-foreground">Total paid across {mockPayments.length} transactions.</p>
            </CardContent>
        </Card>
        </>
    )
}
