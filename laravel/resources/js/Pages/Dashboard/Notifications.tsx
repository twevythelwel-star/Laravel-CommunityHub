import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Bell } from 'lucide-react';
import { NotificationForm } from '@/components/dashboard/notification-form';
import { ClientFormattedDate } from '@/components/client-formatted-date';

/**
 * Community notice board.
 *
 * The list was two hardcoded objects and the composer logged to the console, so
 * neither half of this page did anything. Both now go through
 * NotificationController, and the notices a viewer receives are already
 * filtered by Notification::forRole() — a notice addressed to Security is not
 * sent to a Homeowner's browser and then hidden.
 */

type NotificationRow = {
  id: number;
  title: string;
  content: string;
  author: string;
  timestamp: string;
  targetRoles: string[] | null;
};

type Paginated<T> = {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
  total: number;
  from: number | null;
  to: number | null;
};

type Props = {
  notifications: Paginated<NotificationRow>;
  canBroadcast: boolean;
  roles: string[];
  community: string;
  aiEnabled: boolean;
};

/** First letter of the author's display name, for the avatar fallback. */
function initial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || '?';
}

export default function NotificationsPage({
  notifications,
  canBroadcast,
  roles,
  community,
  aiEnabled,
}: Props) {
  return (
    <DashboardLayout>
      <Head title="Notifications" />

      <div className="grid gap-8">
        <div>
          <h1 className="font-headline text-3xl font-bold">Notifications</h1>
          <p className="text-muted-foreground">
            Send and view community-wide announcements.
          </p>
        </div>

        {/*
          The composer was visible to everyone who could reach the page. Only
          `broadcastNotices` holders can publish, and the route enforces it —
          so showing the form to anybody else offered a button that 403s.
        */}
        {canBroadcast && (
          <Card>
            <CardHeader>
              <CardTitle>Create New Announcement</CardTitle>
              <CardDescription>
                Summarise a longer document with AI, choose who should receive the notice, and
                publish it to {community}.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <NotificationForm roles={roles} community={community} aiEnabled={aiEnabled} />
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle>Recent Announcements</CardTitle>
            <CardDescription>
              Notices addressed to you.
              {notifications.total > 0 &&
                ` Showing ${notifications.from}–${notifications.to} of ${notifications.total}.`}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {notifications.data.length === 0 && (
              <div className="flex flex-col items-center gap-2 py-10 text-center text-muted-foreground">
                <Bell className="h-8 w-8" />
                <p className="text-sm">No announcements yet.</p>
              </div>
            )}

            {notifications.data.map((notification) => (
              <div key={notification.id} className="flex gap-4">
                <Avatar>
                  <AvatarFallback>{initial(notification.author)}</AvatarFallback>
                </Avatar>
                <div className="flex-1">
                  <div className="flex flex-wrap justify-between gap-2">
                    <p className="font-semibold">{notification.title}</p>
                    <ClientFormattedDate
                      date={notification.timestamp}
                      formatString="MMM d, yyyy"
                    />
                  </div>
                  <p className="text-muted-foreground mt-1 whitespace-pre-line">
                    {notification.content}
                  </p>
                  <div className="mt-2 flex flex-wrap items-center gap-2">
                    <span className="text-xs text-muted-foreground">
                      Posted by {notification.author}
                    </span>
                    {/*
                      Only worth showing to someone who can publish: a resident
                      sees a notice because it was addressed to them, and the
                      rest of the distribution list is not their business.
                    */}
                    {canBroadcast &&
                      (notification.targetRoles?.length
                        ? notification.targetRoles.map((role) => (
                            <Badge key={role} variant="secondary" className="text-xs">
                              {role}
                            </Badge>
                          ))
                        : (
                          <Badge variant="outline" className="text-xs">
                            Everyone
                          </Badge>
                        ))}
                  </div>
                </div>
              </div>
            ))}

            {notifications.links.length > 3 && (
              <nav
                className="flex flex-wrap items-center justify-center gap-1 pt-4"
                aria-label="Pagination"
              >
                {notifications.links.map((link, index) => (
                  <Button
                    key={index}
                    size="sm"
                    variant={link.active ? 'default' : 'outline'}
                    disabled={!link.url}
                    onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                    className="h-8 min-w-8 px-2 text-xs"
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </nav>
            )}
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  );
}
