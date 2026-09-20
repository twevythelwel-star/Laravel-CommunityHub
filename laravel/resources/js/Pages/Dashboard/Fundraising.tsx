


import { useState } from 'react';
import {
  Card,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { PlusCircle } from "lucide-react";
import type { Fundraiser, Donation, UserRole } from "@/types";
import { useAuth } from '@/context/auth-context';
import { CreateFundraiserForm } from '@/components/dashboard/create-fundraiser-form';
import { FundraiserProgressCard } from '@/components/dashboard/fundraiser-progress-card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';


const mockFundraisers: Fundraiser[] = [
    {
        id: 'fr_1',
        title: 'New Playground Equipment',
        description: 'Help us build a new, modern playground for the community children with the latest safety features.',
        goal: 1500000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-07-01T00:00:00Z'),
        endDate: new Date('2024-09-30T23:59:59Z'),
        status: 'Active',
    },
    {
        id: 'fr_4',
        title: 'Annual Community BBQ',
        description: 'Support our annual community get-together! Funds will go towards food, drinks, and entertainment for all residents.',
        goal: 250000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-08-01T00:00:00Z'),
        endDate: new Date('2024-08-31T23:59:59Z'),
        status: 'Active',
    },
    {
        id: 'fr_2',
        title: 'Community Garden Expansion',
        description: 'We want to add 10 new plots to the community garden and install a new irrigation system.',
        goal: 400000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-10-01T00:00:00Z'),
        endDate: new Date('2024-11-30T23:59:59Z'),
        status: 'Upcoming',
    },
    {
        id: 'fr_3',
        title: 'Clubhouse Renovation',
        description: 'Updated the clubhouse with new furniture and a fresh coat of paint.',
        goal: 750000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-03-01T00:00:00Z'),
        endDate: new Date('2024-05-31T23:59:59Z'),
        status: 'Completed',
    },
];

const mockDonations: Donation[] = [
    { id: 'd_1', fundraiserId: 'fr_1', amount: 50, currency: 'USD', donorName: 'John S.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_2', fundraiserId: 'fr_1', amount: 100, currency: 'USD', donorName: 'Olivia D.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_3', fundraiserId: 'fr_1', amount: 5000, currency: 'JMD', isAnonymous: true, timestamp: new Date() },
    { id: 'd_4', fundraiserId: 'fr_1', amount: 250, currency: 'USD', donorName: 'Michael B.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_5', fundraiserId: 'fr_1', amount: 75, currency: 'EUR', isAnonymous: true, timestamp: new Date() },
    { id: 'd_6', fundraiserId: 'fr_3', amount: 850000, currency: 'JMD', donorName: 'Community Corp.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_7', fundraiserId: 'fr_4', amount: 10000, currency: 'JMD', isAnonymous: true, timestamp: new Date() },
    { id: 'd_8', fundraiserId: 'fr_4', amount: 20, currency: 'USD', donorName: 'Aisha K.', isAnonymous: false, timestamp: new Date() },
];

export default function FundraisingPage() {
    const { user } = useAuth();
    const [fundraisers, setFundraisers] = useState<Fundraiser[]>(mockFundraisers);
    const [donations, setDonations] = useState<Donation[]>(mockDonations);
    const [isCreateOpen, setCreateOpen] = useState(false);

    const canManage = user?.role === 'System Admin' || user?.role === 'Admin';

    const handleCreateFundraiser = (newFundraiser: Omit<Fundraiser, 'id'>) => {
        const fundraiser: Fundraiser = {
            ...newFundraiser,
            id: `fr_${Date.now()}`,
        };
        setFundraisers([fundraiser, ...fundraisers]);
    };

    const handleAddDonation = (newDonation: Omit<Donation, 'id'>) => {
        const donation: Donation = {
            ...newDonation,
            id: `d_${Date.now()}`
        };
        setDonations([...donations, donation]);
    }

    const filterFundraisers = (status: Fundraiser['status']) => {
        return fundraisers.filter(f => f.status === status);
    }
    
  return (
    <div className="grid gap-8">
        <div className="flex items-center justify-between">
            <div>
                <h1 className="font-headline text-3xl font-bold">Community Fundraising</h1>
                <p className="text-muted-foreground">Support community projects through donations.</p>
            </div>
            {canManage && (
                <CreateFundraiserForm
                    open={isCreateOpen}
                    onOpenChange={setCreateOpen}
                    onCreateFundraiser={handleCreateFundraiser}
                >
                    <Button size="sm" className="gap-1">
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Create Fundraiser
                        </span>
                    </Button>
                </CreateFundraiserForm>
            )}
        </div>
        
        <Tabs defaultValue="active" className="w-full">
            <TabsList className="grid w-full grid-cols-3">
                <TabsTrigger value="active">Active</TabsTrigger>
                <TabsTrigger value="upcoming">Upcoming</TabsTrigger>
                <TabsTrigger value="completed">Completed</TabsTrigger>
            </TabsList>
            <TabsContent value="active" className="mt-4 grid gap-6 md:grid-cols-2">
                {filterFundraisers('Active').length > 0 ? filterFundraisers('Active').map(f => (
                    <FundraiserProgressCard key={f.id} fundraiser={f} donations={donations.filter(d => d.fundraiserId === f.id)} onDonate={handleAddDonation} canManage={canManage} />
                )) : <p className="text-muted-foreground col-span-2 text-center py-8">No active fundraisers.</p>}
            </TabsContent>
            <TabsContent value="upcoming" className="mt-4 grid gap-6 md:grid-cols-2">
                 {filterFundraisers('Upcoming').length > 0 ? filterFundraisers('Upcoming').map(f => (
                    <FundraiserProgressCard key={f.id} fundraiser={f} donations={[]} canManage={canManage} />
                )) : <p className="text-muted-foreground col-span-2 text-center py-8">No upcoming fundraisers.</p>}
            </TabsContent>
            <TabsContent value="completed" className="mt-4 grid gap-6 md:grid-cols-2">
                 {filterFundraisers('Completed').length > 0 ? filterFundraisers('Completed').map(f => (
                    <FundraiserProgressCard key={f.id} fundraiser={f} donations={donations.filter(d => d.fundraiserId === f.id)} canManage={canManage} />
                )) : <p className="text-muted-foreground col-span-2 text-center py-8">No completed fundraisers.</p>}
            </TabsContent>
        </Tabs>
    </div>
  );
}
