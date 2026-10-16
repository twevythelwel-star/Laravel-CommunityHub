import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  Bell,
  CheckCircle2,
  AlertTriangle,
  ShieldCheck,
  Mail,
  MessageSquare,
  Smartphone,
  Eye,
  Loader2,
  Info,
} from 'lucide-react';
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { useToast } from '@/hooks/use-toast';
import type { NotificationRow, Paginated } from './notification-types';

function initial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || '?';
}

export function PublishedNoticesCard({
  notifications,
  canBroadcast,
}: {
  notifications: Paginated<NotificationRow>;
  canBroadcast: boolean;
}) {
  const { toast } = useToast();
  const [markingReadId, setMarkingReadId] = useState<number | null>(null);
  const [acknowledgingNotice, setAcknowledgingNotice] = useState<NotificationRow | null>(null);
  const [ackNote, setAckNote] = useState<string>('');
  const [isSubmittingAck, setIsSubmittingAck] = useState<boolean>(false);

  const handleMarkAsRead = (id: number) => {
    setMarkingReadId(id);
    router.post(
      `/dashboard/notifications/${id}/read`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Notice Marked as Read',
            description: 'Your verified read receipt has been registered.',
          });
        },
        onError: () => {
          toast({
            variant: 'destructive',
            title: 'Error',
            description: 'Could not register read receipt. Please try again.',
          });
        },
        onFinish: () => setMarkingReadId(null),
      }
    );
  };

  const handleSubmitAcknowledgement = (e: React.FormEvent) => {
    e.preventDefault();
    if (!acknowledgingNotice) return;

    setIsSubmittingAck(true);
    router.post(
      `/dashboard/notifications/${acknowledgingNotice.id}/acknowledge`,
      { note: ackNote },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Acknowledgement Confirmed',
            description: `You have formally acknowledged "${acknowledgingNotice.title}".`,
          });
          setAcknowledgingNotice(null);
          setAckNote('');
        },
        onError: (errors) => {
          toast({
            variant: 'destructive',
            title: 'Could not submit acknowledgement',
            description: Object.values(errors)[0] ?? 'An error occurred.',
          });
        },
        onFinish: () => setIsSubmittingAck(false),
      }
    );
  };

  return (
    <>
      <Card className="border shadow-sm">
        <CardHeader>
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <CardTitle className="text-lg flex items-center gap-2">
                <Bell className="h-5 w-5 text-primary" />
                <span>Community Announcements & Notices</span>
              </CardTitle>
              <CardDescription>
                Notices and broadcasts addressed to you and your community.
                {notifications.total > 0 &&
                  ` Showing ${notifications.from}–${notifications.to} of ${notifications.total}.`}
              </CardDescription>
            </div>
            <div className="text-xs text-muted-foreground flex items-center gap-1.5 bg-muted/40 px-3 py-1.5 rounded-lg border">
              <Info className="h-3.5 w-3.5 text-primary shrink-0" />
              <span>Reading a notice records delivery receipt; explicit acknowledgement is recorded separately.</span>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-6">
          {notifications.data.length === 0 && (
            <div className="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
              <Bell className="h-9 w-9 text-muted-foreground/60" />
              <p className="text-sm font-medium">No published announcements found.</p>
            </div>
          )}

          {notifications.data.map((notification) => (
            <div
              key={notification.id}
              className={`flex gap-4 p-5 rounded-xl border transition-colors ${
                notification.requiresAcknowledgement && !notification.isAcknowledged
                  ? 'border-amber-500/40 bg-amber-500/[0.03]'
                  : 'bg-card/60'
              }`}
            >
              <Avatar className="h-10 w-10 shrink-0">
                <AvatarFallback className="bg-primary/10 text-primary font-bold text-sm">
                  {initial(notification.author)}
                </AvatarFallback>
              </Avatar>

              <div className="flex-1 space-y-3">
                {/* Notice Top Header */}
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <h4 className="font-semibold text-base text-foreground">{notification.title}</h4>
                    {notification.category && (
                      <Badge variant="outline" className="text-[10px] uppercase font-mono capitalize">
                        {notification.category}
                      </Badge>
                    )}
                    {notification.priority && (
                      <Badge
                        variant="outline"
                        className={`text-[10px] font-mono capitalize ${
                          notification.priority === 'urgent' || notification.priority === 'critical'
                            ? 'border-destructive/40 text-destructive bg-destructive/10'
                            : 'text-muted-foreground'
                        }`}
                      >
                        {notification.priority}
                      </Badge>
                    )}
                  </div>

                  <div className="flex items-center gap-2">
                    {/* Read Status Badge or Action */}
                    {notification.isRead ? (
                      <Badge variant="outline" className="text-[10px] border-sky-500/40 text-sky-600 bg-sky-500/10 flex items-center gap-1">
                        <CheckCircle2 className="h-3 w-3 text-sky-600" />
                        <span>Read</span>
                        {notification.readAt && (
                          <span className="text-[9px] text-sky-700/80 font-normal">
                            (<ClientFormattedDate date={notification.readAt} formatString="MMM d, h:mm a" />)
                          </span>
                        )}
                      </Badge>
                    ) : (
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleMarkAsRead(notification.id)}
                        disabled={markingReadId === notification.id}
                        className="h-6 text-[11px] px-2 text-muted-foreground hover:text-foreground flex items-center gap-1 border border-border/60"
                        title="Mark as read without formal acknowledgement"
                      >
                        {markingReadId === notification.id ? (
                          <Loader2 className="h-3 w-3 animate-spin" />
                        ) : (
                          <Eye className="h-3 w-3" />
                        )}
                        <span>Mark as Read</span>
                      </Button>
                    )}

                    <span className="text-xs text-muted-foreground font-mono">
                      <ClientFormattedDate
                        date={notification.timestamp}
                        formatString="MMM d, yyyy h:mm a"
                      />
                    </span>
                  </div>
                </div>

                {/* Content */}
                <p className="text-sm text-foreground/90 whitespace-pre-line leading-relaxed">
                  {notification.content}
                </p>

                {/* Mandatory Acknowledgement Banner */}
                {notification.requiresAcknowledgement && (
                  <div className="pt-1">
                    {notification.isAcknowledged ? (
                      <div className="p-3.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 flex items-start gap-3">
                        <ShieldCheck className="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" />
                        <div className="text-xs space-y-1">
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold text-emerald-950 dark:text-emerald-200">
                              Formally Acknowledged
                            </span>
                            {notification.acknowledgedAt && (
                              <span className="text-[11px] text-emerald-700 dark:text-emerald-400">
                                on <ClientFormattedDate date={notification.acknowledgedAt} formatString="MMM d, yyyy h:mm a" />
                              </span>
                            )}
                          </div>
                          {notification.acknowledgementNote && (
                            <p className="text-[11px] text-emerald-800 dark:text-emerald-300 italic">
                              "{notification.acknowledgementNote}"
                            </p>
                          )}
                          <p className="text-[10px] text-emerald-700/80 dark:text-emerald-400/85">
                            Your acknowledgement receipt is officially recorded for community compliance auditing.
                          </p>
                        </div>
                      </div>
                    ) : (
                      <div className="p-3.5 rounded-xl border border-amber-500/40 bg-amber-500/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-start gap-2.5">
                          <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0 mt-0.5" />
                          <div className="text-xs space-y-0.5">
                            <div className="font-semibold text-amber-950 dark:text-amber-200">
                              Action Required: Notice Requires Formal Acknowledgement
                            </div>
                            <p className="text-[11px] text-amber-900/80 dark:text-amber-300/80 leading-relaxed">
                              Reading this notice records delivery, but does not indicate your agreement or formal acknowledgement.
                              {notification.acknowledgementDeadline && (
                                <> Acknowledgement deadline: <strong className="text-amber-950 dark:text-amber-200"><ClientFormattedDate date={notification.acknowledgementDeadline} formatString="MMM d, yyyy h:mm a" /></strong>.</>
                              )}
                            </p>
                          </div>
                        </div>

                        <Button
                          size="sm"
                          onClick={() => {
                            setAcknowledgingNotice(notification);
                            setAckNote('');
                          }}
                          className="h-8 text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shrink-0 flex items-center gap-1.5 shadow-sm"
                        >
                          <ShieldCheck className="h-3.5 w-3.5" />
                          <span>Acknowledge Notice</span>
                        </Button>
                      </div>
                    )}
                  </div>
                )}

                {/* Notice Footer Meta */}
                <div className="mt-2 flex flex-wrap items-center gap-2 pt-2 border-t border-border/50">
                  <span className="text-xs text-muted-foreground font-medium">
                    By {notification.author}
                  </span>

                  {notification.targetType && (
                    <Badge variant="outline" className="text-[10px] uppercase font-mono">
                      {notification.targetType.replace('_', ' ')}
                    </Badge>
                  )}

                  {canBroadcast &&
                    (notification.targetRoles?.length ? (
                      notification.targetRoles.map((role) => (
                        <Badge key={role} variant="secondary" className="text-[10px]">
                          {role}
                        </Badge>
                      ))
                    ) : (
                      <Badge variant="outline" className="text-[10px]">
                        Everyone
                      </Badge>
                    ))}

                  {notification.channels && notification.channels.length > 0 && (
                    <div className="flex items-center gap-1.5 ml-auto">
                      {notification.channels.map((ch) => (
                        <span
                          key={ch}
                          title={`Delivered via ${ch.toUpperCase()}`}
                          className="p-1 rounded bg-muted/60 text-muted-foreground text-[10px] flex items-center gap-1"
                        >
                          {ch === 'email' && <Mail className="h-3 w-3 text-sky-500" />}
                          {ch === 'sms' && <Smartphone className="h-3 w-3 text-emerald-500" />}
                          {ch === 'whatsapp' && <MessageSquare className="h-3 w-3 text-green-600" />}
                          {ch === 'in_app' && <Bell className="h-3 w-3 text-primary" />}
                          <span className="capitalize">{ch.replace('_', ' ')}</span>
                        </span>
                      ))}
                    </div>
                  )}
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

      {/* Formal Acknowledgement Modal Dialog */}
      <Dialog
        open={acknowledgingNotice !== null}
        onOpenChange={(open) => {
          if (!open) {
            setAcknowledgingNotice(null);
            setAckNote('');
          }
        }}
      >
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-base">
              <ShieldCheck className="h-5 w-5 text-indigo-600" />
              <span>Acknowledge Notice</span>
            </DialogTitle>
            <DialogDescription className="text-xs">
              Confirm that you have received and reviewed this community announcement.
            </DialogDescription>
          </DialogHeader>

          {acknowledgingNotice && (
            <form onSubmit={handleSubmitAcknowledgement} className="space-y-4 pt-1">
              <div className="p-3 rounded-lg border bg-muted/30 space-y-1">
                <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                  Notice Details
                </span>
                <h5 className="font-semibold text-sm text-foreground">{acknowledgingNotice.title}</h5>
                <p className="text-xs text-muted-foreground line-clamp-3">
                  {acknowledgingNotice.content}
                </p>
              </div>

              <div className="p-3 rounded-lg border border-indigo-500/30 bg-indigo-500/10 flex items-start gap-2.5 text-xs">
                <Info className="h-4 w-4 text-indigo-600 shrink-0 mt-0.5" />
                <div className="space-y-1 text-indigo-950 dark:text-indigo-200">
                  <p className="leading-relaxed">
                    By submitting this acknowledgement, you confirm that you have read and understood the instructions in this notice.
                  </p>
                  <p className="text-[10px] text-indigo-800/80 dark:text-indigo-300/80 italic">
                    Ordinary read receipts only verify delivery; this step enters a signed acknowledgement into community records.
                  </p>
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-medium text-foreground">
                  Optional Note or Remarks
                </label>
                <Textarea
                  placeholder="e.g., Unit 14B aware and prepared; valves confirmed closed."
                  value={ackNote}
                  onChange={(e) => setAckNote(e.target.value)}
                  className="text-xs min-h-[70px] resize-none"
                  maxLength={500}
                />
                <span className="text-[10px] text-muted-foreground block text-right">
                  {ackNote.length} / 500 characters
                </span>
              </div>

              <DialogFooter className="gap-2 sm:gap-0 pt-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setAcknowledgingNotice(null)}
                  disabled={isSubmittingAck}
                  className="h-8 text-xs"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingAck}
                  className="h-8 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1.5 shadow-sm"
                >
                  {isSubmittingAck ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <ShieldCheck className="h-3.5 w-3.5" />
                  )}
                  <span>{isSubmittingAck ? 'Recording...' : 'Submit Acknowledgement'}</span>
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}
