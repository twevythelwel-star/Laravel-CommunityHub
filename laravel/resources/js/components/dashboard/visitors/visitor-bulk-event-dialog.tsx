import React from 'react';
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

interface VisitorBulkEventDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  bulkCategory: 'VISITOR' | 'CONTRACTOR';
  setBulkCategory: (val: 'VISITOR' | 'CONTRACTOR') => void;
  bulkDate: Date | undefined;
  setBulkDate: (val: Date | undefined) => void;
  bulkText: string;
  setBulkText: (val: string) => void;
  bulkSubmitting: boolean;
  onSubmit: (e: React.FormEvent) => void;
}

export function VisitorBulkEventDialog({
  open,
  onOpenChange,
  bulkCategory,
  setBulkCategory,
  bulkDate,
  setBulkDate,
  bulkText,
  setBulkText,
  bulkSubmitting,
  onSubmit,
}: VisitorBulkEventDialogProps) {
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
