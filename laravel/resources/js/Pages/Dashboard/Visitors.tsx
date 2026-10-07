import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
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
import { Calendar as CalendarIcon, MoreHorizontal, Camera, ShieldOff, Share2, FileDown, Copy, Edit, ShieldAlert, MessageSquare, Users, Car, Search, Send, Clock, Link as LinkIcon, Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
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
import { useToast } from '@/hooks/use-toast';
import { format, parseISO } from 'date-fns';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { VisitorIdModal } from '@/components/dashboard/visitor-id-modal';
import { EditVisitorForm } from '@/components/dashboard/edit-visitor-form';
import { RegisterVisitorDialog, toIsoDateTime } from '@/components/dashboard/register-visitor-dialog';
import { GateQrCameraScanner } from '@/components/dashboard/gate-qr-camera-scanner';
import { CategoryShapeIcon } from '@/lib/gate-pass-engine/shapes';
import { CATEGORY_SHAPES } from '@/lib/gate-pass-engine/config';
import type { PassCategory, PassStatus } from '@/lib/gate-pass-engine/types';
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
  /** The visitor's gate pass. Absent for visitors registered before passes existed. */
  pass?: VisitorPass | null;
};

type VisitorPass = {
  id: number;
  passId: string;
  category: PassCategory;
  status: PassStatus;
  statusLabel: string;
  validFrom: string | null;
  validUntil: string | null;
  singleEntry: boolean;
  color: { hex: string; name: string };
  /** The lifecycle moves this viewer may make, decided by the server. */
  actions: { status: PassStatus; label: string }[];
};

/** Passes whose holder cannot be admitted right now, whatever the button says. */
const NOT_ADMISSIBLE: PassStatus[] = ['REQUESTED', 'APPROVED', 'REJECTED', 'CANCELLED', 'REVOKED', 'EXPIRED', 'SUSPENDED'];

