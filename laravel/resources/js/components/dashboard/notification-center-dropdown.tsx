import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Bell, CheckCheck, Megaphone, AlertTriangle, Info, ExternalLink } from 'lucide-react';
import { cn } from '@/lib/utils';
import { ClientFormattedDate, ClientFormattedDistanceToNow } from '@/components/client-formatted-date';

type InAppNotificationItem = {
  id: number;
  title: string;
  body: string;
  category: string;
  priority: string;
  read: boolean;
  actionUrl: string | null;
  createdAt: string;
};

export function NotificationCenterDropdown() {
  const { props } = usePage();
  const inApp = (props as Record<string, any>).inAppNotifications;
  const unreadCount: number = inApp?.unreadCount ?? 0;
  const recent: InAppNotificationItem[] = inApp?.recent ?? [];

  const [loadingId, setLoadingId] = useState<number | null>(null);

  const handleMarkAllRead = () => {
    router.post('/in-app-notifications/read-all', {}, { preserveScroll: true });
  };

  const handleItemClick = (item: InAppNotificationItem) => {
    if (!item.read) {
      setLoadingId(item.id);
      router.post(
        `/in-app-notifications/${item.id}/read`,
        {},
        {
          preserveScroll: true,
          onFinish: () => {
            setLoadingId(null);
            if (item.actionUrl) {
              router.visit(item.actionUrl);
            }
          },
        }
      );
    } else if (item.actionUrl) {
      router.visit(item.actionUrl);
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative h-9 w-9 text-muted-foreground hover:text-foreground"
          aria-label={`Notifications (${unreadCount} unread)`}
        >
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-destructive-foreground animate-pulse shadow-sm">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-80 sm:w-96 p-0 shadow-lg border rounded-xl">
        <div className="flex items-center justify-between px-4 py-3 border-b bg-muted/30">
          <div className="flex items-center gap-2">
            <Bell className="h-4 w-4 text-primary" />
            <span className="font-semibold text-sm">Notification Centre</span>
            {unreadCount > 0 && (
              <Badge variant="secondary" className="text-[11px] h-5 px-1.5 font-medium">
                {unreadCount} new
              </Badge>
            )}
          </div>
          {unreadCount > 0 && (
            <Button
              variant="ghost"
              size="sm"
              onClick={handleMarkAllRead}
              className="h-7 text-xs text-muted-foreground hover:text-foreground flex items-center gap-1 px-2"
              title="Mark all as read"
            >
              <CheckCheck className="h-3.5 w-3.5" />
              <span>Mark all read</span>
            </Button>
          )}
        </div>

        <div className="max-h-80 overflow-y-auto divide-y divide-border/40">
          {recent.length === 0 ? (
            <div className="py-8 text-center text-muted-foreground text-sm flex flex-col items-center gap-1.5">
              <Bell className="h-8 w-8 text-muted-foreground/40 stroke-[1.5]" />
              <p>No new notifications</p>
              <span className="text-xs text-muted-foreground/70">You're completely up to date.</span>
            </div>
          ) : (
            recent.map((item) => {
              const isUrgent = item.priority === 'urgent' || item.priority === 'high';
              const Icon = isUrgent ? AlertTriangle : item.category === 'announcement' ? Megaphone : Info;

              return (
                <div
                  key={item.id}
                  onClick={() => handleItemClick(item)}
                  className={cn(
                    'p-3.5 text-left transition-colors cursor-pointer hover:bg-muted/50 flex gap-3 items-start',
                    !item.read ? 'bg-primary/5 font-medium' : 'opacity-85'
                  )}
                >
                  <div
                    className={cn(
                      'p-1.5 rounded-full shrink-0 mt-0.5',
                      isUrgent
                        ? 'bg-destructive/15 text-destructive'
                        : 'bg-primary/10 text-primary'
                    )}
                  >
                    <Icon className="h-4 w-4" />
                  </div>
                  <div className="flex-1 min-w-0 space-y-1">
                    <div className="flex items-center justify-between gap-2">
                      <p className={cn('text-xs truncate font-semibold', !item.read ? 'text-foreground' : 'text-muted-foreground')}>
                        {item.title}
                      </p>
                      {!item.read && (
                        <span className="h-2 w-2 rounded-full bg-primary shrink-0" />
                      )}
                    </div>
                    <p className="text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                      {item.body}
                    </p>
                    <div className="flex items-center justify-between pt-0.5 text-[10px] text-muted-foreground/75">
                      <ClientFormattedDistanceToNow date={item.createdAt} />
                      {item.actionUrl && (
                        <span className="flex items-center gap-0.5 hover:text-primary">
                          View <ExternalLink className="h-2.5 w-2.5" />
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              );
            })
          )}
        </div>

        <DropdownMenuSeparator className="m-0" />
        <div className="p-2 text-center bg-muted/20">
          <Link
            href="/dashboard/notifications"
            className="text-xs text-primary hover:underline font-medium inline-flex items-center gap-1 py-1"
          >
            Open Community Notice Board & Announcements &rarr;
          </Link>
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
