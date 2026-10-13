import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import { toIsoDateTime } from '@/components/dashboard/register-visitor-dialog';

interface VisitorBulkEventDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function VisitorBulkEventDialog({ open, onOpenChange }: VisitorBulkEventDialogProps) {
  const { toast } = useToast();
  const [bulkDate, setBulkDate] = useState<Date | undefined>(new Date());
  const [bulkCategory, setBulkCategory] = useState<'VISITOR' | 'CONTRACTOR'>('VISITOR');
  const [bulkText, setBulkText] = useState('');
  const [bulkSubmitting, setBulkSubmitting] = useState(false);

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!bulkText.trim()) {
      toast({
        variant: 'destructive',
        title: 'Empty attendee list',
        description: 'Enter at least one visitor (Name, Phone/Email, Vehicle Plate).',
      });
      return;
    }

    const lines = bulkText.split('\n').map(l => l.trim()).filter(Boolean);
    const parsedVisitors = lines.map(line => {
      const parts = line.split(/[,;\t]/).map(p => p.trim());
      return {
        name: parts[0] || 'Guest',
        contact: parts[1] || null,
        vehicle: parts[2] || null,
      };
    });

    if (parsedVisitors.length === 0) {
      toast({ variant: 'destructive', title: 'Invalid format', description: 'Could not parse any visitors.' });
      return;
    }

    // The dialog has no time picker; arrival is 10:00 AM on the chosen day.
    const expectedAt = toIsoDateTime(bulkDate, '10', '00', 'AM');

    setBulkSubmitting(true);
    router.post('/dashboard/visitors/bulk', {
      expected_at: expectedAt,
      pass_category: bulkCategory,
      visitors: parsedVisitors,
      // Email only: this dialog has no channel choice.
      notify_email: true,
      notify_sms: false,
      notify_whatsapp: false,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setBulkSubmitting(false);
        onOpenChange(false);
        setBulkText('');
        toast({
          title: 'Bulk Event Passes Created',
          description: `Registered ${parsedVisitors.length} attendees with digital passes.`,
        });
      },
      onError: (errors) => {
        setBulkSubmitting(false);
        toast({
          variant: 'destructive',
          title: 'Registration Error',
          description: (Object.values(errors)[0] as string) || 'Failed to register bulk attendees.',
        });
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[540px]">
        <form onSubmit={onSubmit}>
          <DialogHeader>
            <div className="flex items-center gap-2 mb-1">
              <span className="p-1.5 rounded-lg bg-primary/10 text-primary">
                <Users className="h-5 w-5" />
              </span>
              <DialogTitle className="text-lg font-bold">Bulk Event Guest Clearance</DialogTitle>
            </div>
            <DialogDescription className="text-xs">
              Pre-clear a batch of attendees for private events, dinner parties, or contractor teams. Each attendee receives an individual digital gate pass.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-3">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">Pass Category</Label>
                <Select
                  value={bulkCategory}
                  onValueChange={(val: 'VISITOR' | 'CONTRACTOR') => setBulkCategory(val)}
                >
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="VISITOR">Visitor (Immediate Pre-clear)</SelectItem>
                    <SelectItem value="CONTRACTOR">Contractor (Security Review)</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">Expected Arrival</Label>
                <Input
                  type="date"
                  value={bulkDate ? format(bulkDate, 'yyyy-MM-dd') : ''}
                  onChange={(e) => setBulkDate(e.target.value ? new Date(e.target.value) : undefined)}
                  className="h-9 text-xs font-mono"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label className="text-xs font-semibold">Attendee List (1 per line)</Label>
                <span className="text-[10px] text-muted-foreground font-mono">Format: Name, Phone/Email, License Plate</span>
              </div>
              <textarea
                rows={6}
                value={bulkText}
                onChange={(e) => setBulkText(e.target.value)}
                placeholder={`Marcus Wright, 876-555-0192, 4821-JC\nElena Rostova, elena@example.com, 9012-AB\nDavid Chen, 876-555-8833`}
                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-xs font-mono shadow-xs placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
              />
              <p className="text-[11px] text-muted-foreground">
                Tip: Comma or tab separated. Phone and vehicle plates are optional.
              </p>
            </div>
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" size="sm" disabled={bulkSubmitting} className="gap-1.5">
              <Users className="h-4 w-4" />
              <span>{bulkSubmitting ? 'Issuing Passes...' : 'Generate Batch Passes'}</span>
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