/** Moves that must say why. */
const NEEDS_REASON: PassStatus[] = ['REJECTED', 'SUSPENDED', 'REVOKED'];

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
    scan?: number;
    expected: number;
    inside: number;
    checkedOut: number;
    rejected: number;
    history: number;
  };
  securityStats?: {
    visitorsToday: number;
    expected: number;
    currentlyInside: number;
    checkedOut: number;
    rejected: number;
    suspiciousAttempts: number;
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

export default function VisitorsPage({
  visitors,
  filters,
  tabCounts,
  securityStats,
  activeTab = 'all',
  canManage,
  canRegister,
  graceHours,
  userStay,
}: Props) {
  const { toast } = useToast();

  const [scannerOpen, setScannerOpen] = useState(activeTab === 'scan');
  const [selectedVisitor, setSelectedVisitor] = useState<VisitorRow | null>(null);
  const [editingVisitor, setEditingVisitor] = useState<VisitorRow | null>(null);
  const [sharePassVisitor, setSharePassVisitor] = useState<VisitorRow | null>(null);

  // Bulk Event Passes & Plate Search State
  const [bulkDialogOpen, setBulkDialogOpen] = useState(false);
  const [bulkDate, setBulkDate] = useState<Date | undefined>(new Date());
  const [bulkHour, setBulkHour] = useState('10');
  const [bulkMinute, setBulkMinute] = useState('00');
  const [bulkMeridiem, setBulkMeridiem] = useState('AM');
  const [bulkCategory, setBulkCategory] = useState<'VISITOR' | 'CONTRACTOR'>('VISITOR');
  const [bulkText, setBulkText] = useState('');
  const [plateQuery, setPlateQuery] = useState('');
  const [bulkSubmitting, setBulkSubmitting] = useState(false);

  const [rsvpDialogOpen, setRsvpDialogOpen] = useState(false);
  const [rsvpTitle, setRsvpTitle] = useState('');
  const [rsvpDate, setRsvpDate] = useState<Date | undefined>(new Date());
  const [rsvpHour, setRsvpHour] = useState('12');
  const [rsvpMinute, setRsvpMinute] = useState('00');
  const [rsvpMeridiem, setRsvpMeridiem] = useState('PM');
  const [rsvpMaxGuests, setRsvpMaxGuests] = useState('50');
  const [rsvpNotes, setRsvpNotes] = useState('');
  const [rsvpGeneratedUrl, setRsvpGeneratedUrl] = useState<string | null>(null);
  const [rsvpCopied, setRsvpCopied] = useState(false);
  const [rsvpSubmitting, setRsvpSubmitting] = useState(false);

  const handleCreateRsvpInvite = (e: React.FormEvent) => {
    e.preventDefault();
    if (!rsvpTitle.trim()) {
      toast({ variant: 'destructive', title: 'Title required', description: 'Please enter an event or gathering title.' });
      return;
    }

    const expectedAt = toIsoDateTime(rsvpDate, rsvpHour, rsvpMinute, rsvpMeridiem);
    setRsvpSubmitting(true);
    router.post('/dashboard/visitors/invites', {
      title: rsvpTitle,
      expected_at: expectedAt,
      max_guests: parseInt(rsvpMaxGuests, 10) || 50,
      notes: rsvpNotes || null,
    }, {
      preserveScroll: true,
      onSuccess: (page) => {
        setRsvpSubmitting(false);
        const url = (page.props as any).flash?.rsvp_url || null;
        if (url) {
          setRsvpGeneratedUrl(url);
        } else {
          setRsvpDialogOpen(false);
        }
        toast({ title: 'RSVP Invite Created', description: 'Share the link with your attendees.' });
      },
      onError: (errs) => {
        setRsvpSubmitting(false);
        toast({ variant: 'destructive', title: 'Creation failed', description: (Object.values(errs)[0] as string) || 'Error generating RSVP link' });
      }
    });
  };

  const handleServerResend = (visitor: VisitorRow, channel: 'sms' | 'whatsapp' | 'email') => {
    router.post(`/dashboard/visitors/${visitor.id}/resend`, { channel }, {
      preserveScroll: true,
      onSuccess: () => toast({ title: 'Pass Dispatched', description: `Pass sent to ${visitor.name} via ${channel.toUpperCase()}.` }),
      onError: (errs) => toast({ variant: 'destructive', title: 'Dispatch Failed', description: (Object.values(errs)[0] as string) || 'Unable to send pass.' }),
    });
  };

  const handleExtendPass = (visitor: VisitorRow, hours: number) => {
    router.post(`/dashboard/visitors/${visitor.id}/extend`, { hours }, {
      preserveScroll: true,
      onSuccess: () => toast({ title: 'Pass Extended', description: `Validity for ${visitor.name} extended by +${hours} hours.` }),
      onError: (errs) => toast({ variant: 'destructive', title: 'Extension Failed', description: (Object.values(errs)[0] as string) || 'Unable to extend pass.' }),
    });
  };

  const handleBulkSubmit = (e: React.FormEvent) => {
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

    const expectedAt = toIsoDateTime(bulkDate, bulkHour, bulkMinute, bulkMeridiem);

    setBulkSubmitting(true);
    router.post('/dashboard/visitors/bulk', {
      expected_at: expectedAt,
      pass_category: bulkCategory,
      visitors: parsedVisitors,
      // Email only: this dialog has no channel choice. It used to borrow the
      // single-visitor form's checkboxes, which were never shown here.
      notify_email: true,
      notify_sms: false,
      notify_whatsapp: false,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setBulkSubmitting(false);
        setBulkDialogOpen(false);
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

  /** Approve, reject, suspend, reinstate, cancel or revoke a pass. */
  const transitionPass = (visitor: VisitorRow, to: PassStatus, label: string) => {
    if (!visitor.pass) return;

    let reason: string | null = null;
    if (NEEDS_REASON.includes(to)) {
      reason = window.prompt(`${label} the pass for ${visitor.name}. Reason (required):`);
      if (!reason || reason.trim() === '') return;
    }

    router.post(
      `/dashboard/gate-pass/${visitor.pass.id}/transition`,
      { status: to, reason },
      {
        preserveScroll: true,
        onSuccess: () => toast({ title: 'Pass updated', description: `${visitor.name}: ${label.toLowerCase()} done.` }),
        onError: (errors) =>
          toast({
            variant: 'destructive',
            title: 'Pass not updated',
            description: errors.status ?? errors.reason ?? 'The server refused the change.',
          }),
      },
    );
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

          <div className="flex flex-wrap items-center gap-3">
            {/* High-Visibility Security Operator SCAN QR CODE Button */}
            {canManage && (
              <Button
                size="lg"
                onClick={() => setScannerOpen(true)}
                className="h-14 px-6 rounded-2xl font-black text-sm sm:text-base tracking-wide bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white shadow-xl shadow-emerald-600/30 gap-2.5 transition-all hover:scale-[1.02] active:scale-[0.98] border-2 border-emerald-400/40 cursor-pointer focus-visible:ring-4 focus-visible:ring-emerald-400"
                aria-label="Open Gate Pass QR Camera Scanner. Press Space or Click to Launch."
                title="Open Gate Pass Scanner (Shortcut: S)"
              >
                <Camera className="w-5 h-5 animate-pulse text-white" />
                <span className="uppercase tracking-wider">Scan Gate Pass</span>
                <span className="hidden sm:inline-block ml-1 px-2 py-0.5 rounded-md bg-black/25 text-[11px] font-mono text-emerald-100 font-bold border border-white/20">
                  [S]
                </span>
              </Button>
            )}

            {canRegister && (
              <RegisterVisitorDialog graceHours={graceHours} userStay={userStay} secondary={canManage} />
            )}

            {canRegister && (
              <>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() => setBulkDialogOpen(true)}
                  className="gap-1.5 h-11 rounded-xl border-dashed"
                  title="Register a batch of guests or contractors for an event"
                >
                  <Users className="h-4 w-4 text-primary" />
                  <span>Bulk Event Passes</span>
                </Button>

                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    setRsvpGeneratedUrl(null);
                    setRsvpDialogOpen(true);
                  }}
                  className="gap-1.5 h-11 rounded-xl border-dashed"
                  title="Generate a self-service RSVP link for guests to pre-register themselves"
                >
                  <LinkIcon className="h-4 w-4 text-primary" />
                  <span>Event RSVP Link</span>
                </Button>

                <Dialog open={bulkDialogOpen} onOpenChange={setBulkDialogOpen}>
                  <DialogContent className="sm:max-w-[540px]">
                    <form onSubmit={handleBulkSubmit}>
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
                        <Button type="button" variant="outline" size="sm" onClick={() => setBulkDialogOpen(false)}>
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

                <Dialog open={rsvpDialogOpen} onOpenChange={setRsvpDialogOpen}>
                  <DialogContent className="sm:max-w-[500px]">
                    {rsvpGeneratedUrl ? (
                      <div className="space-y-4 py-4 text-center">
                        <div className="mx-auto h-12 w-12 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                          <Check className="h-6 w-6" />
                        </div>
                        <DialogTitle className="text-xl font-bold">Event RSVP Link Ready!</DialogTitle>
                        <DialogDescription className="text-xs">
                          Share this link with your event guests. They can pre-clear themselves and receive instant digital QR gate passes via SMS before arriving.
                        </DialogDescription>
                        <div className="flex items-center gap-2 p-2.5 rounded-xl bg-muted/60 border font-mono text-xs text-foreground">
                          <span className="truncate flex-1 text-left">{rsvpGeneratedUrl}</span>
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={() => {
                              navigator.clipboard.writeText(rsvpGeneratedUrl);
                              setRsvpCopied(true);
                              setTimeout(() => setRsvpCopied(false), 2000);
                              toast({ title: 'Copied!', description: 'RSVP link copied to clipboard.' });
                            }}
                            className="gap-1 h-8"
                          >
                            {rsvpCopied ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
                            <span>{rsvpCopied ? 'Copied' : 'Copy'}</span>
                          </Button>
                        </div>
                        <DialogFooter className="pt-2">
                          <Button
                            type="button"
                            className="w-full"
                            onClick={() => {
                              setRsvpDialogOpen(false);
                              setRsvpGeneratedUrl(null);
                            }}
                          >
                            Done
                          </Button>
                        </DialogFooter>
                      </div>
                    ) : (
                      <form onSubmit={handleCreateRsvpInvite}>
                        <DialogHeader>
                          <div className="flex items-center gap-2 mb-1">
                            <span className="p-1.5 rounded-lg bg-primary/10 text-primary">
                              <LinkIcon className="h-5 w-5" />
                            </span>
                            <DialogTitle className="text-lg font-bold">Create Event RSVP Link</DialogTitle>
                          </div>
                          <DialogDescription className="text-xs">
                            Generate a self-service registration link for guests attending your private party, dinner, or family gathering.
                          </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-3">
                          <div className="space-y-1.5">
                            <Label htmlFor="rsvp-title" className="text-xs font-semibold">Event Name / Occasion</Label>
                            <Input
                              id="rsvp-title"
                              placeholder="e.g. Birthday Party, Dinner Gathering"
                              value={rsvpTitle}
                              onChange={(e) => setRsvpTitle(e.target.value)}
                              required
                              className="h-9 text-xs"
                            />
                          </div>

                          <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                              <Label className="text-xs font-semibold">Arrival Date</Label>
                              <Popover>
                                <PopoverTrigger asChild>
                                  <Button
                                    type="button"
                                    variant="outline"
                                    className={cn('w-full justify-start text-left font-normal h-9 text-xs', !rsvpDate && 'text-muted-foreground')}
                                  >
                                    <CalendarIcon className="mr-2 h-3.5 w-3.5" />
                                    {rsvpDate ? format(rsvpDate, 'MMM d, yyyy') : <span>Pick date</span>}
                                  </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                  <Calendar
                                    mode="single"
                                    selected={rsvpDate}
                                    onSelect={setRsvpDate}
                                    initialFocus
                                  />
                                </PopoverContent>
                              </Popover>
                            </div>

                            <div className="space-y-1.5">
                              <Label className="text-xs font-semibold">Max Guests</Label>
                              <Input
                                type="number"
                                min="1"
                                max="200"
                                value={rsvpMaxGuests}
                                onChange={(e) => setRsvpMaxGuests(e.target.value)}
                                className="h-9 text-xs"
                              />
                            </div>
                          </div>

                          <div className="space-y-1.5">
                            <Label htmlFor="rsvp-notes" className="text-xs font-semibold">Notes for Attendees (Optional)</Label>
                            <Input
                              id="rsvp-notes"
                              placeholder="e.g. Park in visitor bay on North driveway"
                              value={rsvpNotes}
                              onChange={(e) => setRsvpNotes(e.target.value)}
                              className="h-9 text-xs"
                            />
                          </div>
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                          <Button type="button" variant="outline" size="sm" onClick={() => setRsvpDialogOpen(false)}>
                            Cancel
                          </Button>
                          <Button type="submit" size="sm" disabled={rsvpSubmitting} className="gap-1.5">
                            <LinkIcon className="h-4 w-4" />
                            <span>{rsvpSubmitting ? 'Creating Link...' : 'Generate RSVP Link'}</span>
                          </Button>
                        </DialogFooter>
                      </form>
                    )}
                  </DialogContent>
                </Dialog>
              </>
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
        {/* ════════════════════════════════════════════════════════════════
            SECURITY DASHBOARD: TOP LIVE COUNTERS
           ════════════════════════════════════════════════════════════════ */}
        {canManage && (tabCounts || securityStats) && (
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
            {/* Counter 1: VISITORS TODAY */}
            <div className="p-4 sm:p-5 rounded-2xl border bg-card text-left shadow-xs">
              <span className="text-[11px] font-mono uppercase tracking-wider text-muted-foreground font-bold block">
                Visitors Today
              </span>
              <span className="text-3xl sm:text-4xl font-black text-foreground mt-1 block">
                {securityStats?.visitorsToday ?? tabCounts?.history ?? 0}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Scheduled or active</span>
            </div>

            {/* Counter 2: EXPECTED */}
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
                Expected
              </span>
              <span className="text-3xl sm:text-4xl font-black text-amber-600 dark:text-amber-400 mt-1 block">
                {securityStats?.expected ?? tabCounts?.expected ?? 0}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Scheduled clearances</span>
            </button>

            {/* Counter 3: CURRENTLY INSIDE */}
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
                Currently Inside
              </span>
              <span className="text-3xl sm:text-4xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">
                {securityStats?.currentlyInside ?? tabCounts?.inside ?? 0}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Currently on-site</span>
            </button>

            {/* Counter 4: CHECKED OUT */}
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
                Checked Out
              </span>
              <span className="text-3xl sm:text-4xl font-black text-blue-600 dark:text-blue-400 mt-1 block">
                {securityStats?.checkedOut ?? tabCounts?.checkedOut ?? 0}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Departed estate</span>
            </button>

            {/* Counter 5: REJECTED */}
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
                Rejected
              </span>
              <span className="text-3xl sm:text-4xl font-black text-rose-600 dark:text-rose-400 mt-1 block">
                {securityStats?.rejected ?? tabCounts?.rejected ?? 0}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Denied / Lapsed</span>
            </button>

            {/* Counter 6: SUSPICIOUS ATTEMPTS */}
            <div className="p-4 sm:p-5 rounded-2xl border bg-card text-left shadow-xs border-amber-500/30">
              <span className="text-[11px] font-mono uppercase tracking-wider text-amber-600 dark:text-amber-400 font-bold block flex items-center justify-between">
                <span>Suspicious</span>
                <ShieldAlert className="w-3.5 h-3.5" />
              </span>
              <span className="text-3xl sm:text-4xl font-black text-amber-600 dark:text-amber-400 mt-1 block">
                {securityStats?.suspiciousAttempts ?? 0}
              </span>
              <span className="text-[10px] text-muted-foreground mt-1 block">Replay / Tamper alerts</span>
            </div>
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
              className="rounded-xl font-bold gap-1.5 h-9 bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              <Camera className="w-4 h-4" />
              <span>SCAN QR</span>
            </Button>

            <Button
              type="button"
              variant={currentTab === 'expected' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => handleTabChange('expected')}
              className="rounded-xl font-semibold gap-1.5 h-9"
            >
              <span>EXPECTED</span>
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
              <span>INSIDE</span>
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
              <span>CHECKED OUT</span>
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
              <span>REJECTED</span>
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
              <span>HISTORY</span>
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
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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

              <div className="flex items-center gap-2 w-full sm:w-auto">
                <div className="relative w-full sm:w-64">
                  <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                  <Input
                    type="search"
                    placeholder="Search name, phone, plate..."
                    value={plateQuery}
                    onChange={(e) => setPlateQuery(e.target.value)}
                    className="pl-8 h-8 text-xs rounded-xl"
                  />
                </div>
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
                {visitors.data
                  .filter((v) => {
                    if (!plateQuery.trim()) return true;
                    const q = plateQuery.toLowerCase();
                    return (
                      v.name.toLowerCase().includes(q) ||
                      (v.contact && v.contact.toLowerCase().includes(q)) ||
                      (v.vehicle && v.vehicle.toLowerCase().includes(q)) ||
                      (v.hostLot && v.hostLot.toLowerCase().includes(q))
                    );
                  })
                  .length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} className="py-12 text-center text-sm text-muted-foreground">
                      {plateQuery
                        ? `No visitors match "${plateQuery}".`
                        : filters.search
                          ? 'No visitors match that search query.'
                          : currentTab === 'rejected'
                            ? 'Zero rejected visitors recorded today.'
                            : 'No visitors recorded under this category.'}
                    </TableCell>
                  </TableRow>
                )}

                {visitors.data
                  .filter((v) => {
                    if (!plateQuery.trim()) return true;
                    const q = plateQuery.toLowerCase();
                    return (
                      v.name.toLowerCase().includes(q) ||
                      (v.contact && v.contact.toLowerCase().includes(q)) ||
                      (v.vehicle && v.vehicle.toLowerCase().includes(q)) ||
                      (v.hostLot && v.hostLot.toLowerCase().includes(q))
                    );
                  })
                  .map((visitor) => (
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
                      {visitor.pass && (
                        <div className="mt-1.5 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                          <CategoryShapeIcon
                            shape={CATEGORY_SHAPES[visitor.pass.category]}
                            className="h-3.5 w-3.5 shrink-0"
                            color={visitor.pass.color.hex}
                          />
                          <span className="font-mono">{visitor.pass.passId}</span>
                          <span aria-hidden>·</span>
                          <span className={cn(NOT_ADMISSIBLE.includes(visitor.pass.status) && 'text-destructive font-semibold')}>
                            {visitor.pass.statusLabel}
                          </span>
                          {visitor.pass.actions.length > 0 && (
                            <DropdownMenu>
                              <DropdownMenuTrigger asChild>
                                <Button size="sm" variant="ghost" className="h-5 px-1.5 text-[11px]">
                                  Pass ▾<span className="sr-only"> actions for {visitor.name}</span>
                                </Button>
                              </DropdownMenuTrigger>
                              <DropdownMenuContent align="start">
                                <DropdownMenuLabel>Pass {visitor.pass.passId}</DropdownMenuLabel>
                                {visitor.pass.actions.map((action) => (
                                  <DropdownMenuItem
                                    key={action.status}
                                    className={cn(['REJECTED', 'REVOKED', 'CANCELLED'].includes(action.status) && 'text-destructive')}
                                    onClick={() => transitionPass(visitor, action.status, action.label)}
                                  >
                                    {action.label}
                                  </DropdownMenuItem>
                                ))}
                              </DropdownMenuContent>
                            </DropdownMenu>
                          )}
                        </div>
                      )}
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
                              (visitor.status !== 'Checked In' && visitor.pass != null && NOT_ADMISSIBLE.includes(visitor.pass.status)) ||
                              (visitor.status === 'Checked Out' && (visitor.pass == null || visitor.pass.singleEntry))
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
                                    Open in WhatsApp Web
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => handleServerResend(visitor, 'sms')}>
                                    <Send className="h-4 w-4 mr-2 text-primary" />
                                    Dispatch via Twilio SMS
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => handleServerResend(visitor, 'whatsapp')}>
                                    <Share2 className="h-4 w-4 mr-2 text-emerald-600" />
                                    Dispatch via WhatsApp
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => handleServerResend(visitor, 'email')}>
                                    <MessageSquare className="h-4 w-4 mr-2 text-blue-500" />
                                    Dispatch via Email
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => handleExtendPass(visitor, 2)}>
                                    <Clock className="h-4 w-4 mr-2 text-amber-500" />
                                    Extend Validity (+2h)
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => handleExtendPass(visitor, 24)}>
                                    <Clock className="h-4 w-4 mr-2 text-amber-600" />
                                    Extend Validity (+24h)
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
