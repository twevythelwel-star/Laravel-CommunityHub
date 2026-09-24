import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useToast } from '@/hooks/use-toast';
import { Siren, Send, AlertTriangle, ShieldAlert, Radio, Smartphone, Check } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
  community: string;
}

export function EmergencyBroadcastDialog({ community }: Props) {
  const { toast } = useToast();
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [severity, setSeverity] = useState<'emergency' | 'advisory' | 'info'>('emergency');
  const [audience, setAudience] = useState<'all' | 'residents' | 'visitors' | 'security'>('all');
  const [sendSms, setSendSms] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [confirmSafety, setConfirmSafety] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !content.trim()) {
      toast({
        variant: 'destructive',
        title: 'Missing Fields',
        description: 'Please enter both an alert title and message content.',
      });
      return;
    }

    if (severity === 'emergency' && !confirmSafety) {
      toast({
        variant: 'destructive',
        title: 'Confirmation Required',
        description: 'Please acknowledge the safety confirmation checkbox for emergency alerts.',
      });
      return;
    }

    setSubmitting(true);
    router.post(
      '/dashboard/notifications/broadcast',
      {
        title,
        content,
        severity,
        audience,
        send_sms: sendSms,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSubmitting(false);
          setOpen(false);
          setTitle('');
          setContent('');
          setConfirmSafety(false);
          toast({
            title: 'Broadcast Dispatched',
            description: 'The announcement and SMS alerts have been broadcast.',
          });
        },
        onError: (errs) => {
          setSubmitting(false);
          toast({
            variant: 'destructive',
            title: 'Broadcast Failed',
            description: (Object.values(errs)[0] as string) || 'Unable to dispatch broadcast.',
          });
        },
      }
    );
  };

  const smsSegments = Math.max(1, Math.ceil((title.length + content.length + 25) / 160));

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button
          type="button"
          size="sm"
          className="gap-2 bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-md shadow-rose-600/20"
        >
          <Siren className="h-4 w-4 animate-pulse" />
          <span>Emergency SMS Broadcast</span>
        </Button>
      </DialogTrigger>

      <DialogContent className="sm:max-w-[540px]">
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <div className="flex items-center gap-2 mb-1">
              <span className="p-2 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                <Siren className="h-5 w-5" />
              </span>
              <div>
                <DialogTitle className="text-lg font-bold text-foreground">
                  Dispatch Community Broadcast
                </DialogTitle>
                <DialogDescription className="text-xs">
                  Publish high-priority notices and send mass SMS alerts to {community}.
                </DialogDescription>
              </div>
            </div>
          </DialogHeader>

          <div className="space-y-4 py-4">
            {/* Severity Picker */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold">Alert Severity Level</Label>
              <div className="grid grid-cols-3 gap-2">
                <button
                  type="button"
                  onClick={() => setSeverity('emergency')}
                  className={cn(
                    'p-2.5 rounded-xl border text-xs font-bold text-center transition-all cursor-pointer',
                    severity === 'emergency'
                      ? 'border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-2 ring-rose-500/20'
                      : 'border-border bg-card hover:bg-muted/40 text-muted-foreground'
                  )}
                >
                  🚨 Emergency
                </button>
                <button
                  type="button"
                  onClick={() => setSeverity('advisory')}
                  className={cn(
                    'p-2.5 rounded-xl border text-xs font-bold text-center transition-all cursor-pointer',
                    severity === 'advisory'
                      ? 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400 ring-2 ring-amber-500/20'
                      : 'border-border bg-card hover:bg-muted/40 text-muted-foreground'
                  )}
                >
                  ⚠️ Advisory
                </button>
                <button
                  type="button"
                  onClick={() => setSeverity('info')}
                  className={cn(
                    'p-2.5 rounded-xl border text-xs font-bold text-center transition-all cursor-pointer',
                    severity === 'info'
                      ? 'border-blue-500 bg-blue-500/10 text-blue-600 dark:text-blue-400 ring-2 ring-blue-500/20'
                      : 'border-border bg-card hover:bg-muted/40 text-muted-foreground'
                  )}
                >
                  📢 Notice
                </button>
              </div>
            </div>

            {/* Target Audience */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold">Recipient Audience</Label>
              <select
                value={audience}
                onChange={(e) => setAudience(e.target.value as any)}
                className="w-full h-9 rounded-lg border border-input bg-background px-3 text-xs font-medium focus:ring-2 focus:ring-primary"
              >
                <option value="all">Entire Community (Residents, Active Guests, Security)</option>
                <option value="residents">Residents Only (Homeowners & Renters)</option>
                <option value="visitors">Active Guests Currently On-Site</option>
                <option value="security">Security Personnel Stationed at Gates</option>
              </select>
            </div>

            {/* Alert Title */}
            <div className="space-y-1.5">
              <Label htmlFor="alert-title" className="text-xs font-semibold">
                Alert Title <span className="text-destructive">*</span>
              </Label>
              <Input
                id="alert-title"
                placeholder="e.g. Severe Tropical Storm Warning, Main Gate Malfunction"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                maxLength={160}
                required
                className="h-9 text-xs"
              />
            </div>

            {/* Alert Content */}
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="alert-content" className="text-xs font-semibold">
                  Broadcast Message Body <span className="text-destructive">*</span>
                </Label>
                <span className="text-[10px] text-muted-foreground font-mono">
                  {content.length}/500 chars (~{smsSegments} SMS)
                </span>
              </div>
              <Textarea
                id="alert-content"
                placeholder="Provide concise instructions and safety directives for community members..."
                value={content}
                onChange={(e) => setContent(e.target.value)}
                maxLength={500}
                required
                rows={4}
                className="text-xs resize-none"
              />
            </div>

            {/* SMS Toggle */}
            <div className="flex items-center space-x-2 pt-1">
              <Checkbox
                id="send-sms"
                checked={sendSms}
                onCheckedChange={(checked) => setSendSms(Boolean(checked))}
              />
              <label
                htmlFor="send-sms"
                className="text-xs font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 flex items-center gap-1.5 cursor-pointer"
              >
                <Smartphone className="h-3.5 w-3.5 text-primary" />
                <span>Send SMS alert via Twilio to all recipients with registered phone numbers</span>
              </label>
            </div>

            {/* Confirmation for Emergency */}
            {severity === 'emergency' && (
              <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 text-xs space-y-2">
                <div className="flex items-center space-x-2">
                  <Checkbox
                    id="confirm-safety"
                    checked={confirmSafety}
                    onCheckedChange={(checked) => setConfirmSafety(Boolean(checked))}
                  />
                  <label
                    htmlFor="confirm-safety"
                    className="font-bold leading-none cursor-pointer"
                  >
                    I confirm this is an urgent safety or security emergency.
                  </label>
                </div>
              </div>
            )}
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button type="button" variant="outline" size="sm" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={submitting || (severity === 'emergency' && !confirmSafety)}
              className="gap-2 bg-rose-600 hover:bg-rose-700 text-white font-bold"
            >
              <Send className="h-4 w-4" />
              <span>{submitting ? 'Broadcasting...' : 'Send Broadcast'}</span>
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
