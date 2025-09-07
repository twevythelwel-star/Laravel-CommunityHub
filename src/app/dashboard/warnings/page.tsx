
'use client';

import { WarningList } from "@/components/dashboard/warning-list";
import { WarningForm } from "@/components/dashboard/warning-form";
import type { Warning } from "@/types";

const mockWarnings: Warning[] = [
    {
        id: '1',
        title: 'Suspicious Vehicle Reported',
        description: 'A black sedan with no license plate has been seen circling Lot B. Please be cautious.',
        author: 'Jane Doe',
        timestamp: new Date('2024-07-29T10:00:00Z'),
        confirms: 2,
        denies: 0,
        userStatus: null,
    },
    {
        id: '2',
        title: 'Lost Golden Retriever',
        description: 'Our dog, "Buddy", went missing near the park. He is very friendly and has a blue collar.',
        author: 'John Smith',
        timestamp: new Date('2024-07-29T08:30:00Z'),
        confirms: 5,
        denies: 1,
        userStatus: 'confirmed',
    },
]

export default function WarningsPage() {
  return (
    <div className="flex flex-col gap-8">
      <div className="flex items-center">
        <div className="flex-1">
          <h1 className="font-headline text-3xl font-bold">Community Warnings</h1>
          <p className="text-muted-foreground">Send and validate urgent alerts within the community.</p>
        </div>
        <WarningForm />
      </div>
      
      <WarningList initialWarnings={mockWarnings} />

    </div>
  );
}
