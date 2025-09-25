
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
import { useState } from "react";
import { DonateForm } from "./donate-form";
import { HeartHandshake } from "lucide-react";


type FundraiserProgressCardProps = {
    fundraiser: Fundraiser;
    donations: Donation[];
    onDonate?: (newDonation: Omit<Donation, 'id'>) => void;
    canManage?: boolean;
}

export function FundraiserProgressCard({ fundraiser, donations, onDonate, canManage }: FundraiserProgressCardProps) {
    const [isDonateOpen, setDonateOpen] = useState(false);
    const totalDonated = donations.reduce((acc, d) => acc + d.amount, 0);
    const progress = Math.min((totalDonated / fundraiser.goal) * 100, 100);
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


  return (
    <Card className="flex flex-col">
        <CardHeader>
            <div className="flex justify-between items-start">
                <CardTitle>{fundraiser.title}</CardTitle>
                 <Badge variant={getStatusVariant(fundraiser.status)}>{isCompleted && progress >= 100 ? 'Goal Reached!' : fundraiser.status}</Badge>
            </div>
            <CardDescription>{fundraiser.description}</CardDescription>
        </CardHeader>
        <CardContent className="flex-grow space-y-4">
            <div>
                <div className="flex justify-between items-end mb-1">
                    <span className="text-lg font-bold text-primary">${totalDonated.toLocaleString()}</span>
                    <span className="text-sm text-muted-foreground">raised of ${fundraiser.goal.toLocaleString()}</span>
                </div>
                <Progress value={progress} />
                 <p className="text-xs text-muted-foreground mt-1 text-right">{donations.length} donations</p>
            </div>
            
        </CardContent>
        <CardFooter className="flex flex-wrap justify-between items-center bg-muted/50 py-3 px-6">
            <div className="text-xs text-muted-foreground">
                {fundraiser.status === 'Upcoming' && <span>Starts: <ClientFormattedDate date={fundraiser.startDate} formatString="MMM d, yyyy" /></span>}
                {fundraiser.status === 'Active' && <span>Ends: <ClientFormattedDate date={fundraiser.endDate} formatString="MMM d, yyyy" /></span>}
                 {fundraiser.status === 'Completed' && <span>Ended: <ClientFormattedDate date={fundraiser.endDate} formatString="MMM d, yyyy" /></span>}
            </div>
            {!isCompleted && !isUpcoming && onDonate && (
                <DonateForm open={isDonateOpen} onOpenChange={setDonateOpen} fundraiser={fundraiser} onDonate={onDonate}>
                     <Button>
                        <HeartHandshake className="mr-2 h-4 w-4" />
                        Donate Now
                    </Button>
                </DonateForm>
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
