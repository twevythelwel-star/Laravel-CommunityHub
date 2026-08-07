
'use client';

import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import { ClientFormattedDate } from "@/components/client-formatted-date";
import type { Fundraiser, Donation } from "@/types";
import { useState, useMemo } from "react";
import { DonateForm } from "./donate-form";
import { HeartHandshake, Share2 } from "lucide-react";

// Mock exchange rates. In a real app, this would come from an API.
const MOCK_EXCHANGE_RATES = {
    JMD_TO_USD: 0.0064,
    JMD_TO_GBP: 0.0051,
    JMD_TO_EUR: 0.0060,
    JMD_TO_CAD: 0.0088,
    USD_TO_JMD: 155.50,
    GBP_TO_JMD: 196.80,
    EUR_TO_JMD: 167.90,
    CAD_TO_JMD: 114.10,
};


type FundraiserProgressCardProps = {
    fundraiser: Fundraiser;
    donations: Donation[];
    onDonate?: (newDonation: Omit<Donation, 'id'>) => void;
    canManage?: boolean;
}

export function FundraiserProgressCard({ fundraiser, donations, onDonate, canManage }: FundraiserProgressCardProps) {
    const [isDonateOpen, setDonateOpen] = useState(false);

    const totalDonatedInJMD = useMemo(() => {
        return donations.reduce((acc, d) => {
            const amountInJMD = d.amount * (MOCK_EXCHANGE_RATES[`${d.currency}_TO_JMD` as keyof typeof MOCK_EXCHANGE_RATES] || 1);
            return acc + amountInJMD;
        }, 0);
    }, [donations]);

    const progress = Math.min((totalDonatedInJMD / fundraiser.goal) * 100, 100);
    const isCompleted = fundraiser.status === 'Completed' || progress >= 100;
    const isUpcoming = fundraiser.status === 'Upcoming';

    const getStatusVariant = (status: Fundraiser['status']) => {
        switch (status) {
            case 'Active':
                return 'default';
            case 'Completed':
                return 'secondary';
            case 'Upcoming':
                return 'outline';
            case 'Canceled':
                return 'destructive';
            default:
                return 'default';
        }
    }
    
    const equivalentGoals = {
        USD: (fundraiser.goal * MOCK_EXCHANGE_RATES.JMD_TO_USD).toLocaleString('en-US', { style: 'currency', currency: 'USD' }),
        GBP: (fundraiser.goal * MOCK_EXCHANGE_RATES.JMD_TO_GBP).toLocaleString('en-GB', { style: 'currency', currency: 'GBP' }),
        EUR: (fundraiser.goal * MOCK_EXCHANGE_RATES.JMD_TO_EUR).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }),
        CAD: (fundraiser.goal * MOCK_EXCHANGE_RATES.JMD_TO_CAD).toLocaleString('en-CA', { style: 'currency', currency: 'CAD' }),
    };


  return (
    <Card className="flex flex-col h-full overflow-hidden">
        <CardHeader>
            <div className="flex justify-between items-start">
                <CardTitle>{fundraiser.title}</CardTitle>
                 <Badge variant={getStatusVariant(fundraiser.status)}>{isCompleted && progress >= 100 ? 'Goal Reached!' : fundraiser.status}</Badge>
            </div>
            <CardDescription>{fundraiser.description}</CardDescription>
        </CardHeader>
        <CardContent className="flex-grow space-y-4">
            <div>
                <div className="flex flex-col gap-1 mb-2">
                    <div className="flex items-baseline justify-between">
                        <span className="text-2xl font-bold tracking-tight text-primary">{totalDonatedInJMD.toLocaleString('en-JM', { style: 'currency', currency: 'JMD' })}</span>
                        <span className="text-sm font-medium text-muted-foreground">Goal: {fundraiser.goal.toLocaleString('en-JM', { style: 'currency', currency: 'JMD' })}</span>
                    </div>
                </div>
                <Progress value={progress} className="h-2" />
                 <div className="flex justify-between items-center mt-2">
                    <p className="text-xs text-muted-foreground">
                        Approx. {equivalentGoals.USD} / {equivalentGoals.GBP} / {equivalentGoals.EUR} / {equivalentGoals.CAD}
                    </p>
                    <p className="text-xs text-muted-foreground font-medium">{donations.length} donations</p>
                 </div>
            </div>
            
        </CardContent>
        <CardFooter className="flex flex-wrap justify-between items-center bg-muted/50 py-3 px-6 mt-auto">
            <div className="text-xs text-muted-foreground">
                {fundraiser.status === 'Upcoming' && <span>Starts: <ClientFormattedDate date={fundraiser.startDate} formatString="MMM d, yyyy" /></span>}
                {fundraiser.status === 'Active' && <span>Ends: <ClientFormattedDate date={fundraiser.endDate} formatString="MMM d, yyyy" /></span>}
                 {fundraiser.status === 'Completed' && <span>Ended: <ClientFormattedDate date={fundraiser.endDate} formatString="MMM d, yyyy" /></span>}
            </div>
            {fundraiser.status === 'Active' && (
                <div className="flex gap-2 w-full sm:w-auto">
                    <DonateForm open={isDonateOpen} onOpenChange={setDonateOpen} fundraiser={fundraiser} onDonate={onDonate || (() => {})}>
                        <Button className="flex-1 sm:flex-none">
                            <HeartHandshake className="mr-2 h-4 w-4" />
                            Donate Now
                        </Button>
                    </DonateForm>
                    <Button variant="outline" className="flex-1 sm:flex-none">
                        <Share2 className="mr-2 h-4 w-4" />
                        Share
                    </Button>
                </div>
            )}
            {isUpcoming && canManage && (
                 <Button variant="outline" size="sm">Enable Now</Button>
            )}
             {isCompleted && (
                 <Button variant="secondary" disabled>Goal Reached</Button>
            )}
        </CardFooter>
    </Card>
  );
}
