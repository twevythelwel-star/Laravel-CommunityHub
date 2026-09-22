import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { useMessaging } from '@/lib/messaging';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Calendar as CalendarIcon, MoreHorizontal, PlusCircle, Camera, ShieldOff, Share2, FileDown, Copy, Edit, ShieldAlert, MessageSquare } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useToast } from '@/hooks/use-toast';
import type { DateRange } from 'react-day-picker';
import { add, format, parseISO } from 'date-fns';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { VisitorIdModal } from '@/components/dashboard/visitor-id-modal';
import { EditVisitorForm } from '@/components/dashboard/edit-visitor-form';
import { GateQrCameraScanner } from '@/components/dashboard/gate-qr-camera-scanner';
import type { VisitorStatus } from '@/types';

type VisitorRow = {
  id: number;
  name: string;
  contact: string | null;
  vehicle: string | null;
  idType: string | null;
  type: string;
  status: VisitorStatus;
  expectedAt: string;
  dateRange: string | null;
  homeowner: string | null;
  hostLot?: string | null;
  timeDisplay?: string | null;
  idImageUrl: string | null;
  isBlocked: boolean;
  expired: boolean;
  checkedInAt?: string | null;
  checkedOutAt?: string | null;
  shareToken?: string | null;
  guestPassUrl?: string | null;
  notify_email?: boolean;
  notify_sms?: boolean;
  notify_whatsapp?: boolean;
};

type Paginated<T> = {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
  total: number;
  from: number | null;
  to: number | null;
};

type Props = {
  visitors: Paginated<VisitorRow>;
  filters: { status?: string; search?: string; tab?: string };
  tabCounts?: {
    expected: number;
    inside: number;
    checkedOut: number;
    rejected: number;
    history: number;
  };
  activeTab?: string;
  canManage: boolean;
  canRegister: boolean;
  graceHours: number;
  userStay?: {
    stayType: string;
    leaseStart: string;
    leaseEnd: string;
    expired?: boolean;
  } | null;
};

function statusVariant(status: VisitorStatus) {
  switch (status) {
    case 'Checked In':
      return 'default';
    case 'Checked Out':
      return 'secondary';
    default:
      return 'outline';
  }
}

/** Combines a calendar date and a 12-hour time selection into an ISO string. */
function toIsoDateTime(date: Date | undefined, hour: string, minute: string, meridiem: string): string {
  if (!date) return '';

  let h = parseInt(hour, 10) % 12;
  if (meridiem === 'PM') h += 12;

  const composed = new Date(date);
  composed.setHours(h, parseInt(minute, 10), 0, 0);

  return composed.toISOString();
}

