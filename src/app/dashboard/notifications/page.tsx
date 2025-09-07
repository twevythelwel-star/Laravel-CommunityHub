'use client';

import { Card, CardDescription, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { NotificationForm } from "@/components/dashboard/notification-form";
import type { Notification } from "@/types";
import { format } from "date-fns";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { useEffect, useState } from "react";

const mockNotifications: Notification[] = [
    {
        id: '1',
        title: 'Community Pool Maintenance',
        content: 'The community pool will be closed for maintenance from July 1st to July 3rd. We apologize for any inconvenience.',
        author: 'Admin',
        timestamp: new Date('2023-06-28T00:00:00Z')
    },
    {
        id: '2',
        title: 'Annual HOA Meeting',
        content: 'Please join us for the annual Homeowners Association meeting on July 15th at 7 PM in the clubhouse.',
        author: 'Admin',
        timestamp: new Date('2023-06-25T00:00:00Z')
    }
]

function ClientFormattedDate({ date }: { date: Date }) {
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  return (
    <p className="text-sm text-muted-foreground">
      {isClient ? format(date, 'MMM d, yyyy') : '...'}
    </p>
  );
}

export default function NotificationsPage() {
  return (
    <div className="grid gap-8">
        <div>
          <h1 className="font-headline text-3xl font-bold">Notifications</h1>
          <p className="text-muted-foreground">
            Send and view community-wide announcements.
          </p>
        </div>
        <Card>
            <CardHeader>
                <CardTitle>Create New Announcement</CardTitle>
                <CardDescription>System Admins can use AI to generate targeted notifications from a larger document.</CardDescription>
            </CardHeader>
            <CardContent>
                <NotificationForm />
            </CardContent>
        </Card>

         <Card>
            <CardHeader>
                <CardTitle>Recent Announcements</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {mockNotifications.map(notification => (
                    <div key={notification.id} className="flex gap-4">
                        <Avatar>
                            <AvatarImage src={`https://picsum.photos/40?q=${notification.id}`} data-ai-hint="person avatar" />
                            <AvatarFallback>A</AvatarFallback>
                        </Avatar>
                        <div className="flex-1">
                            <div className="flex justify-between">
                                <p className="font-semibold">{notification.title}</p>
                                <ClientFormattedDate date={notification.timestamp} />
                            </div>
                            <p className="text-muted-foreground mt-1">{notification.content}</p>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    </div>
  );
}
