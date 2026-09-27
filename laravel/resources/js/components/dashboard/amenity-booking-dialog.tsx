import React, { useState } from 'react';
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
import { format } from 'date-fns';
import { router } from '@inertiajs/react';
import {
  Calendar as CalendarIcon,
  Clock,
  Users,
  CheckCircle2,
  Building2,
  Sparkles,
  MapPin,
  Share2,
  FileText
} from 'lucide-react';
import { cn } from '@/lib/utils';

export interface AmenityItem {
  id: number;
  name: string;
  category?: string | null;
  maxGuests: number;
  /** "HH:MM - HH:MM", from the server. */
  openHours: string;
  landmarkId?: number | null;
}

export interface BookedSlot {
  amenityId: number;
  date: string;
  slot: string;
}

export interface MyBooking {
  id: number;
  reference: string;
  amenityName: string;
  date: string;
  slot: string;
  guests: number;
  /** Cancelled by the office, not by the resident: shown with the reason, not cancellable. */
  cancelledByOffice: boolean;
  cancellationReason: string | null;
}

/** Must match AmenityBooking::SLOTS on the server, which is what decides. */
const TIME_SLOTS = [
  { id: 'morning', name: 'Morning Slot', starts: '08:00', ends: '12:00', label: '08:00 AM – 12:00 PM' },
  { id: 'afternoon', name: 'Afternoon Slot', starts: '12:00', ends: '16:00', label: '12:00 PM – 04:00 PM' },
  { id: 'evening', name: 'Evening Prime Slot', starts: '16:00', ends: '20:00', label: '04:00 PM – 08:00 PM' },
  { id: 'night', name: 'Twilight Session', starts: '20:00', ends: '22:00', label: '08:00 PM – 10:00 PM' },
];

const slotName = (id: string) => TIME_SLOTS.find((s) => s.id === id)?.name ?? id;

interface AmenityBookingDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  amenities: AmenityItem[];
  bookedSlots?: BookedSlot[];
  myBookings?: MyBooking[];
  initialAmenityId?: number;
}