export default function VisitorsPage({
  visitors,
  filters,
  tabCounts,
  activeTab = 'all',
  canManage,
  canRegister,
  graceHours,
  userStay,
}: Props) {
  const { toast } = useToast();

  const [scannerOpen, setScannerOpen] = useState(activeTab === 'scan');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedVisitor, setSelectedVisitor] = useState<VisitorRow | null>(null);
  const [editingVisitor, setEditingVisitor] = useState<VisitorRow | null>(null);
  const [sharePassVisitor, setSharePassVisitor] = useState<VisitorRow | null>(null);
  const [expectedDate, setExpectedDate] = useState<Date | undefined>(new Date());
  const [dateRange, setDateRange] = useState<DateRange | undefined>({
    from: new Date(),
    to: add(new Date(), { days: 7 }),
  });
  const [hour, setHour] = useState('10');
  const [minute, setMinute] = useState('00');
  const [meridiem, setMeridiem] = useState('AM');

  /*
   * The registration form previously had no state at all: every input was
   * uncontrolled and "Save visitor" had no onClick, so nothing was ever saved.
   * useForm gives it controlled values, server-side validation errors and a
   * submitting state.
   */
  const messaging = useMessaging();
  const form = useForm({
    name: '',
    contact: '',
    vehicle: '',
    id_type: '',
    id_number: '',
    type: 'One-time' as 'One-time' | 'Recurring',
    expected_at: '',
    date_range: '',
    notify_email: true,
    notify_sms: false,
    notify_whatsapp: false,
  });

  const submitRegistration = (e: React.FormEvent) => {
    e.preventDefault();

    const isRecurring = form.data.type === 'Recurring';

    // Recurring clearances start at the beginning of the range.
    const expectedAt = isRecurring
      ? toIsoDateTime(dateRange?.from, hour, minute, meridiem)
      : toIsoDateTime(expectedDate, hour, minute, meridiem);

    const rangeLabel =
      isRecurring && dateRange?.from && dateRange?.to
        ? `${format(dateRange.from, 'yyyy-MM-dd')} - ${format(dateRange.to, 'yyyy-MM-dd')}`
        : expectedDate
          ? format(expectedDate, 'yyyy-MM-dd')
          : '';

    form.transform((data) => ({
      ...data,
      expected_at: expectedAt,
      date_range: rangeLabel,
    }));

    form.post('/dashboard/visitors', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        setDialogOpen(false);
        toast({
          title: 'Visitor Registered',
          description: 'The gatehouse can now clear this visitor on arrival.',
        });
      },
      // A blocklist hit comes back as a validation error on `name`, so it is
      // surfaced inline by the field rather than swallowed.
    });
  };

  const changeStatus = (visitor: VisitorRow, action: 'check-in' | 'check-out') => {
    router.post(
      `/dashboard/visitors/${visitor.id}/${action}`,
      {},
      {
        preserveScroll: true,
        onSuccess: () =>
          toast({
            title: action === 'check-in' ? 'Visitor Checked In' : 'Visitor Checked Out',
            description: `${visitor.name} — ${visitor.homeowner ?? 'resident'} has been notified.`,
          }),
        onError: (errors) =>
          toast({
            variant: 'destructive',
            title: 'Check-In Denied',
            description:
              errors.visitor ?? `${visitor.name} could not be admitted. Contact security.`,
          }),
      },
    );
  };

  const deleteVisitor = (visitor: VisitorRow) => {
    if (!window.confirm(`Remove ${visitor.name} from the expected list?`)) return;

    router.delete(`/dashboard/visitors/${visitor.id}`, {
      preserveScroll: true,
      onSuccess: () => toast({ title: 'Visitor Removed' }),
    });
  };

  const reportMismatch = (visitor: VisitorRow) => {
    toast({
      variant: 'destructive',
      title: 'Security Alert: ID Mismatch',
      description: `${visitor.homeowner ?? 'The resident'} has been notified about a possible impersonation attempt by someone claiming to be ${visitor.name}.`,
    });
  };

  const copyPassLink = (visitor: VisitorRow) => {
    const url = visitor.guestPassUrl || `${window.location.origin}/guest/pass/${visitor.shareToken}`;
    navigator.clipboard.writeText(url);
    toast({
      title: 'Guest Pass Link Copied',
      description: 'You can now paste and send this pass link to your visitor.',
    });
  };

  const shareViaWhatsApp = (visitor: VisitorRow) => {
    const url = visitor.guestPassUrl || `${window.location.origin}/guest/pass/${visitor.shareToken}`;
    const text = encodeURIComponent(`Hello ${visitor.name}, here is your digital gate entry pass: ${url}`);
    window.open(`https://wa.me/?text=${text}`, '_blank');
  };

  const shareViaSms = (visitor: VisitorRow) => {
    const url = visitor.guestPassUrl || `${window.location.origin}/guest/pass/${visitor.shareToken}`;
    const text = encodeURIComponent(`Hello ${visitor.name}, here is your digital gate entry pass: ${url}`);
    window.open(`sms:?&body=${text}`, '_blank');
  };

  const currentTab = filters.tab || activeTab || 'all';

  const handleTabChange = (tab: string) => {
    router.get(
      '/dashboard/visitors',
      { tab: tab === 'all' ? undefined : tab },
      { preserveScroll: true, preserveState: true }
    );
  };

  return (
    <DashboardLayout>
      <Head title="Visitor Management" />

      {/* QR Camera Scanner Dialog */}
      <GateQrCameraScanner
        open={scannerOpen}
        onOpenChange={setScannerOpen}
        onSuccessCheck={() => router.reload()}
      />

      {selectedVisitor && (
        <VisitorIdModal
          visitor={selectedVisitor}
          open={!!selectedVisitor}
          onOpenChange={(isOpen) => !isOpen && setSelectedVisitor(null)}
          onStatusChange={(_id, newStatus) =>
            changeStatus(selectedVisitor, newStatus === 'Checked In' ? 'check-in' : 'check-out')
          }
          onReportMismatch={() => reportMismatch(selectedVisitor)}
        />
      )}

      {/* Share Guest Pass Dialog */}
      {sharePassVisitor && (
        <Dialog open={!!sharePassVisitor} onOpenChange={(open) => !open && setSharePassVisitor(null)}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-lg font-bold">
                <Share2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                Share Digital Guest Pass
              </DialogTitle>
              <DialogDescription>
                Send secure gate entry credentials to <strong>{sharePassVisitor.name}</strong>.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-2">
              <div className="rounded-xl border border-border/80 bg-muted/40 p-4 space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Visitor</span>
                  <span className="font-semibold text-foreground">{sharePassVisitor.name}</span>
                </div>
                {sharePassVisitor.vehicle && (
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-muted-foreground">Vehicle Plate</span>
                    <span className="font-mono font-medium text-foreground">{sharePassVisitor.vehicle}</span>
                  </div>
                )}
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Expected Arrival</span>
                  <span className="font-medium text-foreground">
                    {sharePassVisitor.timeDisplay || (sharePassVisitor.expectedAt ? format(parseISO(sharePassVisitor.expectedAt), 'PPp') : 'Pending')}
                  </span>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs text-muted-foreground">Guest Pass Direct URL</Label>
                <div className="flex items-center gap-2">
                  <Input
                    readOnly
                    value={sharePassVisitor.guestPassUrl || `${window.location.origin}/guest/pass/${sharePassVisitor.shareToken}`}
                    className="font-mono text-xs select-all bg-background"
                  />
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="shrink-0 gap-1.5"
                    onClick={() => copyPassLink(sharePassVisitor)}
                  >
                    <Copy className="h-3.5 w-3.5" />
                    Copy
                  </Button>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3 pt-1">
                <Button
                  type="button"
                  className="w-full bg-[#25D366] hover:bg-[#20bd5a] text-white font-semibold gap-2 shadow-sm"
                  onClick={() => shareViaWhatsApp(sharePassVisitor)}
                >
                  <Share2 className="h-4 w-4" />
                  WhatsApp
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  className="w-full font-semibold gap-2 border-blue-500/40 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30"
                  onClick={() => shareViaSms(sharePassVisitor)}
                >
                  <MessageSquare className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                  SMS Text
                </Button>
              </div>

              {sharePassVisitor.shareToken && (
                <div className="pt-1 text-center">
                  <a
                    href={`/guest/pass/${sharePassVisitor.shareToken}/pdf`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors underline-offset-4 hover:underline"
                  >
                    <FileDown className="h-3.5 w-3.5" />
                    Download Printable PDF Guest Pass
                  </a>
                </div>
              )}
            </div>

            <DialogFooter className="sm:justify-end">
              <Button variant="ghost" onClick={() => setSharePassVisitor(null)}>
                Close
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}

      <div className="flex flex-col gap-6">
        {/* Top Header with Title & Prominent Action Buttons */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="font-headline text-3xl font-bold tracking-tight">
              {canManage ? 'Security → Visitors' : 'Visitor Management'}
            </h1>
            <p className="text-muted-foreground text-sm">
              {canManage
                ? 'Review, verify credentials, and manage daily estate gate access.'
                : 'Register and manage authorized visitors for your property.'}
            </p>
          </div>

          <div className="flex items-center gap-3">
            {/* Prominent SCAN QR CODE Button */}
            {canManage && (
              <Button
                size="lg"
                onClick={() => setScannerOpen(true)}
                className="h-11 px-5 rounded-xl font-black text-xs sm:text-sm tracking-wide bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-lg shadow-emerald-600/25 gap-2 transition-all hover:scale-[1.02] active:scale-[0.98]"
              >
                <Camera className="w-4 h-4 animate-pulse text-white" />
                <span>📷 SCAN QR CODE</span>
              </Button>
            )}

            {canRegister && (
              <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogTrigger asChild>
                  <Button size="sm" variant={canManage ? "outline" : "default"} className="gap-1.5 h-11 rounded-xl">
                    <PlusCircle className="h-4 w-4" />
                    <span>Register Visitor</span>
                  </Button>
                </DialogTrigger>

                <DialogContent className="sm:max-w-[480px]">
                  <form onSubmit={submitRegistration}>
                    <DialogHeader>
                      <DialogTitle>Register New Visitor</DialogTitle>
                      <DialogDescription>
                        Fill in the details for the new visitor. Clearances expire {graceHours} hours
                        after the expected time if the visitor never arrives.
                      </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                      <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="name" className="text-right">Name</Label>
                        <div className="col-span-3">
                          <Input
                            id="name"
                            placeholder="John Doe"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            aria-invalid={!!form.errors.name}
                            required
                          />
                          {form.errors.name && (
                            <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>
                          )}
                        </div>
                      </div>

                      <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="contact" className="text-right">Contact</Label>
                        <Input
                          id="contact"
                          placeholder="Phone or Email"
                          className="col-span-3"
                          value={form.data.contact}
                          onChange={(e) => form.setData('contact', e.target.value)}
                        />
                      </div>

                      <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="vehicle" className="text-right">Vehicle</Label>
                        <Input
                          id="vehicle"
                          placeholder="Details (optional)"
                          className="col-span-3"
                          value={form.data.vehicle}
                          onChange={(e) => form.setData('vehicle', e.target.value)}
                        />
                      </div>

                      <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="id-type" className="text-right">ID Type</Label>
                        <Select
                          value={form.data.id_type}
                          onValueChange={(value) => form.setData('id_type', value)}
                        >
                          <SelectTrigger id="id-type" className="col-span-3">
                            <SelectValue placeholder="Select ID Type" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="Driver&apos;s License">Driver&apos;s License</SelectItem>
                            <SelectItem value="Passport">Passport</SelectItem>
                            <SelectItem value="National ID">National ID</SelectItem>
                            <SelectItem value="School ID">School ID</SelectItem>
                            <SelectItem value="Work ID">Work ID</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>

                      <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="id-number" className="text-right">ID #</Label>
                        <Input
                          id="id-number"
                          placeholder="Identification Number"
                          className="col-span-3"
                          value={form.data.id_number}
                          onChange={(e) => form.setData('id_number', e.target.value)}
                        />
                      </div>

                      <div className="grid grid-cols-4 items-center gap-4">
                        <Label className="text-right">Entry Type</Label>
                        <RadioGroup
                          value={form.data.type}
                          onValueChange={(value: string) =>
                            form.setData('type', value as 'One-time' | 'Recurring')
                          }
                          className="col-span-3 flex gap-4"
                        >
                          <div className="flex items-center space-x-2">
                            <RadioGroupItem value="One-time" id="r1" />
                            <Label htmlFor="r1">One-time</Label>
                          </div>
                          <div className="flex items-center space-x-2">
                            <RadioGroupItem value="Recurring" id="r2" />
                            <Label htmlFor="r2">Recurring</Label>
                          </div>
                        </RadioGroup>
                      </div>

                      {form.data.type === 'One-time' && (
                        <div className="grid grid-cols-4 items-center gap-4">
                          <Label className="text-right">Date &amp; Time</Label>
                          <div className="col-span-3 flex gap-2">
                            <Popover>
                              <PopoverTrigger asChild>
                                <Button
                                  type="button"
                                  variant="outline"
                                  className={cn(
                                    'w-[150px] justify-start text-left font-normal',
                                    !expectedDate && 'text-muted-foreground',
                                  )}
                                >
                                  <CalendarIcon className="mr-2 h-4 w-4" />
                                  {expectedDate ? format(expectedDate, 'PPP') : <span>Pick a date</span>}
                                </Button>
                              </PopoverTrigger>
                              <PopoverContent className="w-auto p-0">
                                <Calendar
                                  mode="single"
                                  selected={expectedDate}
                                  onSelect={setExpectedDate}
                                  disabled={(date) => {
                                    if (userStay?.leaseStart && userStay?.leaseEnd) {
                                      const s = parseISO(userStay.leaseStart);
                                      s.setHours(0, 0, 0, 0);
                                      const e = parseISO(userStay.leaseEnd);
                                      e.setHours(23, 59, 59, 999);
                                      return date < s || date > e;
                                    }
                                    return false;
                                  }}
                                  initialFocus
                                />
                              </PopoverContent>
                            </Popover>

                            <Select value={hour} onValueChange={setHour}>
                              <SelectTrigger className="w-[80px]"><SelectValue /></SelectTrigger>
                              <SelectContent>
                                {Array.from({ length: 12 }, (_, i) => i + 1).map((h) => (
                                  <SelectItem key={h} value={`${h}`}>{h}</SelectItem>
                                ))}
                              </SelectContent>
                            </Select>

                            <Select value={minute} onValueChange={setMinute}>
                              <SelectTrigger className="w-[80px]"><SelectValue /></SelectTrigger>
                              <SelectContent>
                                {['00', '15', '30', '45'].map((m) => (
                                  <SelectItem key={m} value={m}>{m}</SelectItem>
                                ))}
                              </SelectContent>
                            </Select>

                            <Select value={meridiem} onValueChange={setMeridiem}>
                              <SelectTrigger className="w-[80px]"><SelectValue /></SelectTrigger>
                              <SelectContent>
                                <SelectItem value="AM">AM</SelectItem>
                                <SelectItem value="PM">PM</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
                        </div>
                      )}

                      {form.data.type === 'Recurring' && (
                        <div className="grid grid-cols-4 items-center gap-4">
                          <Label className="text-right">Date Range</Label>
                          <div className="col-span-3">
                            <Popover>
                              <PopoverTrigger asChild>
                                <Button
                                  type="button"
                                  id="date"
                                  variant="outline"
                                  className={cn(
                                    'w-full justify-start text-left font-normal',
                                    !dateRange && 'text-muted-foreground',
                                  )}
                                >
                                  <CalendarIcon className="mr-2 h-4 w-4" />
                                  {dateRange?.from ? (
                                    dateRange.to ? (
                                      <>
                                        {format(dateRange.from, 'LLL dd, y')} –{' '}
                                        {format(dateRange.to, 'LLL dd, y')}
                                      </>
                                    ) : (
                                      format(dateRange.from, 'LLL dd, y')
                                    )
                                  ) : (
                                    <span>Pick a date range</span>
                                  )}
                                </Button>
                              </PopoverTrigger>
                              <PopoverContent className="w-auto p-0" align="start">
                                <Calendar
                                  initialFocus
                                  mode="range"
                                  defaultMonth={dateRange?.from}
                                  selected={dateRange}
                                  onSelect={setDateRange}
                                  numberOfMonths={2}
                                />
                              </PopoverContent>
                            </Popover>
                          </div>
                        </div>
                      )}

                      {form.errors.expected_at && (
                        <p className="text-xs text-destructive">{form.errors.expected_at}</p>
                      )}

                      {/* Notification Preferences */}
                      <div className="grid grid-cols-4 items-start gap-4">
                        <Label className="text-right pt-2">Send Pass Via</Label>
                        <div className="col-span-3 space-y-2">
                          <div className="flex items-center space-x-2">
                            <input
                              type="checkbox"
                              id="notify_email"
                              checked={form.data.notify_email}
                              onChange={(e) => form.setData('notify_email', e.target.checked)}
                              className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <Label htmlFor="notify_email" className="text-sm font-normal cursor-pointer">
                              Email (QR code attached)
                            </Label>
                          </div>
                          <div className="flex items-center space-x-2">
                            <input
                              type="checkbox"
                              id="notify_sms"
                              checked={messaging.sms && form.data.notify_sms}
                              disabled={!messaging.sms}
                              onChange={(e) => form.setData('notify_sms', e.target.checked)}
                              className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                            />
                            <Label htmlFor="notify_sms" className="text-sm font-normal cursor-pointer">
                              SMS Text Message
                              {!messaging.sms && <span className="text-muted-foreground"> (not set up)</span>}
                            </Label>
                          </div>
                          <div className="flex items-center space-x-2">
                            <input
                              type="checkbox"
                              id="notify_whatsapp"
                              checked={messaging.whatsapp && form.data.notify_whatsapp}
                              disabled={!messaging.whatsapp}
                              onChange={(e) => form.setData('notify_whatsapp', e.target.checked)}
                              className="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500 disabled:opacity-50"
                            />
                            <Label htmlFor="notify_whatsapp" className="text-sm font-normal cursor-pointer">
                              WhatsApp
                              {!messaging.whatsapp && <span className="text-muted-foreground"> (not set up)</span>}
                            </Label>
                          </div>
                          <p className="text-xs text-muted-foreground mt-1">
                            Email needs an email address as the contact; SMS and WhatsApp need a phone number.
                          </p>
                        </div>
                      </div>
                    </div>

                    <DialogFooter>
                      <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save visitor'}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            )}
          </div>
        </div>

        {userStay && (
          <div className="rounded-xl border border-blue-500/20 bg-blue-500/5 p-4 flex items-start gap-3">
            <ShieldAlert className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" />
            <div className="text-xs sm:text-sm space-y-1">
              <p className="font-semibold text-foreground flex items-center gap-2">
                Temporary Homeowner Access ({userStay.stayType})
                <Badge variant="outline" className="text-blue-600 border-blue-500/30 text-[11px]">
                  Stay Active
                </Badge>
              </p>
              <p className="text-muted-foreground">
                Authorized stay timeframe: <strong>{format(parseISO(userStay.leaseStart), 'MMM d, yyyy')}</strong> to{' '}
                <strong>{format(parseISO(userStay.leaseEnd), 'MMM d, yyyy')}</strong>. Registered visitors and edits are limited to dates within this timeframe.
              </p>
            </div>
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════
            SECURITY VISITORS DASHBOARD: TOP LIVE COUNTERS
           ════════════════════════════════════════════════════════════════ */}
        {canManage && tabCounts && (
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            {/* Counter 1: EXPECTED */}
            <button
              type="button"
              onClick={() => handleTabChange('expected')}
              className={cn(
                "p-4 sm:p-5 rounded-2xl border text-left transition-all hover:scale-[1.01] shadow-xs cursor-pointer",
                currentTab === 'expected'
                  ? "bg-amber-500/10 border-amber-500/40 ring-2 ring-amber-500/20"
                  : "bg-card hover:bg-muted/40"
              )}
            >
              <span className="text-[11px] font-mono uppercase tracking-wider text-muted-foreground font-bold block">
                EXPECTED
              </span>
              <span className="text-3xl sm:text-4xl font-black text-amber-600 dark:text-amber-400 mt-1 block">
                {tabCounts.expected}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Scheduled clearances</span>
            </button>

            {/* Counter 2: INSIDE */}
            <button
              type="button"
              onClick={() => handleTabChange('inside')}
              className={cn(
                "p-4 sm:p-5 rounded-2xl border text-left transition-all hover:scale-[1.01] shadow-xs cursor-pointer",
                currentTab === 'inside'
                  ? "bg-emerald-500/10 border-emerald-500/40 ring-2 ring-emerald-500/20"
                  : "bg-card hover:bg-muted/40"
              )}
            >
              <span className="text-[11px] font-mono uppercase tracking-wider text-muted-foreground font-bold block">
                INSIDE
              </span>
              <span className="text-3xl sm:text-4xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">
                {tabCounts.inside}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Currently on-site</span>
            </button>

            {/* Counter 3: CHECKED OUT */}
            <button
              type="button"
              onClick={() => handleTabChange('checked-out')}
              className={cn(
                "p-4 sm:p-5 rounded-2xl border text-left transition-all hover:scale-[1.01] shadow-xs cursor-pointer",
                currentTab === 'checked-out'
                  ? "bg-blue-500/10 border-blue-500/40 ring-2 ring-blue-500/20"
                  : "bg-card hover:bg-muted/40"
              )}
            >
              <span className="text-[11px] font-mono uppercase tracking-wider text-muted-foreground font-bold block">
                CHECKED OUT
              </span>
              <span className="text-3xl sm:text-4xl font-black text-blue-600 dark:text-blue-400 mt-1 block">
                {tabCounts.checkedOut}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Departed estate</span>
            </button>

            {/* Counter 4: REJECTED */}
            <button
              type="button"
              onClick={() => handleTabChange('rejected')}
              className={cn(
                "p-4 sm:p-5 rounded-2xl border text-left transition-all hover:scale-[1.01] shadow-xs cursor-pointer",
                currentTab === 'rejected'
                  ? "bg-rose-500/10 border-rose-500/40 ring-2 ring-rose-500/20"
                  : "bg-card hover:bg-muted/40"
              )}
            >
              <span className="text-[11px] font-mono uppercase tracking-wider text-muted-foreground font-bold block">
                REJECTED
              </span>
              <span className="text-3xl sm:text-4xl font-black text-rose-600 dark:text-rose-400 mt-1 block">
                {tabCounts.rejected}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Denied / Blocklist</span>
            </button>
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════
            SECURITY VISITORS SUB-NAVIGATION TABS
           ════════════════════════════════════════════════════════════════ */}
        {canManage && (
          <div className="flex flex-wrap items-center gap-1.5 border-b pb-2 pt-1">
            <Button
              type="button"
              variant={currentTab === 'scan' ? 'default' : 'outline'}
              size="sm"
              onClick={() => setScannerOpen(true)}
              className="rounded-xl font-bold gap-1.5 h-9"
            >
              <Camera className="w-4 h-4 text-emerald-500" />
              <span>📷 Scan QR</span>
            </Button>

            <Button
              type="button"
              variant={currentTab === 'expected' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => handleTabChange('expected')}
              className="rounded-xl font-semibold gap-1.5 h-9"
            >
              <span>Expected Visitors</span>
              {tabCounts && (
                <Badge variant={currentTab === 'expected' ? "secondary" : "outline"} className="px-1.5 py-0 text-[10px]">
                  {tabCounts.expected}
                </Badge>
              )}
            </Button>

            <Button
              type="button"
              variant={currentTab === 'inside' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => handleTabChange('inside')}
              className="rounded-xl font-semibold gap-1.5 h-9"
            >
              <span>Currently Inside</span>
              {tabCounts && (
                <Badge variant={currentTab === 'inside' ? "secondary" : "outline"} className="px-1.5 py-0 text-[10px]">
                  {tabCounts.inside}
                </Badge>
              )}
            </Button>

            <Button
              type="button"
              variant={currentTab === 'checked-out' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => handleTabChange('checked-out')}
              className="rounded-xl font-semibold gap-1.5 h-9"
            >
              <span>Checked Out</span>
              {tabCounts && (
                <Badge variant={currentTab === 'checked-out' ? "secondary" : "outline"} className="px-1.5 py-0 text-[10px]">
                  {tabCounts.checkedOut}
                </Badge>
              )}
            </Button>

            <Button
              type="button"
              variant={currentTab === 'rejected' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => handleTabChange('rejected')}
              className="rounded-xl font-semibold gap-1.5 h-9"
            >
              <span>Rejected</span>
              {tabCounts && (
                <Badge variant={currentTab === 'rejected' ? "secondary" : "outline"} className="px-1.5 py-0 text-[10px] text-rose-500">
                  {tabCounts.rejected}
                </Badge>
              )}
            </Button>

            <Button
              type="button"
              variant={currentTab === 'history' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => handleTabChange('history')}
              className="rounded-xl font-semibold gap-1.5 h-9"
            >
              <span>Visitor History</span>
              {tabCounts && (
                <Badge variant={currentTab === 'history' ? "secondary" : "outline"} className="px-1.5 py-0 text-[10px]">
                  {tabCounts.history}
                </Badge>
              )}
            </Button>

            {currentTab !== 'all' && (
              <Button
                type="button"
                variant="link"
                size="sm"
                onClick={() => handleTabChange('all')}
                className="text-xs text-muted-foreground ml-auto"
              >
                View All Records
              </Button>
            )}
          </div>
        )}

        {/* ════════════════════════════════════════════════════════════════
            TODAY'S VISITORS TABLE (Matching User Columns)
           ════════════════════════════════════════════════════════════════ */}
        <Card className="rounded-2xl shadow-xs border">
          <CardHeader className="pb-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <CardTitle className="text-xl font-bold">
                  {canManage
                    ? currentTab === 'history'
                      ? 'Visitor History'
                      : currentTab === 'rejected'
                        ? 'Rejected Visitors & Denials'
                        : "Today's Visitors"
                    : 'Registered Visitors'}
                </CardTitle>
                <CardDescription className="text-xs">
                  {canManage
                    ? 'Security access registry with time logs and instant gate control.'
                    : 'Visitors registered for your property.'}
                  {visitors.total > 0 && ` Showing ${visitors.from}–${visitors.to} of ${visitors.total}.`}
                </CardDescription>
              </div>
            </div>
          </CardHeader>

          <CardContent className="overflow-x-auto p-0">
            <Table>
              <TableHeader>
                <TableRow className="bg-muted/40">
                  <TableHead className="font-bold">Visitor</TableHead>
                  <TableHead className="font-bold">Type</TableHead>
                  <TableHead className="font-bold">{canManage ? 'Host' : 'Host'}</TableHead>
                  <TableHead className="font-bold">Status</TableHead>
                  <TableHead className="font-bold">Time</TableHead>
                  <TableHead className="font-bold text-right pr-6">Actions</TableHead>
                </TableRow>
              </TableHeader>

              <TableBody>
                {visitors.data.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} className="py-12 text-center text-sm text-muted-foreground">
                      {filters.search
                        ? 'No visitors match that search query.'
                        : currentTab === 'rejected'
                          ? 'Zero rejected visitors recorded today.'
                          : 'No visitors recorded under this category.'}
                    </TableCell>
                  </TableRow>
                )}

                {visitors.data.map((visitor) => (
                  <TableRow
                    key={visitor.id}
                    className={cn(
                      visitor.isBlocked && 'bg-destructive/10 hover:bg-destructive/20',
                      visitor.expired && 'opacity-60',
                    )}
                  >
                    {/* Column 1: Visitor */}
                    <TableCell className="font-semibold text-foreground">
                      <div className="flex items-center gap-2">
                        <span>{visitor.name}</span>
                        {visitor.isBlocked && (
                          <Tooltip>
                            <TooltipTrigger>
                              <ShieldOff className="h-4 w-4 text-destructive shrink-0" />
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>This individual is on the community blocklist.</p>
                            </TooltipContent>
                          </Tooltip>
                        )}
                        {visitor.expired && (
                          <Badge variant="outline" className="text-[10px] text-rose-500 border-rose-500/30">
                            No-show
                          </Badge>
                        )}
                      </div>
                    </TableCell>

                    {/* Column 2: Type */}
                    <TableCell className="text-xs text-muted-foreground font-medium">
                      {visitor.type}
                    </TableCell>

                    {/* Column 3: Host */}
                    <TableCell className="text-xs font-mono font-medium text-foreground">
                      {visitor.hostLot || visitor.homeowner || '#104'}
                    </TableCell>

                    {/* Column 4: Status */}
                    <TableCell>
                      <Badge
                        variant={
                          visitor.status === 'Checked In'
                            ? 'default'
                            : visitor.status === 'Checked Out'
                              ? 'secondary'
                              : 'outline'
                        }
                        className={cn(
                          'text-xs font-bold',
                          visitor.status === 'Checked In' && 'bg-emerald-600 text-white hover:bg-emerald-700'
                        )}
                      >
                        {visitor.status === 'Checked In' ? 'Inside' : visitor.status}
                      </Badge>
                    </TableCell>

                    {/* Column 5: Time */}
                    <TableCell className="text-xs font-mono text-muted-foreground">
                      {visitor.timeDisplay || format(parseISO(visitor.expectedAt), 'g:i A')}
                    </TableCell>

                    {/* Column 6: Actions */}
                    <TableCell className="text-right pr-6">
                      {canManage ? (
                        <div className="flex items-center justify-end gap-2">
                          <Button
                            size="sm"
                            variant={visitor.status === 'Checked In' ? 'default' : 'outline'}
                            onClick={() => changeStatus(visitor, visitor.status === 'Checked In' ? 'check-out' : 'check-in')}
                            disabled={
                              visitor.isBlocked ||
                              visitor.expired ||
                              visitor.status === 'Checked Out'
                            }
                            className={cn(
                              'h-8 px-3 text-xs font-bold',
                              visitor.status === 'Checked In' && 'bg-blue-600 hover:bg-blue-700 text-white'
                            )}
                          >
                            {visitor.status === 'Checked In' ? 'Check Out' : 'Check In'}
                          </Button>
                          <Button
                            size="icon"
                            variant="ghost"
                            className="h-8 w-8 text-muted-foreground hover:text-foreground"
                            onClick={() => setSelectedVisitor(visitor)}
                            title={`View ID for ${visitor.name}`}
                          >
                            <Camera className="h-4 w-4" />
                            <span className="sr-only">View ID for {visitor.name}</span>
                          </Button>
                        </div>
                      ) : (
                        <div className="flex items-center justify-end gap-1">
                          {visitor.shareToken && (
                            <Button
                              size="sm"
                              variant="outline"
                              className="h-8 px-2.5 text-xs gap-1.5 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                              onClick={() => setSharePassVisitor(visitor)}
                              title="Share Guest Pass"
                            >
                              <Share2 className="h-3.5 w-3.5" />
                              <span className="hidden sm:inline">Share</span>
                            </Button>
                          )}
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button aria-haspopup="true" size="icon" variant="ghost">
                                <MoreHorizontal className="h-4 w-4" />
                                <span className="sr-only">Actions for {visitor.name}</span>
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                              <DropdownMenuLabel>Actions</DropdownMenuLabel>
                              {visitor.shareToken && (
                                <>
                                  <DropdownMenuItem onClick={() => setSharePassVisitor(visitor)}>
                                    <Share2 className="h-4 w-4 mr-2 text-indigo-500" />
                                    Quick Share Pass...
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => copyPassLink(visitor)}>
                                    <Copy className="h-4 w-4 mr-2" />
                                    Copy Pass Link
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => shareViaWhatsApp(visitor)}>
                                    <Share2 className="h-4 w-4 mr-2 text-emerald-600" />
                                    Share WhatsApp
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => shareViaSms(visitor)}>
                                    <MessageSquare className="h-4 w-4 mr-2 text-blue-500" />
                                    Share SMS
                                  </DropdownMenuItem>
                                  <DropdownMenuItem asChild>
                                    <a href={`/guest/pass/${visitor.shareToken}/pdf`} target="_blank" rel="noopener noreferrer">
                                      <FileDown className="h-4 w-4 mr-2" />
                                      Download PDF
                                    </a>
                                  </DropdownMenuItem>
                                </>
                              )}
                              <DropdownMenuItem onClick={() => setEditingVisitor(visitor)}>
                                <Edit className="h-4 w-4 mr-2" />
                                Edit Visitor
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => setSelectedVisitor(visitor)}>
                                View ID
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                className="text-destructive"
                                onClick={() => deleteVisitor(visitor)}
                              >
                                Delete
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            {/* Server-side pagination */}
            {visitors.links.length > 3 && (
              <nav className="flex flex-wrap items-center justify-center gap-1 p-4 border-t" aria-label="Pagination">
                {visitors.links.map((link, index) => (
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
      </div>

      <EditVisitorForm
        visitor={editingVisitor}
        open={Boolean(editingVisitor)}
        onOpenChange={(open) => !open && setEditingVisitor(null)}
        userStay={userStay}
      />
    </DashboardLayout>
  );
}
