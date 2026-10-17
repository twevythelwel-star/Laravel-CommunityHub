import React, { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import { useAuth } from '@/context/auth-context';
import { describeHttpError } from '@/lib/http';
import { format } from 'date-fns';
import {
  Calendar as CalendarIcon,
  Users,
  CheckCircle2,
  Building2,
  Sparkles,
  Share2,
  Loader2,
  X,
  CreditCard,
  Bell,
} from 'lucide-react';
import { cn } from '@/lib/utils';

export interface AmenityItem {
  id: number;
  name: string;
  category?: string;
  maxGuests?: number;
  feeMinor?: number;
  feeFormatted?: string;
}

/** The bookable slots; the keys match FacilityBooking::SLOTS on the server. */
const TIME_SLOTS = [
  { id: 'morning', label: 'Morning Slot', hours: '08:00 AM – 12:00 PM' },
  { id: 'afternoon', label: 'Afternoon Slot', hours: '12:00 PM – 04:00 PM' },
  { id: 'evening', label: 'Evening Prime Slot', hours: '04:00 PM – 08:00 PM' },
  { id: 'night', label: 'Twilight Session', hours: '08:00 PM – 10:00 PM' },
];

type MyBooking = {
  id: number;
  reference: string;
  facility: string;
  date: string;
  slot: string;
  slotLabel: string;
  guests: number;
  feeMinor?: number;
  feeFormatted?: string;
  invoiceReference?: string | null;
  invoiceStatus?: string | null;
};

type Availability = {
  takenSlots: string[];
  guestLimit: number;
  feeMinor: number;
  feeFormatted: string;
  myBookings: MyBooking[];
};

type Confirmation = {
  reference: string;
  facility: string;
  date: string;
  slot: string;
  guests: number;
  fee_minor?: number;
  fee_formatted?: string;
  invoice_reference?: string | null;
};

interface AmenityBookingDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  initialAmenityName?: string;
  availableAmenities?: AmenityItem[];
}

const isoDate = (date: Date) => format(date, 'yyyy-MM-dd');

