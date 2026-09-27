import { Head, Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ClientFormattedDistanceToNow } from '@/components/client-formatted-date';
import { CalendarX, DoorOpen, Inbox as InboxIcon, Mail } from 'lucide-react';
import { cn } from '@/lib/utils';

type InboxMessage = {
  id: number;
  kind: string;
  title: string;
  body: string;
  actionUrl: string | null;
  read: boolean;
  receivedAt: string;
};

type Props = { messages: InboxMessage[] };

const ICONS: Record<string, typeof Mail> = {
  visitor_checked_in: DoorOpen,
  amenity_booking_cancelled: CalendarX,
};

const markRead = (message: InboxMessage) => {
  if (!message.read) {
    router.post(`/dashboard/inbox/${message.id}/read`, {}, { preserveScroll: true, preserveState: true });
  }
};

export default function InboxPage({ messages }: Props) {
  const unread = messages.filter((m) => !m.read).length;

  return (
    <DashboardLayout>
      <Head title="Inbox" />
      <div className="grid gap-6 max-w-3xl mx-auto pb-12">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="font-headline text-3xl font-bold">Inbox</h1>
            <p className="text-muted-foreground">Messages for you alone. Estate-wide notices are under Notifications.</p>
          </div>
          {unread > 0 && (
            <Button
              size="sm"
              variant="outline"
              onClick={() => router.post('/dashboard/inbox/read-all', {}, { preserveScroll: true })}
            >
              Mark all as read
            </Button>
          )}
        </div>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{unread > 0 ? `${unread} unread` : 'All caught up'}</CardTitle>
            <CardDescription>Showing your most recent messages.</CardDescription>
          </CardHeader>
          <CardContent className="p-0">
            {messages.length === 0 ? (
              <div className="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
                <InboxIcon className="h-8 w-8" />
                <p>No messages yet. You’ll hear here when a visitor arrives or a booking changes.</p>
              </div>
            ) : (
              <ul className="divide-y">
                {messages.map((message) => {
                  const Icon = ICONS[message.kind] ?? Mail;
                  return (
                    <li
                      key={message.id}
                      className={cn('flex gap-3 px-6 py-4', !message.read && 'bg-primary/5')}
                      onClick={() => markRead(message)}
                    >
                      <Icon className={cn('mt-0.5 h-5 w-5 shrink-0', message.read ? 'text-muted-foreground' : 'text-primary')} />
                      <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex items-start justify-between gap-3">
                          <p className={cn('text-sm', message.read ? 'text-foreground' : 'font-semibold text-foreground')}>
                            {!message.read && <span className="sr-only">Unread: </span>}
                            {message.title}
                          </p>
                          <span className="shrink-0 text-xs text-muted-foreground">
                            <ClientFormattedDistanceToNow date={message.receivedAt} />
                          </span>
                        </div>
                        <p className="text-sm text-muted-foreground break-words">{message.body}</p>
                        <div className="flex items-center gap-3 pt-1">
                          {message.actionUrl && (
                            <Link href={message.actionUrl} className="text-xs font-medium text-primary hover:underline">
                              View details
                            </Link>
                          )}
                          {!message.read && (
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation();
                                markRead(message);
                              }}
                              className="text-xs text-muted-foreground hover:text-foreground hover:underline"
                            >
                              Mark as read
                            </button>
                          )}
                        </div>
                      </div>
                    </li>
                  );
                })}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  );
}
