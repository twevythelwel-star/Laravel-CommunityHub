import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Clock, MapPin, Pencil, PlusCircle, Trash2, Users } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { submit } from '@/lib/submit';
import { AmenityForm, type AmenityFields } from '@/components/dashboard/amenity-form';

type UpcomingBooking = {
  id: number;
  reference: string;
  date: string;
  slot: string;
  guests: number;
  notes: string | null;
  residentName: string;
  residentLot: string | null;
};

type ManagedAmenity = {
  id: number;
  name: string;
  category: string | null;
  maxGuests: number;
  opensAt: string;
  closesAt: string;
  isActive: boolean;
  landmarkId: number | null;
  landmarkName: string | null;
  hasBookings: boolean;
  upcomingBookings: UpcomingBooking[];
};

type Props = {
  amenities: ManagedAmenity[];
  landmarks: { id: number; name: string }[];
};

const SLOT_LABELS: Record<string, string> = {
  morning: '08:00–12:00',
  afternoon: '12:00–16:00',
  evening: '16:00–20:00',
  night: '20:00–22:00',
};

const toFields = (a: ManagedAmenity): AmenityFields => ({
  name: a.name,
  category: a.category ?? '',
  max_guests: a.maxGuests,
  opens_at: a.opensAt,
  closes_at: a.closesAt,
  landmark_id: a.landmarkId,
  is_active: a.isActive,
});

