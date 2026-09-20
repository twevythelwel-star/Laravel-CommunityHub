import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
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
import { Calendar as CalendarIcon, MoreHorizontal, PlusCircle, Camera, ShieldOff, Share2, FileDown, Copy } from 'lucide-react';
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
import { add, format, parseISO } from 'date-fns';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { VisitorIdModal } from '@/components/dashboard/visitor-id-modal';
import type { VisitorStatus } from '@/types';

type VisitorRow = {
  id: number;
  name: string;
  contact: string | null;
  vehicle: string | null;
  idType: string | null;
  type: 'One-time' | 'Recurring';
  status: VisitorStatus;
  expectedAt: string;
  dateRange: string | null;
  homeowner: string | null;
  idImageUrl: string | null;
  isBlocked: boolean;
  expired: boolean;
  shareToken?: string | null;
  guestPassUrl?: string | null;
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
  filters: { status?: string; search?: string };
  canManage: boolean;
  canRegister: boolean;
  graceHours: number;
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

export default function VisitorsPage({ visitors, filters, canManage, canRegister, graceHours }: Props) {
  const { toast } = useToast();

  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedVisitor, setSelectedVisitor] = useState<VisitorRow | null>(null);
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
  const form = useForm({
    name: '',
    contact: '',
    vehicle: '',
    id_type: '',
    id_number: '',
    type: 'One-time' as 'One-time' | 'Recurring',
    expected_at: '',
    date_range: '',
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

  return (
    <DashboardLayout>
      <Head title="Visitor Management" />

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

      <div className="flex flex-col gap-8">
        <div className="flex items-center">
          <div className="flex-1">
            <h1 className="font-headline text-3xl font-bold">Visitor Management</h1>
            <p className="text-muted-foreground">
              {canManage
                ? 'Review and manage daily visitor access.'
                : 'Register and manage visitors for your property.'}
            </p>
          </div>

          {canRegister && (
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
              <DialogTrigger asChild>
                <Button size="sm" className="gap-1">
                  <PlusCircle className="h-3.5 w-3.5" />
                  <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">Register Visitor</span>
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

        <Card>
          <CardHeader>
            <CardTitle>{canManage ? "Today's Visitors" : 'Registered Visitors'}</CardTitle>
            <CardDescription>
              {canManage
                ? 'All expected visitors for today.'
                : 'Visitors you have registered.'}
              {visitors.total > 0 && ` Showing ${visitors.from}–${visitors.to} of ${visitors.total}.`}
            </CardDescription>
          </CardHeader>

          <CardContent className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Visitor Name</TableHead>
                  {canManage && <TableHead>Registered By</TableHead>}
                  <TableHead>Type</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="hidden md:table-cell">Date Range</TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>

              <TableBody>
                {visitors.data.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={canManage ? 6 : 5} className="py-8 text-center text-sm text-muted-foreground">
                      {filters.search
                        ? 'No visitors match that search.'
                        : 'No visitors registered yet.'}
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
                    <TableCell className="font-medium">
                      <div className="flex items-center gap-2">
                        <span>{visitor.name}</span>
                        {visitor.isBlocked && (
                          <Tooltip>
                            <TooltipTrigger>
                              <ShieldOff className="h-4 w-4 text-destructive" />
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>This individual is on the block list.</p>
                            </TooltipContent>
                          </Tooltip>
                        )}
                        {visitor.expired && (
                          <Badge variant="outline" className="text-[10px]">
                            No-show
                          </Badge>
                        )}
                      </div>
                    </TableCell>

                    {canManage && <TableCell>{visitor.homeowner}</TableCell>}

                    <TableCell>{visitor.type}</TableCell>

                    <TableCell>
                      <Badge variant={statusVariant(visitor.status)}>{visitor.status}</Badge>
                    </TableCell>

                    <TableCell className="hidden md:table-cell">
                      {visitor.dateRange ?? format(parseISO(visitor.expectedAt), 'yyyy-MM-dd')}
                    </TableCell>

                    <TableCell>
                      {canManage ? (
                        <div className="flex gap-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => changeStatus(visitor, 'check-in')}
                            disabled={
                              visitor.isBlocked ||
                              visitor.expired ||
                              visitor.status === 'Checked In' ||
                              visitor.status === 'Checked Out'
                            }
                            title={visitor.isBlocked ? 'Blocked by community security' : undefined}
                          >
                            Check In
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => changeStatus(visitor, 'check-out')}
                            disabled={visitor.status !== 'Checked In'}
                          >
                            Check Out
                          </Button>
                          <Button size="icon" variant="ghost" onClick={() => setSelectedVisitor(visitor)}>
                            <Camera className="h-4 w-4" />
                            <span className="sr-only">View ID for {visitor.name}</span>
                          </Button>
                        </div>
                      ) : (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button aria-haspopup="true" size="icon" variant="ghost">
                              <MoreHorizontal className="h-4 w-4" />
                              <span className="sr-only">Actions for {visitor.name}</span>
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            {visitor.shareToken && (
                              <>
                                <DropdownMenuItem onClick={() => copyPassLink(visitor)}>
                                  <Copy className="h-4 w-4 mr-2" />
                                  Copy Pass Link
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => shareViaWhatsApp(visitor)}>
                                  <Share2 className="h-4 w-4 mr-2 text-emerald-600" />
                                  Share WhatsApp
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                  <a href={`/guest/pass/${visitor.id}/pdf`} target="_blank" rel="noopener noreferrer">
                                    <FileDown className="h-4 w-4 mr-2" />
                                    Download PDF
                                  </a>
                                </DropdownMenuItem>
                              </>
                            )}
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
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            {/* Server-side pagination; the original rendered every row at once. */}
            {visitors.links.length > 3 && (
              <nav className="flex flex-wrap items-center justify-center gap-1 pt-4" aria-label="Pagination">
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
    </DashboardLayout>
  );
}
