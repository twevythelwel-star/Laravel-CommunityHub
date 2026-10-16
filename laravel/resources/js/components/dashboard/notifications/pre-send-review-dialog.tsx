import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  ShieldCheck,
  Calendar,
  Clock,
  CheckCircle2,
  Loader2,
  Send,
  AlertTriangle,
  Check,
} from 'lucide-react';
import type { AudiencePreview, NotificationFormValues } from './notification-form-schema';

type PreSendReviewDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  values: NotificationFormValues | null;
  community: string;
  preview: AudiencePreview | null;
  isSubmitting: boolean;
  onConfirm: (values: NotificationFormValues) => void;
};

/** The exact message, recipient count and warnings, confirmed before anything is sent. */
export function PreSendReviewDialog({
  open,
  onOpenChange,
  values,
  community,
  preview,
  isSubmitting,
  onConfirm,
}: PreSendReviewDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="text-lg flex items-center gap-2">
            <ShieldCheck className="h-5 w-5 text-primary" />
            <span>Review Announcement Before Dispatch</span>
          </DialogTitle>
          <DialogDescription className="text-xs">
            Inspect the exact rendered message and verified recipient count before broadcasting to the community.
          </DialogDescription>
        </DialogHeader>

        {values && (
          <div className="space-y-4 py-2 text-xs">
            {/* Message Preview Box */}
            <div className="p-4 rounded-xl border bg-muted/30 space-y-2">
              <div className="flex items-center justify-between gap-2">
                <span className="font-bold text-sm text-foreground">{values.title}</span>
                <div className="flex items-center gap-1.5 shrink-0">
                  <Badge variant="outline" className="text-[10px] uppercase font-mono capitalize">
                    {values.category}
                  </Badge>
                  <Badge variant="outline" className="text-[10px] font-mono capitalize">
                    {values.priority}
                  </Badge>
                </div>
              </div>
              <div className="text-xs text-muted-foreground whitespace-pre-line leading-relaxed max-h-48 overflow-y-auto border-t pt-2 mt-2">
                {values.content}
              </div>
            </div>

            {/* Target & Recipient Stats */}
            <div className="grid grid-cols-2 gap-3">
              <div className="p-3 rounded-lg border bg-card">
                <span className="text-[11px] font-medium text-muted-foreground uppercase">Target Audience</span>
                <div className="font-semibold text-sm capitalize mt-0.5">
                  {values.targetType.replace('_', ' ')}
                </div>
                <span className="text-[10px] text-muted-foreground">
                  {values.targetStreet || values.targetBlock || values.targetSection || community}
                </span>
              </div>

              <div className="p-3 rounded-lg border bg-primary/5 border-primary/20">
                <span className="text-[11px] font-medium text-primary uppercase">Intended Recipients</span>
                <div className="font-bold text-xl text-primary mt-0.5">
                  {preview?.uniqueRecipients ?? 0}
                </div>
                <span className="text-[10px] text-muted-foreground">
                  Deduplicated resident recipients
                </span>
              </div>
            </div>

            {/* Deduplication & Overlap Check */}
            {preview?.hasOverlaps ? (
              <div className="p-3 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300 text-[11px]">
                <strong>Overlapping Selections ({preview.overlapCount} overlaps):</strong> Active deduplication ensures overlapping boundaries are merged so each resident receives exactly one notification.
              </div>
            ) : (
              <div className="p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 text-[11px] flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                <span>Clean targeting boundaries. Zero overlapping recipient duplicates detected.</span>
              </div>
            )}

            {/* Channels List */}
            <div className="flex items-center gap-2 pt-1">
              <span className="text-muted-foreground">Selected Channels:</span>
              <div className="flex flex-wrap gap-1">
                {values.channels.map((ch) => (
                  <Badge key={ch} variant="secondary" className="text-[10px] uppercase font-mono">
                    {ch.replace('_', ' ')}
                  </Badge>
                ))}
              </div>
            </div>

            {/* Urgent Priority / Emergency Disclaimer */}
            {(values.priority === 'critical' || values.category === 'emergency') && (
              <div className="p-3 rounded-lg border border-destructive/40 bg-destructive/10 text-destructive text-[11px] space-y-1">
                <div className="font-bold flex items-center gap-1.5">
                  <AlertTriangle className="h-4 w-4" />
                  <span>Emergency Priority Dispatch Warning</span>
                </div>
                <p>
                  Emergency notices initiate multi-channel delivery across push, in-app, SMS, and email. Best-effort delivery is subject to carrier latency and recipient device connectivity; guaranteed delivery cannot be claimed.
                </p>
              </div>
            )}

            {/* Scheduled / Expiry Notice */}
            {values.isScheduled && (
              <div className="p-2.5 rounded-lg border bg-amber-500/10 border-amber-500/20 text-amber-800 dark:text-amber-300 text-[11px] flex items-center gap-2">
                <Clock className="h-4 w-4 shrink-0" />
                <span>
                  Will be queued for dispatch at <strong>{values.scheduledFor}</strong> in community timezone. Requires active Laravel scheduler.
                </span>
              </div>
            )}

            {values.expiresAt && (
              <div className="p-2.5 rounded-lg border bg-muted/40 text-muted-foreground text-[11px] flex items-center gap-2">
                <Calendar className="h-4 w-4 shrink-0" />
                <span>Notice automatically expires and hides from resident feeds on <strong>{values.expiresAt}</strong>.</span>
              </div>
            )}

            {values.requiresAcknowledgement && (
              <div className="p-2.5 rounded-lg border bg-emerald-500/10 border-emerald-500/20 text-emerald-800 dark:text-emerald-300 text-[11px] flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                <span>
                  <strong>Acknowledgement Tracking Active:</strong> Residents will be required to explicitly acknowledge this notice. Ordinary read receipts will be tracked separately.
                </span>
              </div>
            )}
          </div>
        )}

        <DialogFooter className="flex items-center justify-end gap-2 pt-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => onOpenChange(false)}
            className="text-xs"
          >
            Back to Edit
          </Button>
          <Button
            type="button"
            size="sm"
            disabled={isSubmitting}
            onClick={() => {
              if (values) {
                onOpenChange(false);
                onConfirm(values);
              }
            }}
            className="text-xs flex items-center gap-1.5"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                <span>Dispatching...</span>
              </>
            ) : values?.isScheduled ? (
              <>
                <Calendar className="h-3.5 w-3.5" />
                <span>Confirm & Schedule</span>
              </>
            ) : (
              <>
                <Send className="h-3.5 w-3.5" />
                <span>Confirm & Dispatch Now</span>
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
