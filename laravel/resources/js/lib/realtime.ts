import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { router } from '@inertiajs/react';
import { toast } from '@/hooks/use-toast';

/** Sent by HandleInertiaRequests; null when Reverb is not the broadcaster. */
export type RealtimeConfig = {
  key: string;
  host: string;
  port: number;
  scheme: 'http' | 'https';
  userChannel: string;
  gateFeed: boolean;
};

type InboxMessageEvent = { id: number; kind: string; title: string; unread: number };
type SecurityAlertEvent = { title: string };
type VisitorArrivedEvent = { visitor_name: string; entry_gate: string; homeowner_name: string | null };

let echo: Echo<'reverb'> | null = null;

/**
 * Connect once per tab and join this user's channels. Safe to call on every
 * page: the layout remounts on each navigation, and re-joining each time would
 * churn subscriptions. The server decides who may join what (routes/channels.php).
 */
export function startRealtime(config: RealtimeConfig | null | undefined): void {
  if (!config || echo) {
    return;
  }

  (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
  const csrf = document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

  echo = new Echo({
    broadcaster: 'reverb',
    key: config.key,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: config.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: { headers: { 'X-CSRF-TOKEN': csrf } },
  });

  // Personal inbox: refresh the badge (and the list, when it is on screen).
  echo.private(config.userChannel).listen('.inbox.message', (e: InboxMessageEvent) => {
    toast({ title: 'New message', description: e.title });
    const onInbox = window.location.pathname === '/dashboard/inbox';
    router.reload({ only: onInbox ? ['inboxUnread', 'messages'] : ['inboxUnread'] });
  });

  // Estate safety alerts: bring up the banner everyone sees.
  echo.private('community-alerts').listen('.security.alert', (e: SecurityAlertEvent) => {
    toast({ variant: 'destructive', title: 'Safety alert', description: e.title });
    router.reload({ only: ['activeAlert'] });
  });

  // Every arrival, for gate staff only (the server refuses anyone else).
  if (config.gateFeed) {
    echo.private('gatehouse-stream').listen('.visitor.checked-in', (e: VisitorArrivedEvent) => {
      toast({
        title: `Arrived at ${e.entry_gate}`,
        description: e.homeowner_name ? `${e.visitor_name}, visiting ${e.homeowner_name}` : e.visitor_name,
      });
    });
  }
}