export function AmenityBookingDialog({
  open,
  onOpenChange,
  amenities,
  bookedSlots = [],
  myBookings = [],
  initialAmenityId,
}: AmenityBookingDialogProps) {
  const { user } = useAuth();
  const { toast } = useToast();

  const [selectedAmenityId, setSelectedAmenityId] = useState<number | undefined>(initialAmenityId ?? amenities[0]?.id);
  const [selectedDate, setSelectedDate] = useState<Date | undefined>(new Date());
  const [selectedSlot, setSelectedSlot] = useState<string>(TIME_SLOTS[1].id);
  const [guestCount, setGuestCount] = useState<number>(4);
  const [notes, setNotes] = useState<string>('');
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [isConfirmed, setIsConfirmed] = useState<boolean>(false);
  const [confirmationRef, setConfirmationRef] = useState<string>('');
  const [cancellingId, setCancellingId] = useState<number | null>(null);

  React.useEffect(() => {
    if (open) {
      setSelectedAmenityId(initialAmenityId ?? amenities[0]?.id);
    }
  }, [open, initialAmenityId, amenities]);

  const activeAmenity = amenities.find((a) => a.id === selectedAmenityId);
  const dateKey = selectedDate ? format(selectedDate, 'yyyy-MM-dd') : '';
  const [opensAt, closesAt] = (activeAmenity?.openHours ?? '').split(' - ');

  /** Why a slot cannot be booked, or null when it can. The server re-checks all of this. */
  const slotUnavailableReason = (slot: (typeof TIME_SLOTS)[number]): string | null => {
    if (!activeAmenity || !selectedDate) return 'Choose an amenity and date';
    if (slot.starts < opensAt || slot.ends > closesAt) return 'Outside opening hours';
    if (bookedSlots.some((b) => b.amenityId === activeAmenity.id && b.date === dateKey && b.slot === slot.id)) {
      return 'Already booked';
    }
    if (dateKey === format(new Date(), 'yyyy-MM-dd') && slot.starts <= format(new Date(), 'HH:mm')) {
      return 'Already started';
    }
    return null;
  };

  const selectedSlotData = TIME_SLOTS.find((s) => s.id === selectedSlot)!;
  const canSubmit =
    !!activeAmenity &&
    !!selectedDate &&
    slotUnavailableReason(selectedSlotData) === null &&
    guestCount >= 1 &&
    guestCount <= (activeAmenity?.maxGuests ?? 0) &&
    !isSubmitting;

  const handleBook = (e: React.FormEvent) => {
    e.preventDefault();
    if (!canSubmit || !activeAmenity) return;

    setIsSubmitting(true);
    router.post(
      route('dashboard.amenity-bookings.store'),
      {
        amenity_id: activeAmenity.id,
        booked_on: dateKey,
        slot: selectedSlot,
        guests: guestCount,
        notes: notes || null,
      },
      {
        preserveScroll: true,
        onFinish: () => setIsSubmitting(false),
        onSuccess: (page) => {
          const booking = (page.props as { flash?: { booking?: { reference?: string } | null } }).flash?.booking;
          setConfirmationRef(booking?.reference ?? '');
          setIsConfirmed(true);
        },
        onError: (errors) => {
          toast({
            variant: 'destructive',
            title: 'Reservation not made',
            description: (Object.values(errors)[0] as string) || 'Please check the details and try again.',
          });
        },
      }
    );
  };

  const handleCancelBooking = (booking: MyBooking) => {
    setCancellingId(booking.id);
    router.delete(route('dashboard.amenity-bookings.destroy', booking.id), {
      preserveScroll: true,
      onFinish: () => setCancellingId(null),
      onSuccess: () => toast({ title: 'Booking cancelled', description: `${booking.reference} has been cancelled.` }),
      onError: (errors) =>
        toast({
          variant: 'destructive',
          title: 'Could not cancel',
          description: (Object.values(errors)[0] as string) || 'Please try again.',
        }),
    });
  };

  const handleReset = () => {
    setIsConfirmed(false);
    setNotes('');
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[92vh] overflow-y-auto">
        {!isConfirmed ? (
          <form onSubmit={handleBook} className="space-y-4">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-lg font-bold">
                <Building2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                Reserve Community Amenity
              </DialogTitle>
              <DialogDescription>
                Schedule facility usage for family gatherings, tennis matches, or community events.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 pt-1">
              {myBookings.length > 0 && (
                <div className="rounded-xl border border-border/80 bg-muted/20 p-3 text-xs space-y-2">
                  <span className="text-[10px] font-semibold text-muted-foreground uppercase block">Your bookings</span>
                  {myBookings.map((booking) => (
                    <div key={booking.id} className="flex items-center justify-between gap-2">
                      <span className={booking.cancelledByOffice ? 'text-muted-foreground' : ''}>
                        <strong className={booking.cancelledByOffice ? 'line-through' : 'text-foreground'}>{booking.amenityName}</strong> ·{' '}
                        {booking.date} · {slotName(booking.slot)}
                        <span className="font-mono text-muted-foreground"> ({booking.reference})</span>
                        {booking.cancelledByOffice && (
                          <span className="block text-[11px] text-rose-600 dark:text-rose-400">
                            Cancelled by the office{booking.cancellationReason ? `: ${booking.cancellationReason}` : '.'}
                          </span>
                        )}
                      </span>
                      {!booking.cancelledByOffice && (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={cancellingId === booking.id}
                        onClick={() => handleCancelBooking(booking)}
                        className="h-6 px-2 text-[11px] text-rose-600 hover:text-rose-700"
                      >
                        {cancellingId === booking.id ? 'Cancelling...' : 'Cancel'}
                      </Button>
                      )}
                    </div>
                  ))}
                </div>
              )}

              {amenities.length === 0 && (
                <p className="rounded-lg border border-dashed p-3 text-xs text-muted-foreground">
                  No amenities are open for booking yet.
                </p>
              )}

              {/* Facility Selection */}
              <div className="space-y-1.5">
                <Label htmlFor="amenity-select" className="text-xs font-semibold">
                  Select Facility / Amenity
                </Label>
                <Select
                  value={selectedAmenityId !== undefined ? String(selectedAmenityId) : undefined}
                  onValueChange={(value) => setSelectedAmenityId(Number(value))}
                >
                  <SelectTrigger id="amenity-select" className="h-10">
                    <SelectValue placeholder="Choose an amenity" />
                  </SelectTrigger>
                  <SelectContent>
                    {amenities.map((amenity) => (
                      <SelectItem key={amenity.id} value={String(amenity.id)}>
                        <div className="flex items-center justify-between w-full gap-2">
                          <span>{amenity.name}</span>
                          {amenity.category && (
                            <span className="text-[10px] text-muted-foreground ml-2">
                              ({amenity.category})
                            </span>
                          )}
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Amenity Specs Badge Banner */}
              <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                <span className="flex items-center gap-1.5 text-muted-foreground">
                  <Clock className="w-3.5 h-3.5 text-emerald-600" />
                  Hours: <strong className="text-foreground">{activeAmenity?.openHours ?? '—'}</strong>
                </span>
                <span className="flex items-center gap-1.5 text-muted-foreground">
                  <Users className="w-3.5 h-3.5 text-blue-600" />
                  Capacity: <strong className="text-foreground">Up to {activeAmenity?.maxGuests ?? 0} guests</strong>
                </span>
              </div>

              {/* Date & Time Slot Row */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {/* Date Picker */}
                <div className="space-y-1.5">
                  <Label className="text-xs font-semibold">Reservation Date</Label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        type="button"
                        className={cn(
                          'w-full justify-start text-left font-normal h-10',
                          !selectedDate && 'text-muted-foreground'
                        )}
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

                {/* Guest Count */}
                <div className="space-y-1.5">
                  <Label htmlFor="guest-count" className="text-xs font-semibold">
                    Expected Attendance
                  </Label>
                  <Input
                    id="guest-count"
                    type="number"
                    min={1}
                    max={activeAmenity?.maxGuests}
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
                    const isSelected = selectedSlot === slot.id;
                    const unavailable = slotUnavailableReason(slot);
                    return (
                      <button
                        key={slot.id}
                        type="button"
                        disabled={unavailable !== null}
                        onClick={() => setSelectedSlot(slot.id)}
                        className={cn(
                          'p-2.5 text-left rounded-xl border text-xs font-medium transition-all flex flex-col justify-between gap-1 disabled:cursor-not-allowed disabled:opacity-50',
                          isSelected && !unavailable
                            ? 'border-emerald-500 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200 ring-1 ring-emerald-500'
                            : 'border-border/80 bg-card hover:bg-muted/40 text-muted-foreground hover:text-foreground'
                        )}
                      >
                        <span className="font-semibold text-foreground text-[11px] leading-tight">{slot.name}</span>
                        <span className="text-[10px] opacity-80">{unavailable ?? `(${slot.label})`}</span>
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
                  <span className="font-mono text-foreground">{user?.lot || 'Main Residence'}</span>
                </div>
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
                  onChange={(e) => setNotes(e.target.value)}
                  className="text-xs h-9"
                />
              </div>
            </div>

            <DialogFooter className="gap-2 sm:gap-0 pt-2">
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={!canSubmit}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold gap-1.5 shadow-sm"
              >
                <Sparkles className="w-4 h-4" />
                {isSubmitting ? 'Reserving...' : 'Confirm Reservation'}
              </Button>
            </DialogFooter>
          </form>
        ) : (
          /* Confirmation Pass Screen */
          <div className="space-y-5 py-2 text-center">
            <div className="mx-auto w-14 h-14 rounded-full bg-emerald-500/15 flex items-center justify-center text-emerald-600 dark:text-emerald-400 animate-bounce">
              <CheckCircle2 className="w-8 h-8" />
            </div>

            <div>
              <Badge variant="outline" className="text-emerald-600 border-emerald-500/40 text-xs px-3 py-1 font-mono">
                {confirmationRef}
              </Badge>
              <h3 className="text-xl font-bold text-foreground mt-2">Reservation Confirmed</h3>
              <p className="text-xs text-muted-foreground mt-1">
                Saved. It appears under your upcoming bookings, where you can cancel it.
              </p>
            </div>

            <div className="rounded-xl border border-border bg-card p-4 text-left space-y-2.5 text-xs shadow-sm">
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Amenity</span>
                <strong className="text-foreground">{activeAmenity?.name}</strong>
              </div>
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Date</span>
                <span className="font-semibold text-foreground">
                  {selectedDate ? format(selectedDate, 'EEEE, MMMM d, yyyy') : 'Scheduled'}
                </span>
              </div>
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Time Slot</span>
                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                  {selectedSlotData.name} ({selectedSlotData.label})
                </span>
              </div>
              <div className="flex justify-between items-center border-b pb-2">
                <span className="text-muted-foreground">Registered Guests</span>
                <span className="font-medium text-foreground">{guestCount} people</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-muted-foreground">Host Lot</span>
                <span className="font-mono text-foreground">{user?.lot || 'Main Residence'}</span>
              </div>
            </div>

            <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
              <Button
                type="button"
                variant="outline"
                className="w-full gap-1.5"
                onClick={() => {
                  navigator.clipboard.writeText(
                    `Amenity Booking: ${activeAmenity?.name} on ${selectedDate ? format(selectedDate, 'PPP') : ''} (${confirmationRef})`
                  );
                  toast({ title: 'Reservation Details Copied' });
                }}
              >
                <Share2 className="w-3.5 h-3.5" />
                Copy Details
              </Button>
              <Button
                type="button"
                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold"
                onClick={handleReset}
              >
                Done
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
