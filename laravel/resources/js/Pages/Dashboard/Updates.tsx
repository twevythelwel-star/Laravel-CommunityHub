

import { useState } from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { PlusCircle } from "lucide-react";
import type { CommunityUpdate } from "@/types";
import { useAuth } from '@/context/auth-context';
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { CommunityUpdateForm } from '@/components/dashboard/community-update-form';

const mockUpdates: CommunityUpdate[] = [
    { 
        id: 'cu_1', 
        title: 'Q2 2024 HOA Board Meeting Summary', 
        date: new Date('2024-06-25T00:00:00Z'),
        summary: 'The board approved the budget for the new playground. Construction is set to begin in September. A new proposal for community garden expansion was discussed and will be voted on next quarter.'
    },
    { 
        id: 'cu_2', 
        title: 'Annual Summer BBQ Roundup', 
        date: new Date('2024-08-15T00:00:00Z'),
        summary: 'A fantastic turnout for our annual BBQ! Over 200 residents attended. A big thank you to the volunteers and everyone who brought a side dish. The new grill was a huge success!'
    },
     { 
        id: 'cu_3', 
        title: 'Security System Upgrade Town Hall', 
        date: new Date('2024-05-10T00:00:00Z'),
        summary: 'Discussion was held regarding the proposed upgrade to a new RFID entry system. Feedback from residents was collected, and the security committee will present a revised plan based on the input.'
    },
];

export default function UpdatesPage() {
    const { user } = useAuth();
    const [updates, setUpdates] = useState<CommunityUpdate[]>(mockUpdates);
    const [isFormOpen, setFormOpen] = useState(false);

    const canManage = user?.role === 'System Admin' || user?.role === 'Admin';

    const handleSave = (data: Omit<CommunityUpdate, 'id'>) => {
        const newUpdate: CommunityUpdate = {
            id: `cu_${Date.now()}`,
            ...data,
        };
        setUpdates([newUpdate, ...updates]);
    };

  return (
    <DashboardLayout>
      <Head title="Community Updates" />
      <div className="grid gap-8 max-w-7xl mx-auto pb-12">
        <div className="flex items-center justify-between">
            <div>
                <h1 className="font-headline text-3xl font-bold">Community Updates</h1>
                <p className="text-muted-foreground">Summaries of recent meetings and community activities.</p>
            </div>
             {canManage && (
                <CommunityUpdateForm
                    open={isFormOpen}
                    onOpenChange={setFormOpen}
                    onSave={handleSave}
                >
                    <Button size="sm" className="gap-1">
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Add Update
                        </span>
                    </Button>
                </CommunityUpdateForm>
             )}
        </div>
        
        <div className="space-y-6">
            {updates.map(update => (
                 <Card key={update.id}>
                    <CardHeader>
                        <CardTitle>{update.title}</CardTitle>
                        <CardDescription>
                           Meeting/Activity Date: <ClientFormattedDate date={update.date} formatString="MMMM d, yyyy" />
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground whitespace-pre-wrap">{update.summary}</p>
                    </CardContent>
                </Card>
            ))}
        </div>
      </div>
    </DashboardLayout>
  );
}
