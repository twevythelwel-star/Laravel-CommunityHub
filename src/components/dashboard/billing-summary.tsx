
"use client";

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Info } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useEffect, useState } from "react";
import { useBilling } from "@/context/billing-context";

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

export function BillingSummary() {
    const { monthlyFee } = useBilling();
    const [rates, setRates] = useState<FormattedRates | null>(null);

    useEffect(() => {
        // This simulates fetching and formatting rates and ensures it only runs on the client.
        const jmd = (monthlyFee * MOCK_EXCHANGE_RATES.USD_TO_JMD).toFixed(2);
        const gbp = (monthlyFee * MOCK_EXCHANGE_RATES.USD_TO_GBP).toFixed(2);
        const eur = (monthlyFee * MOCK_EXCHANGE_RATES.USD_TO_EUR).toFixed(2);
        
        setRates({
            jmd: new Intl.NumberFormat('en-JM', { style: 'currency', currency: 'JMD' }).format(Number(jmd)),
            gbp: new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(Number(gbp)),
            eur: new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(Number(eur)),
        });
    }, [monthlyFee]);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Monthly Dues</CardTitle>
                <CardDescription>
                    Your upcoming HOA payment is detailed below.
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
    )
}