export function AmenityBookingDialog({
  open,
  onOpenChange,
  initialAmenityName,
  availableAmenities = [],
}: AmenityBookingDialogProps) {
  const { user } = useAuth();
  const { toast } = useToast();

  const [facilityId, setFacilityId] = useState<number | undefined>(availableAmenities[0]?.id);
  const [selectedDate, setSelectedDate] = useState<Date | undefined>(new Date());
  const [selectedSlot, setSelectedSlot] = useState<string>('');
  const [guestCount, setGuestCount] = useState<number>(4);
  const [notes, setNotes] = useState<string>('');
  const [availability, setAvailability] = useState<Availability>({
    takenSlots: [],
    guestLimit: 50,
    feeMinor: 0,
    feeFormatted: 'Free',
    myBookings: [],
  });
  const [isSubmitting, setSubmitting] = useState(false);
  const [confirmation, setConfirmation] = useState<Confirmation | null>(null);

  // Each time it opens, start on the facility the resident clicked, when there is one.
  useEffect(() => {
    if (!open) return;
    const match = availableAmenities.find((a) => a.name.toLowerCase() === initialAmenityName?.toLowerCase());
    setFacilityId(match?.id ?? availableAmenities[0]?.id);
  }, [open, initialAmenityName]);

  const facility = availableAmenities.find((a) => a.id === facilityId);

  const loadAvailability = useCallback(async () => {
    const params: Record<string, string | number> = {};
    if (facilityId) params.landmark_id = facilityId;
    if (selectedDate) params.date = isoDate(selectedDate);
    try {
      const { data } = await axios.get<Availability>('/dashboard/facility-bookings', { params });
      setAvailability(data);
      setSelectedSlot((current) => (current && !data.takenSlots.includes(current) ? current : TIME_SLOTS.find((s) => !data.takenSlots.includes(s.id))?.id ?? ''));
    } catch {
      setAvailability((current) => ({ ...current, takenSlots: [] }));
    }
  }, [facilityId, selectedDate]);

  useEffect(() => {
    if (open) loadAvailability();
  }, [open, loadAvailability]);

  const handleBook = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!facilityId || !selectedDate || !selectedSlot) return;

    setSubmitting(true);
    try {
      const { data } = await axios.post<Confirmation>('/dashboard/facility-bookings', {
        landmark_id: facilityId,
        booked_on: isoDate(selectedDate),
        slot: selectedSlot,
        guests: guestCount,
        notes: notes || null,
      });
      setConfirmation(data);
      toast({
        title: 'Facility booked',
        description: `${data.facility} on ${format(selectedDate, 'PPP')}. Reference ${data.reference}.`,
      });
    } catch (error) {
      toast({ variant: 'destructive', title: 'Could not book', description: describeHttpError(error) });
      loadAvailability();
    } finally {
      setSubmitting(false);
    }
  };

  const handleCancelBooking = async (booking: MyBooking) => {
    if (!confirm(`Cancel your booking of ${booking.facility} on ${booking.date}?`)) return;
    try {
      await axios.post(`/dashboard/facility-bookings/${booking.id}/cancel`);
      toast({ title: 'Booking cancelled', description: `${booking.reference} has been cancelled.` });
      loadAvailability();
    } catch (error) {
      toast({ variant: 'destructive', title: 'Could not cancel', description: describeHttpError(error) });
    }
  };

  const handleDone = () => {
    setConfirmation(null);
    setNotes('');
    onOpenChange(false);
  };

  const guestLimit = availability.guestLimit || facility?.maxGuests || 50;
  const currentFeeFormatted = availability.feeFormatted || facility?.feeFormatted || 'Free';
  const hasFee = (availability.feeMinor || 0) > 0 || (facility?.feeMinor || 0) > 0;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[92vh] overflow-y-auto">
        {!confirmation ? (
          <form onSubmit={handleBook} className="space-y-4">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-lg font-bold">
                <Building2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                Reserve Community Amenity
              </DialogTitle>
              <DialogDescription>
                Reserve a shared community facility. Automatic reminders are sent 24 hours prior to reservation.
              </DialogDescription>
            </DialogHeader>

            {availableAmenities.length === 0 ? (
              <p className="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                No facilities are open for booking yet.
              </p>
            ) : (
              <div className="space-y-4 pt-1">
                {/* Facility Selection */}
                <div className="space-y-1.5">
                  <Label htmlFor="amenity-select" className="text-xs font-semibold">
                    Select Facility / Amenity
                  </Label>
                  <Select value={facilityId ? String(facilityId) : undefined} onValueChange={(v) => setFacilityId(Number(v))}>
                    <SelectTrigger id="amenity-select" className="h-10">
                      <SelectValue placeholder="Choose an amenity" />
                    </SelectTrigger>
                    <SelectContent>
                      {availableAmenities.map((amenity) => (
                        <SelectItem key={amenity.id} value={String(amenity.id)}>
                          <div className="flex items-center justify-between w-full gap-2">
                            <span>{amenity.name}</span>
                            <span className="text-[10px] text-muted-foreground ml-2">
                              {amenity.feeFormatted && amenity.feeFormatted !== 'Free' ? `(${amenity.feeFormatted})` : '(Free)'}
                            </span>
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Capacity and Fee Badges */}
                <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                  <span className="flex items-center gap-1.5 text-muted-foreground">
                    <Users className="w-3.5 h-3.5 text-blue-600" />
                    Capacity: <strong className="text-foreground">Up to {guestLimit} guests</strong>
                  </span>
                  <span className="flex items-center gap-1.5">
                    <Badge variant={hasFee ? 'default' : 'secondary'} className="text-[11px] font-medium">
                      {hasFee ? <CreditCard className="w-3 h-3 mr-1" /> : null}
                      Fee: {currentFeeFormatted}
                    </Badge>
                  </span>
                </div>

                {/* Date & Guests */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold">Reservation Date</Label>
                    <Popover>
                      <PopoverTrigger asChild>
                        <Button
                          variant="outline"
                          type="button"
                          className={cn('w-full justify-start text-left font-normal h-10', !selectedDate && 'text-muted-foreground')}
                        >
                          <CalendarIcon className="mr-2 h-4 w-4" />
                          {selectedDate ? format(selectedDate, 'PPP') : <span>Pick date</span>}
                        </Button>
                      </PopoverTrigger>
                      <PopoverContent className="w-auto p-0" align="start">
                        <Calendar
                          mode="single"
                          selected={selectedDate}
                          onSelect={setSelectedDate}
                          disabled={(date) => date < new Date(new Date().setHours(0, 0, 0, 0))}
                          initialFocus
                        />
                      </PopoverContent>
                    </Popover>
                  </div>

                  <div className="space-y-1.5">
                    <Label htmlFor="guest-count" className="text-xs font-semibold">
                      Expected Attendance
                    </Label>
                    <Input
                      id="guest-count"
                      type="number"
                      min={1}
                      max={guestLimit}
                      value={guestCount}
                      onChange={(e) => setGuestCount(parseInt(e.target.value) || 1)}
                      className="h-10"
                    />
                  </div>
                </div>

                {/* Time Slot Selection */}
                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold">Select Time Window</Label>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {TIME_SLOTS.map((slot) => {
                      const isTaken = availability.takenSlots.includes(slot.id);
                      const isSelected = selectedSlot === slot.id;
                      return (
                        <button
                          key={slot.id}
                          type="button"
                          disabled={isTaken}
                          onClick={() => setSelectedSlot(slot.id)}
                          className={cn(
                            'p-2.5 text-left rounded-xl border text-xs font-medium transition-all flex flex-col justify-between gap-1',
                            isTaken
                              ? 'border-dashed border-border/60 bg-muted/30 text-muted-foreground/60 cursor-not-allowed'
                              : isSelected
                              ? 'border-emerald-500 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200 ring-1 ring-emerald-500'
                              : 'border-border/80 bg-card hover:bg-muted/40 text-muted-foreground hover:text-foreground'
                          )}
                        >
                          <span className="font-semibold text-foreground text-[11px] leading-tight">{slot.label}</span>
                          <span className="text-[10px] opacity-80">{isTaken ? 'Already booked' : `(${slot.hours})`}</span>
                        </button>
                      );
                    })}
                  </div>
                </div>

                {/* Resident Host Information */}
                <div className="rounded-xl border border-border/80 bg-muted/20 p-3 text-xs space-y-1">
                  <div className="flex justify-between items-center text-muted-foreground">
                    <span>Host Resident</span>
                    <span className="font-semibold text-foreground">{user?.displayName || user?.name || 'Resident'}</span>
                  </div>
                  <div className="flex justify-between items-center text-muted-foreground">
                    <span>Assigned Property</span>
                    <span className="font-mono text-foreground">{user?.lot ? `Lot ${user.lot}` : 'Main Residence'}</span>
                  </div>
                  {hasFee && (
                    <div className="flex justify-between items-center text-amber-700 dark:text-amber-400 pt-1 border-t border-border/60 font-medium text-[11px]">
                      <span>Billing Terms</span>
                      <span>{currentFeeFormatted} invoiced to resident account</span>
                    </div>
                  )}
                </div>

                {/* Optional Notes */}
                <div className="space-y-1.5">
                  <Label htmlFor="booking-notes" className="text-xs font-semibold">
                    Special Setup / Equipment Notes (Optional)
                  </Label>
                  <Input
                    id="booking-notes"
                    placeholder="e.g. Need grill gas canister, extra banquet tables..."
                    value={notes}
                    maxLength={500}
                    onChange={(e) => setNotes(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>

                {/* Automatic reminder disclosure */}
                <div className="flex items-center gap-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 px-3 py-2 text-[11px] text-emerald-800 dark:text-emerald-300">
                  <Bell className="w-3.5 h-3.5 shrink-0" />
                  <span>A reminder will be automatically sent to you 24 hours before your reservation slot.</span>
                </div>

                {/* Your upcoming bookings */}
                {availability.myBookings.length > 0 && (
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold">Your Upcoming Bookings</Label>
                    <ul className="divide-y rounded-xl border text-xs">
                      {availability.myBookings.map((booking) => (
                        <li key={booking.id} className="flex items-center justify-between gap-2 px-3 py-2">
                          <div>
                            <span className="font-semibold text-foreground">{booking.facility}</span>
                            <span className="text-muted-foreground"> · {format(new Date(`${booking.date}T00:00:00`), 'EEE d MMM')} · {booking.slotLabel}</span>
                            {booking.feeMinor && booking.feeMinor > 0 ? (
                              <Badge variant="outline" className="ml-2 text-[10px] py-0 px-1.5 font-normal">
                                {booking.feeFormatted}
                              </Badge>
                            ) : null}
                          </div>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => handleCancelBooking(booking)}
                            className="h-7 px-2 text-xs text-destructive hover:text-destructive"
                          >
                            <X className="mr-1 h-3 w-3" /> Cancel
                          </Button>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            )}

            <DialogFooter className="gap-2 sm:gap-0 pt-2">
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={isSubmitting || !facilityId || !selectedSlot}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold gap-1.5 shadow-sm"
              >
                {isSubmitting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                Confirm Reservation
              </Button>
            </DialogFooter>
          </form>
        ) : (
          /* Confirmation Pass Screen */
          <div className="space-y-5 py-2 text-center">
            <div className="mx-auto w-14 h-14 rounded-full bg-emerald-500/15 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
              <CheckCircle2 className="w-8 h-8" />
            </div>

            <div>
              <Badge variant="outline" className="text-emerald-600 border-emerald-500/40 text-xs px-3 py-1 font-mono">
                {confirmation.reference}
              </Badge>
              <h3 className="text-xl font-bold text-foreground mt-2">Reservation Confirmed</h3>
              <p className="text-xs text-muted-foreground mt-1">
                A confirmation is in your notifications. An automatic reminder will arrive 24h prior.
              </p>
            </div>

            <div className="rounded-xl border border-border bg-card p-4 text-left space-y-2.5 text-xs shadow-sm">
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Amenity</span>
                <strong className="text-foreground">{confirmation.facility}</strong>
              </div>
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Date</span>
                <span className="font-semibold text-foreground">
                  {format(new Date(`${confirmation.date}T00:00:00`), 'EEEE, MMMM d, yyyy')}
                </span>
              </div>
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Time Slot</span>
                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                  {TIME_SLOTS.find((s) => s.id === confirmation.slot)?.hours}
                </span>
              </div>
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Registered Guests</span>
                <span className="font-medium text-foreground">{confirmation.guests} people</span>
              </div>
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Booking Fee</span>
                <span className="font-medium text-foreground">
                  {confirmation.fee_formatted || 'Free'}
                  {confirmation.invoice_reference && (
                    <span className="text-[11px] text-muted-foreground font-normal ml-1">
                      (Invoice #{confirmation.invoice_reference})
                    </span>
                  )}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-muted-foreground">Host Lot</span>
                <span className="font-mono text-foreground">{user?.lot ? `Lot ${user.lot}` : 'Main Residence'}</span>
              </div>
            </div>

            <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
              <Button
                type="button"
                variant="outline"
                className="w-full gap-1.5"
                onClick={() => {
                  navigator.clipboard.writeText(
                    `Amenity Booking: ${confirmation.facility} on ${confirmation.date} (${confirmation.reference})`
                  );
                  toast({ title: 'Reservation Details Copied' });
                }}
              >
                <Share2 className="w-3.5 h-3.5" />
                Copy Details
              </Button>
              <Button type="button" className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold" onClick={handleDone}>
                Done
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
