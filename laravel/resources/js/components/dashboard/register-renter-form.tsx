import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { CalendarIcon, UserCheck } from 'lucide-react';
import { Calendar } from '@/components/ui/calendar';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';

type RegisterRenterFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  defaultLot?: string | null;
  defaultStreet?: string | null;
};

export function RegisterRenterForm({
  children,
  open,
  onOpenChange,
  defaultLot,
  defaultStreet,
}: RegisterRenterFormProps) {
  const { toast } = useToast();
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const [name, setName] = useState('');
  const [stayType, setStayType] = useState<'Long-term (Renter)' | 'Short-term (Airbnb)'>('Long-term (Renter)');
  const [contact, setContact] = useState('');
  const [leaseStart, setLeaseStart] = useState<Date | undefined>(new Date());
  const [leaseEnd, setLeaseEnd] = useState<Date | undefined>(undefined);
  const [notes, setNotes] = useState('');

  const resetForm = () => {
    setName('');
    setStayType('Long-term (Renter)');
    setContact('');
    setLeaseStart(new Date());
    setLeaseEnd(undefined);
    setNotes('');
    setErrors({});
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
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

    router.post(
      '/dashboard/renters',
      {
        name,
        stay_type: stayType,
        contact,
        notes,
        lease_start: format(leaseStart, 'yyyy-MM-dd'),
        lease_end: format(leaseEnd, 'yyyy-MM-dd'),
        lot: defaultLot || undefined,
        street: defaultStreet || undefined,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Temporary Homeowner Registered',
            description: `${name} has been added as a ${stayType}.`,
          });
          resetForm();
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

  return (
    <Dialog
      open={open}
      onOpenChange={(isOpen) => {
        onOpenChange(isOpen);
        if (!isOpen) {
          resetForm();
        }
      }}
    >
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-[500px]">
        <form onSubmit={handleSubmit} className="space-y-4">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <UserCheck className="h-5 w-5 text-primary" />
              Register Temporary Homeowner
            </DialogTitle>
            <DialogDescription>
              Grant temporary access to a renter or short-term guest under your property profile. Access automatically expires once the end date is reached.
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
                    <RadioGroupItem value="Long-term (Renter)" id="stay-long" />
                    <Label htmlFor="stay-long" className="cursor-pointer font-semibold">
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
                    <RadioGroupItem value="Short-term (Airbnb)" id="stay-short" />
                    <Label htmlFor="stay-short" className="cursor-pointer font-semibold">
                      Short-term (Airbnb)
                    </Label>
                  </div>
                  <span className="text-xs text-muted-foreground pl-6">
                    Days to weeks vacation stays
                  </span>
                </div>
              </RadioGroup>
            </div>

            {/* Name */}
            <div className="space-y-1.5">
              <Label htmlFor="renter-name">Occupant Name</Label>
              <Input
                id="renter-name"
                placeholder="e.g., Sophia Taylor or John Doe"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
              />
              {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
            </div>

            {/* Contact */}
            <div className="space-y-1.5">
              <Label htmlFor="renter-contact">Contact Phone or Email</Label>
              <Input
                id="renter-contact"
                placeholder="e.g., +1 (555) 234-5678 or guest@example.com"
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

            {/* Notes */}
            <div className="space-y-1.5">
              <Label htmlFor="renter-notes">Notes / Special Instructions (Optional)</Label>
              <Textarea
                id="renter-notes"
                placeholder="Keycode instructions, vehicle license plates, or party size..."
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
              {submitting ? 'Registering...' : 'Register Occupant'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