export default function AmenitiesPage({ amenities, landmarks }: Props) {
  const { toast } = useToast();
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<ManagedAmenity | null>(null);

  const editingFields = useMemo(() => (editing ? toFields(editing) : undefined), [editing]);

  const openForm = (amenity: ManagedAmenity | null) => {
    setEditing(amenity);
    setFormOpen(true);
  };

  const handleSave = (fields: AmenityFields) =>
    (editing ? submit('patch', `/dashboard/amenities/${editing.id}`, fields) : submit('post', '/dashboard/amenities', fields)).then(() => {
      toast({ title: editing ? 'Amenity updated' : 'Amenity added', description: fields.name });
    });

  const handleDelete = (amenity: ManagedAmenity) =>
    submit('delete', `/dashboard/amenities/${amenity.id}`).then(
      () => toast({ title: 'Amenity deleted', description: amenity.name }),
      (message?: string) =>
        toast({ variant: 'destructive', title: 'Not deleted', description: message ?? 'The server refused the request.' })
    );

  const [cancelReason, setCancelReason] = useState('');

  // The server's message says whether the resident was actually notified, so
  // show that rather than a generic "cancelled". FlashToaster only handles full
  // page loads, so read it from this visit's response.
  const handleCancelBooking = (booking: UpcomingBooking) =>
    router.delete(`/dashboard/amenity-bookings/${booking.id}`, {
      data: { reason: cancelReason },
      preserveScroll: true,
      onSuccess: (page) => {
        setCancelReason('');
        const message = (page.props as { flash?: { success?: string | null } }).flash?.success;
        toast({ title: 'Booking cancelled', description: message ?? `${booking.reference} for ${booking.residentName}` });
      },
      onError: (errors) =>
        toast({
          variant: 'destructive',
          title: 'Not cancelled',
          description: (Object.values(errors)[0] as string) || 'The server refused the request.',
        }),
    });

  return (
    <DashboardLayout>
      <Head title="Amenities" />
      <div className="grid gap-6 max-w-7xl mx-auto pb-12">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="font-headline text-3xl font-bold">Amenities</h1>
            <p className="text-muted-foreground">What residents can book, when it is open, and who has booked it.</p>
          </div>
          <Button size="sm" className="gap-1" onClick={() => openForm(null)}>
            <PlusCircle className="h-3.5 w-3.5" />
            Add Amenity
          </Button>
        </div>

        {amenities.length === 0 && (
          <Card>
            <CardContent className="py-10 text-center text-muted-foreground">
              No amenities yet. Add one to let residents book it.
            </CardContent>
          </Card>
        )}

        <div className="grid gap-4 md:grid-cols-2">
          {amenities.map((amenity) => (
            <Card key={amenity.id} className={amenity.isActive ? '' : 'opacity-75'}>
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <CardTitle className="text-lg flex items-center gap-2 flex-wrap">
                      {amenity.name}
                      <Badge variant={amenity.isActive ? 'default' : 'secondary'} className="text-[10px]">
                        {amenity.isActive ? 'Open for booking' : 'Not bookable'}
                      </Badge>
                    </CardTitle>
                    {amenity.category && <CardDescription>{amenity.category}</CardDescription>}
                  </div>
                  <div className="flex items-center gap-1 shrink-0">
                    <Button size="icon" variant="ghost" onClick={() => openForm(amenity)} aria-label={`Edit ${amenity.name}`}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    {!amenity.hasBookings && (
                      <AlertDialog>
                        <AlertDialogTrigger asChild>
                          <Button size="icon" variant="ghost" className="text-red-600" aria-label={`Delete ${amenity.name}`}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                          <AlertDialogHeader>
                            <AlertDialogTitle>Delete {amenity.name}?</AlertDialogTitle>
                            <AlertDialogDescription>It has never been booked. This cannot be undone.</AlertDialogDescription>
                          </AlertDialogHeader>
                          <AlertDialogFooter>
                            <AlertDialogCancel>Keep it</AlertDialogCancel>
                            <AlertDialogAction onClick={() => handleDelete(amenity)}>Delete</AlertDialogAction>
                          </AlertDialogFooter>
                        </AlertDialogContent>
                      </AlertDialog>
                    )}
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                <div className="flex flex-wrap gap-x-4 gap-y-1 text-muted-foreground">
                  <span className="flex items-center gap-1.5">
                    <Clock className="h-3.5 w-3.5" /> {amenity.opensAt}–{amenity.closesAt}
                  </span>
                  <span className="flex items-center gap-1.5">
                    <Users className="h-3.5 w-3.5" /> Up to {amenity.maxGuests}
                  </span>
                  <span className="flex items-center gap-1.5">
                    <MapPin className="h-3.5 w-3.5" /> {amenity.landmarkName ?? 'Not on the map'}
                  </span>
                </div>
                {amenity.hasBookings && (
                  <p className="text-[11px] text-muted-foreground">Has booking history, so it can be deactivated but not deleted.</p>
                )}

                <div className="rounded-lg border">
                  <div className="border-b px-3 py-2 text-xs font-semibold text-muted-foreground">
                    Upcoming bookings ({amenity.upcomingBookings.length})
                  </div>
                  {amenity.upcomingBookings.length === 0 ? (
                    <p className="px-3 py-3 text-xs text-muted-foreground">None.</p>
                  ) : (
                    <ul className="divide-y">
                      {amenity.upcomingBookings.map((booking) => (
                        <li key={booking.id} className="flex items-start justify-between gap-2 px-3 py-2 text-xs">
                          <div className="min-w-0">
                            <div className="font-medium text-foreground">
                              {booking.date} · {SLOT_LABELS[booking.slot] ?? booking.slot} · {booking.guests} guests
                            </div>
                            <div className="text-muted-foreground">
                              {booking.residentName}
                              {booking.residentLot && ` · ${booking.residentLot}`} ·{' '}
                              <span className="font-mono">{booking.reference}</span>
                            </div>
                            {booking.notes && <div className="text-muted-foreground italic break-words">“{booking.notes}”</div>}
                          </div>
                          <AlertDialog onOpenChange={(isOpen) => isOpen && setCancelReason('')}>
                            <AlertDialogTrigger asChild>
                              <Button size="sm" variant="ghost" className="h-7 shrink-0 text-rose-600">
                                Cancel
                              </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                              <AlertDialogHeader>
                                <AlertDialogTitle>Cancel {booking.residentName}’s booking?</AlertDialogTitle>
                                <AlertDialogDescription>
                                  {amenity.name}, {booking.date}, {SLOT_LABELS[booking.slot] ?? booking.slot}. The slot becomes free for
                                  others. The resident is told by email or SMS, per their notification settings, and sees it in their
                                  bookings.
                                </AlertDialogDescription>
                              </AlertDialogHeader>
                              <div className="space-y-1.5">
                                <Label htmlFor={`cancel-reason-${booking.id}`} className="text-xs">
                                  Reason for the resident (optional)
                                </Label>
                                <Input
                                  id={`cancel-reason-${booking.id}`}
                                  value={cancelReason}
                                  onChange={(e) => setCancelReason(e.target.value)}
                                  placeholder="e.g. Pool closed for maintenance"
                                  maxLength={255}
                                />
                              </div>
                              <AlertDialogFooter>
                                <AlertDialogCancel>Keep booking</AlertDialogCancel>
                                <AlertDialogAction onClick={() => handleCancelBooking(booking)}>Cancel booking</AlertDialogAction>
                              </AlertDialogFooter>
                            </AlertDialogContent>
                          </AlertDialog>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>

      <AmenityForm
        open={formOpen}
        onOpenChange={setFormOpen}
        initial={editingFields}
        landmarks={landmarks}
        onSave={handleSave}
      />
    </DashboardLayout>
  );
}
