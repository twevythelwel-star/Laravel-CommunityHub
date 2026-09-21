import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { CalendarIcon, Edit } from 'lucide-react';
import { Calendar } from '@/components/ui/calendar';
import { cn } from '@/lib/utils';
import { format, parseISO } from 'date-fns';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type { Renter } from '@/types';

type EditRenterFormProps = {
  renter: Renter | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function EditRenterForm({ renter, open, onOpenChange }: EditRenterFormProps) {
  const { toast } = useToast();
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const [name, setName] = useState('');
  const [stayType, setStayType] = useState<'Long-term (Renter)' | 'Short-term (Airbnb)'>('Long-term (Renter)');
  const [contact, setContact] = useState('');
  const [leaseStart, setLeaseStart] = useState<Date | undefined>(undefined);
  const [leaseEnd, setLeaseEnd] = useState<Date | undefined>(undefined);
  const [notes, setNotes] = useState('');
  const [status, setStatus] = useState<'Active' | 'Inactive'>('Active');

  useEffect(() => {
    if (renter) {
      setName(renter.name);
      setStayType((renter.stayType as any) || 'Long-term (Renter)');
      setContact(renter.contact || '');
      setNotes(renter.notes || '');
      setStatus(renter.status === 'Expired' ? 'Inactive' : (renter.status as any) || 'Active');
      if (renter.leaseStart) {
        setLeaseStart(typeof renter.leaseStart === 'string' ? parseISO(renter.leaseStart) : renter.leaseStart);
      }
      if (renter.leaseEnd) {
        setLeaseEnd(typeof renter.leaseEnd === 'string' ? parseISO(renter.leaseEnd) : renter.leaseEnd);
      }
    }
  }, [renter]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!renter) return;

    if (!name.trim()) {
      setErrors({ name: 'Full name is required.' });
      return;
    }
    if (!leaseStart) {
      setErrors({ lease_start: 'Start date is required.' });
      return;
    }
    if (!leaseEnd) {
      setErrors({ lease_end: 'End date is required.' });
      return;
    }
    if (leaseEnd < leaseStart) {
      setErrors({ lease_end: 'End date cannot be earlier than start date.' });
      return;
    }

    setSubmitting(true);
    setErrors({});

    router.put(
      `/dashboard/renters/${renter.id}`,
      {
        name,
        stay_type: stayType,
        contact,
        notes,
        status,
        lease_start: format(leaseStart, 'yyyy-MM-dd'),
        lease_end: format(leaseEnd, 'yyyy-MM-dd'),
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Temporary Homeowner Updated',
            description: `${name}'s stay details and access timeframe have been updated.`,
          });
          onOpenChange(false);
          setSubmitting(false);
        },
        onError: (errs) => {
          setErrors(errs);
          setSubmitting(false);
        },
      }
    );
  };

  if (!renter) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[500px]">
        <form onSubmit={handleSubmit} className="space-y-4">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Edit className="h-5 w-5 text-primary" />
              Edit Temporary Homeowner
            </DialogTitle>
            <DialogDescription>
              Adjust stay timeframe, stay category, contact details, or active status.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            {/* Stay Type */}
            <div className="space-y-2">
              <Label className="font-medium">Type of Stay</Label>
              <RadioGroup
                value={stayType}
                onValueChange={(val) => setStayType(val as 'Long-term (Renter)' | 'Short-term (Airbnb)')}
                className="grid grid-cols-2 gap-3"
              >
                <div
                  className={cn(
                    'flex flex-col items-start gap-1 p-3 rounded-lg border cursor-pointer transition-all',
                    stayType === 'Long-term (Renter)'
                      ? 'border-primary bg-primary/5 text-primary font-medium'
                      : 'border-muted hover:border-muted-foreground/30'
                  )}
                  onClick={() => setStayType('Long-term (Renter)')}
                >
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="Long-term (Renter)" id="edit-stay-long" />
                    <Label htmlFor="edit-stay-long" className="cursor-pointer font-semibold">
                      Long-term
                    </Label>
                  </div>
                  <span className="text-xs text-muted-foreground pl-6">
                    Multi-month lease tenants
                  </span>
                </div>

                <div
                  className={cn(
                    'flex flex-col items-start gap-1 p-3 rounded-lg border cursor-pointer transition-all',
                    stayType === 'Short-term (Airbnb)'
                      ? 'border-primary bg-primary/5 text-primary font-medium'
                      : 'border-muted hover:border-muted-foreground/30'
                  )}
                  onClick={() => setStayType('Short-term (Airbnb)')}
                >
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="Short-term (Airbnb)" id="edit-stay-short" />
                    <Label htmlFor="edit-stay-short" className="cursor-pointer font-semibold">
                      Short-term (Airbnb)
                    </Label>
                  </div>
                  <span className="text-xs text-muted-foreground pl-6">
                    Vacation rental booking
                  </span>
                </div>
              </RadioGroup>
            </div>

            {/* Name */}
            <div className="space-y-1.5">
              <Label htmlFor="edit-renter-name">Occupant Name</Label>
              <Input
                id="edit-renter-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
              />
              {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
            </div>

            {/* Contact */}
            <div className="space-y-1.5">
              <Label htmlFor="edit-renter-contact">Contact Phone or Email</Label>
              <Input
                id="edit-renter-contact"
                value={contact}
                onChange={(e) => setContact(e.target.value)}
              />
              {errors.contact && <p className="text-xs text-destructive">{errors.contact}</p>}
            </div>

            {/* Timeframe */}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label>Start Date</Label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button
                      type="button"
                      variant="outline"
                      className={cn(
                        'w-full justify-start text-left font-normal',
                        !leaseStart && 'text-muted-foreground'
                      )}
                    >
                      <CalendarIcon className="mr-2 h-4 w-4 opacity-70" />
                      {leaseStart ? format(leaseStart, 'PPP') : <span>Start Date</span>}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                      mode="single"
                      selected={leaseStart}
                      onSelect={setLeaseStart}
                      initialFocus
                    />
                  </PopoverContent>
                </Popover>
                {errors.lease_start && <p className="text-xs text-destructive">{errors.lease_start}</p>}
              </div>

              <div className="space-y-1.5">
                <Label>Expiration Date</Label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button
                      type="button"
                      variant="outline"
                      className={cn(
                        'w-full justify-start text-left font-normal',
                        !leaseEnd && 'text-muted-foreground'
                      )}
                    >
                      <CalendarIcon className="mr-2 h-4 w-4 opacity-70" />
                      {leaseEnd ? format(leaseEnd, 'PPP') : <span>End Date</span>}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                      mode="single"
                      selected={leaseEnd}
                      onSelect={setLeaseEnd}
                      disabled={(date) => (leaseStart ? date < leaseStart : false)}
                      initialFocus
                    />
                  </PopoverContent>
                </Popover>
                {errors.lease_end && <p className="text-xs text-destructive">{errors.lease_end}</p>}
              </div>
            </div>

            {/* Status */}
            <div className="space-y-1.5">
              <Label>Access Status</Label>
              <div className="flex gap-4">
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                  <input
                    type="radio"
                    name="edit-renter-status"
                    checked={status === 'Active'}
                    onChange={() => setStatus('Active')}
                    className="accent-primary"
                  />
                  <span>Active Access</span>
                </label>
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                  <input
                    type="radio"
                    name="edit-renter-status"
                    checked={status === 'Inactive'}
                    onChange={() => setStatus('Inactive')}
                    className="accent-primary"
                  />
                  <span>Inactive / Suspended</span>
                </label>
              </div>
            </div>

            {/* Notes */}
            <div className="space-y-1.5">
              <Label htmlFor="edit-renter-notes">Notes / Special Instructions</Label>
              <Textarea
                id="edit-renter-notes"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={2}
              />
            </div>
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving...' : 'Save Changes'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
