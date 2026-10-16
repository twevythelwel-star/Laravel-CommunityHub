import { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Bell,
  Radio,
  CheckCircle2,
  AlertTriangle,
  AlertCircle,
  ShieldCheck,
  Mail,
  MessageSquare,
  Smartphone,
  Calendar,
  Clock,
  Trash2,
  Send,
  Users,
  Search,
  Filter,
  Layers,
  Building,
  MapPin,
  Eye,
  FileText,
  RotateCcw,
  Check,
  X,
  Loader2,
  Info,
  Sparkles,
  ArrowRight,
  TrendingUp,
} from 'lucide-react';
import { NotificationForm, ChannelStatus } from '@/components/dashboard/notification-form';
import { EmergencyBroadcastDialog } from '@/components/dashboard/emergency-broadcast-dialog';
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { useToast } from '@/hooks/use-toast';

export type NotificationRow = {
  id: number;
  title: string;
  content: string;
  category?: string;
  priority?: string;
  author: string;
  timestamp: string;
  targetRoles: string[] | null;
  targetType?: string | null;
  channels?: string[] | null;
  stats?: Record<string, number> | null;
  requiresAcknowledgement?: boolean;
  acknowledgementDeadline?: string | null;
  isRead?: boolean;
  readAt?: string | null;
  isAcknowledged?: boolean;
  acknowledgedAt?: string | null;
  acknowledgementNote?: string | null;
};

export type ScheduledAnnouncementRow = {
  id: number;
  title: string;
  content: string;
  author: string;
  scheduledFor: string;
  targetType?: string | null;
  channels?: string[] | null;
  requiresAcknowledgement?: boolean;
  acknowledgementDeadline?: string | null;
};

export type AnnouncementSummaryRow = {
  id: number;
  title: string;
  message: string;
  category: string;
  priority: string;
  status: string;
  publishTime: string | null;
  scheduledTime: string | null;
  expiresAt: string | null;
  isExpired: boolean;
  requiresAcknowledgement?: boolean;
  acknowledgementDeadline?: string | null;
  author: string;
  targetType: string;
  targetCriteria?: Record<string, any> | null;
  recipientCount: number;
  deliveredCount: number;
  failureCount: number;
  readCount?: number;
  acknowledgedCount?: number;
  stats?: Record<string, any> | null;
};

export type SummaryStats = {
  total: number;
  drafts: number;
  scheduled: number;
  published: number;
  expired: number;
  totalRecipientsReached: number;
  totalDeliveryFailures: number;
  totalReads?: number;
  totalAcknowledgements?: number;
};

export type DeliveryRecipient = {
  id: number;
  userId: number;
  name: string;
  email: string | null;
  phone: string | null;
  deliveryState: string;
  attemptedChannels: string[];
  channelOutcomes: Record<string, any>;
  failureDetails: string | null;
  deliveredAt: string | null;
  isRead: boolean;
  readAt?: string | null;
  isAcknowledged?: boolean;
  acknowledgedAt?: string | null;
  acknowledgementNote?: string | null;
};

export type DeliveryReport = {
  announcementId: number;
  title: string;
  category: string;
  priority: string;
  publishedAt: string | null;
  requiresAcknowledgement?: boolean;
  acknowledgementDeadline?: string | null;
  stats: Record<string, any> | null;
  totalRecipients: number;
  deliveredCount: number;
  failedCount: number;
  readCount: number;
  acknowledgedCount?: number;
  pendingAcknowledgementCount?: number;
  recipients: DeliveryRecipient[];
};

export type Paginated<T> = {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
  total: number;
  from: number | null;
  to: number | null;
};

type Props = {
  notifications: Paginated<NotificationRow>;
  scheduledAnnouncements?: ScheduledAnnouncementRow[];
  announcements?: AnnouncementSummaryRow[];
  summaryStats?: SummaryStats | null;
  deliveryReports?: DeliveryReport[];
  streets?: string[];
  blocks?: string[];
  sections?: string[];
  canBroadcast: boolean;
  roles: string[];
  community: string;
  aiEnabled: boolean;
  notificationChannels?: Record<string, ChannelStatus>;
};

function initial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || '?';
}

export default function NotificationsPage({
  notifications,
  scheduledAnnouncements = [],
  announcements = [],
  summaryStats = null,
  deliveryReports = [],
  streets = [],
  blocks = [],
  sections = [],
  canBroadcast,
  roles,
  community,
  aiEnabled,
  notificationChannels = {},
}: Props) {
  const { toast } = useToast();
  const [activeTab, setActiveTab] = useState<string>('dashboard');
  const [selectedReportId, setSelectedReportId] = useState<number | null>(
    deliveryReports.length > 0 ? deliveryReports[0].announcementId : null
  );
  const [cancellingId, setCancellingId] = useState<number | null>(null);

  const handleCancelAnnouncement = (id: number, title: string) => {
    if (!confirm(`Are you sure you want to cancel the scheduled announcement "${title}"?`)) {
      return;
    }

    setCancellingId(id);
    router.post(
      `/dashboard/notifications/${id}/cancel`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Announcement Cancelled',
            description: `"${title}" has been removed from the dispatch queue.`,
          });
        },
        onError: (err) => {
          toast({
            variant: 'destructive',
            title: 'Could not cancel announcement',
            description: Object.values(err)[0] ?? 'Server error occurred.',
          });
        },
        onFinish: () => setCancellingId(null),
      },
    );
  };

  const navigateToDeliveryReport = (announcementId: number) => {
    setSelectedReportId(announcementId);
    setActiveTab('delivery-report');
  };

  return (
    <DashboardLayout>
      <Head title="Community Announcements & Notifications" />

      <div className="grid gap-8">
        {/* Header Bar */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="font-headline text-3xl font-bold tracking-tight">Community Announcements</h1>
              {canBroadcast && (
                <Badge variant="outline" className="border-primary/40 bg-primary/10 text-primary text-xs font-semibold">
                  Admin Console
                </Badge>
              )}
            </div>
            <p className="text-muted-foreground text-sm mt-1">
              Multi-channel delivery pipeline, recipient targeting, and community notification centre.
            </p>
          </div>
          {canBroadcast && <EmergencyBroadcastDialog community={community} />}
        </div>

        {/* Administration Area: 4 Main Screens */}
        {canBroadcast ? (
          <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
            <div className="flex items-center justify-between border-b pb-3 mb-6 overflow-x-auto">
              <TabsList className="bg-muted/60 p-1 rounded-xl">
                <TabsTrigger value="dashboard" className="flex items-center gap-2 text-xs font-semibold">
                  <Layers className="h-4 w-4" />
                  <span>Announcement Dashboard</span>
                  {summaryStats && (
                    <Badge variant="secondary" className="ml-1 text-[10px] px-1.5 py-0 font-mono">
                      {summaryStats.total}
                    </Badge>
                  )}
                </TabsTrigger>
                <TabsTrigger value="create" className="flex items-center gap-2 text-xs font-semibold">
                  <Send className="h-4 w-4" />
                  <span>Create Announcement</span>
                </TabsTrigger>
                <TabsTrigger value="audience-preview" className="flex items-center gap-2 text-xs font-semibold">
                  <Users className="h-4 w-4" />
                  <span>Audience Preview</span>
                </TabsTrigger>
                <TabsTrigger value="delivery-report" className="flex items-center gap-2 text-xs font-semibold">
                  <Radio className="h-4 w-4" />
                  <span>Delivery Report</span>
                  {deliveryReports.length > 0 && (
                    <Badge variant="secondary" className="ml-1 text-[10px] px-1.5 py-0 font-mono">
                      {deliveryReports.length}
                    </Badge>
                  )}
                </TabsTrigger>
                <TabsTrigger value="resident-view" className="flex items-center gap-2 text-xs font-semibold">
                  <Bell className="h-4 w-4" />
                  <span>Resident Notice Feed</span>
                  {notifications.total > 0 && (
                    <Badge variant="secondary" className="ml-1 text-[10px] px-1.5 py-0 font-mono">
                      {notifications.total}
                    </Badge>
                  )}
                </TabsTrigger>
              </TabsList>
            </div>

            {/* Screen 1: Announcement Dashboard */}
            <TabsContent value="dashboard" className="space-y-6">
              <AnnouncementDashboardScreen
                summaryStats={summaryStats}
                announcements={announcements}
                notificationChannels={notificationChannels}
                onViewDeliveryReport={navigateToDeliveryReport}
                onCancelAnnouncement={handleCancelAnnouncement}
                cancellingId={cancellingId}
                onCreateClick={() => setActiveTab('create')}
              />
            </TabsContent>

            {/* Screen 2: Create Announcement */}
            <TabsContent value="create" className="space-y-6">
              <Card className="border shadow-sm">
                <CardHeader>
                  <CardTitle className="text-xl flex items-center gap-2">
                    <Send className="h-5 w-5 text-primary" />
                    <span>Create Announcement</span>
                  </CardTitle>
                  <CardDescription>
                    Title, message, category, priority, audience targeting, channels, optional expiry, and publish-now or schedule-later controls.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <NotificationForm
                    roles={roles}
                    community={community}
                    aiEnabled={aiEnabled}
                    streets={streets}
                    blocks={blocks}
                    sections={sections}
                    notificationChannels={notificationChannels}
                    onSuccess={() => {
                      setActiveTab('dashboard');
                    }}
                  />
                </CardContent>
              </Card>
            </TabsContent>

            {/* Screen 3: Audience Preview */}
            <TabsContent value="audience-preview" className="space-y-6">
              <AudiencePreviewScreen
                streets={streets}
                blocks={blocks}
                sections={sections}
                community={community}
                onComposeWithAudience={() => setActiveTab('create')}
              />
            </TabsContent>

            {/* Screen 4: Delivery Report */}
            <TabsContent value="delivery-report" className="space-y-6">
              <DeliveryReportScreen
                deliveryReports={deliveryReports}
                selectedReportId={selectedReportId}
                onSelectReport={setSelectedReportId}
              />
            </TabsContent>

            {/* Screen 5: Resident Notice Feed */}
            <TabsContent value="resident-view" className="space-y-6">
              <PublishedNoticesCard notifications={notifications} canBroadcast={canBroadcast} />
            </TabsContent>
          </Tabs>
        ) : (
          /* Resident Notice Board Feed */
          <PublishedNoticesCard notifications={notifications} canBroadcast={canBroadcast} />
        )}
      </div>
    </DashboardLayout>
  );
}

// =========================================================================
// SCREEN 1: Announcement Dashboard
// =========================================================================
function AnnouncementDashboardScreen({
  summaryStats,
  announcements,
  notificationChannels,
  onViewDeliveryReport,
  onCancelAnnouncement,
  cancellingId,
  onCreateClick,
}: {
  summaryStats: SummaryStats | null;
  announcements: AnnouncementSummaryRow[];
  notificationChannels: Record<string, ChannelStatus>;
  onViewDeliveryReport: (id: number) => void;
  onCancelAnnouncement: (id: number, title: string) => void;
  cancellingId: number | null;
  onCreateClick: () => void;
}) {
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [categoryFilter, setCategoryFilter] = useState('all');
  const [priorityFilter, setPriorityFilter] = useState('all');

  const filteredAnnouncements = useMemo(() => {
    return announcements.filter((item) => {
      const matchesSearch =
        searchTerm === '' ||
        item.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.message.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.author.toLowerCase().includes(searchTerm.toLowerCase());

      const matchesStatus =
        statusFilter === 'all' ||
        (statusFilter === 'expired' ? item.isExpired : item.status === statusFilter);

      const matchesCategory =
        categoryFilter === 'all' || item.category === categoryFilter;

      const matchesPriority =
        priorityFilter === 'all' || item.priority === priorityFilter;

      return matchesSearch && matchesStatus && matchesCategory && matchesPriority;
    });
  }, [announcements, searchTerm, statusFilter, categoryFilter, priorityFilter]);

  return (
    <div className="space-y-6">
      {/* KPI Stats Strip */}
      {summaryStats && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <Card className="p-3 border bg-card/60 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold">{summaryStats.total}</span>
              <Layers className="h-4 w-4 text-muted-foreground/60" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">All broadcasts</span>
          </Card>

          <Card className="p-3 border bg-card/60 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Published</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{summaryStats.published}</span>
              <CheckCircle2 className="h-4 w-4 text-emerald-500" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">Active notices</span>
          </Card>

          <Card className="p-3 border bg-card/60 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wider">Scheduled</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-amber-600 dark:text-amber-400">{summaryStats.scheduled}</span>
              <Clock className="h-4 w-4 text-amber-500" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">In dispatch queue</span>
          </Card>

          <Card className="p-3 border bg-primary/5 border-primary/20 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-primary uppercase tracking-wider">Recipients Reached</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-primary">{summaryStats.totalRecipientsReached}</span>
              <Users className="h-4 w-4 text-primary" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">Delivered via transport</span>
          </Card>

          <Card className="p-3 border bg-sky-500/5 border-sky-500/20 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-sky-600 dark:text-sky-400 uppercase tracking-wider">Verified Reads</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-sky-600 dark:text-sky-400">{summaryStats.totalReads ?? 0}</span>
              <Eye className="h-4 w-4 text-sky-500" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">Confirmed opens</span>
          </Card>

          <Card className="p-3 border bg-indigo-500/5 border-indigo-500/20 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Formal Sign-offs</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{summaryStats.totalAcknowledgements ?? 0}</span>
              <ShieldCheck className="h-4 w-4 text-indigo-500" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">Explicit acknowledgements</span>
          </Card>
        </div>
      )}

      {/* Multi-Channel Engine Readiness Strip */}
      {Object.keys(notificationChannels).length > 0 && (
        <Card className="border shadow-sm bg-gradient-to-br from-card via-card to-muted/20">
          <CardHeader className="py-3 px-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Radio className="h-4 w-4 text-primary" />
                <span className="text-xs font-semibold">Notification Engine &bull; Channel Readiness</span>
              </div>
              <Badge variant="outline" className="font-mono text-[10px]">
                {Object.keys(notificationChannels).length} Transports Configured
              </Badge>
            </div>
          </CardHeader>
          <CardContent className="pt-0 px-4 pb-3">
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
              {Object.values(notificationChannels).map((ch) => (
                <div
                  key={ch.channel}
                  className="p-2 rounded-lg border bg-background/60 flex items-center justify-between text-xs"
                >
                  <div className="truncate mr-2">
                    <span className="font-medium block text-foreground truncate">{ch.label}</span>
                    <span className="text-[10px] text-muted-foreground block truncate">{ch.provider}</span>
                  </div>
                  {ch.configured ? (
                    <Badge variant="outline" className="text-[9px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10 shrink-0">
                      Active
                    </Badge>
                  ) : (
                    <Badge variant="outline" className="text-[9px] border-amber-500/40 text-amber-600 bg-amber-500/10 shrink-0">
                      Inactive
                    </Badge>
                  )}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Search and Filters Toolbar */}
      <Card className="border shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <CardTitle className="text-base font-bold flex items-center gap-2">
                <Search className="h-4 w-4 text-primary" />
                <span>Search & Filter Announcements</span>
              </CardTitle>
              <CardDescription className="text-xs">
                Filter announcements by title, author, category, priority, and publication status.
              </CardDescription>
            </div>
            <Button size="sm" onClick={onCreateClick} className="h-8 text-xs shrink-0 flex items-center gap-1.5">
              <Send className="h-3.5 w-3.5" />
              <span>Create Announcement</span>
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div className="relative">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search title, message, or author..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-9 h-9 text-xs"
              />
            </div>

            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="h-9 text-xs">
                <SelectValue placeholder="All Statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Statuses</SelectItem>
                <SelectItem value="published">Published</SelectItem>
                <SelectItem value="scheduled">Scheduled</SelectItem>
                <SelectItem value="draft">Drafts</SelectItem>
                <SelectItem value="expired">Expired</SelectItem>
              </SelectContent>
            </Select>

            <Select value={categoryFilter} onValueChange={setCategoryFilter}>
              <SelectTrigger className="h-9 text-xs">
                <SelectValue placeholder="All Categories" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Categories</SelectItem>
                <SelectItem value="general">General</SelectItem>
                <SelectItem value="maintenance">Maintenance</SelectItem>
                <SelectItem value="security">Security</SelectItem>
                <SelectItem value="emergency">Emergency</SelectItem>
                <SelectItem value="event">Event</SelectItem>
                <SelectItem value="financial">Financial</SelectItem>
              </SelectContent>
            </Select>

            <Select value={priorityFilter} onValueChange={setPriorityFilter}>
              <SelectTrigger className="h-9 text-xs">
                <SelectValue placeholder="All Priorities" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Priorities</SelectItem>
                <SelectItem value="low">Low Priority</SelectItem>
                <SelectItem value="normal">Normal Priority</SelectItem>
                <SelectItem value="urgent">Urgent Priority</SelectItem>
                <SelectItem value="critical">Critical Priority</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {(searchTerm !== '' || statusFilter !== 'all' || categoryFilter !== 'all' || priorityFilter !== 'all') && (
            <div className="flex items-center gap-2 pt-1 text-xs text-muted-foreground">
              <span>Filtering active. Showing {filteredAnnouncements.length} of {announcements.length} announcements.</span>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  setSearchTerm('');
                  setStatusFilter('all');
                  setCategoryFilter('all');
                  setPriorityFilter('all');
                }}
                className="h-6 px-2 text-xs text-primary"
              >
                Reset Filters
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Announcements Table */}
      <Card className="border shadow-sm">
        <CardHeader className="pb-3">
          <CardTitle className="text-base font-bold">Announcements Catalog</CardTitle>
          <CardDescription className="text-xs">
            Comprehensive audit log of notices, target criteria, recipient reach, and failure telemetry.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {filteredAnnouncements.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center text-muted-foreground">
              <Layers className="h-9 w-9 text-muted-foreground/40 mb-2" />
              <p className="text-sm font-medium">No announcements match your filter criteria.</p>
              <Button variant="outline" size="sm" onClick={onCreateClick} className="mt-3 text-xs">
                Create First Announcement
              </Button>
            </div>
          ) : (
            <div className="rounded-xl border overflow-hidden">
              <Table>
                <TableHeader className="bg-muted/40">
                  <TableRow>
                    <TableHead className="w-[30%]">Announcement Details</TableHead>
                    <TableHead>Category & Priority</TableHead>
                    <TableHead>Audience Target</TableHead>
                    <TableHead>Status & Expiry</TableHead>
                    <TableHead>Recipient Reach</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredAnnouncements.map((a) => {
                    const statusBadgeVariant =
                      a.isExpired
                        ? 'secondary'
                        : a.status === 'published'
                        ? 'default'
                        : a.status === 'scheduled'
                        ? 'outline'
                        : 'secondary';

                    return (
                      <TableRow key={a.id} className="hover:bg-muted/30">
                        <TableCell>
                          <div className="space-y-1">
                            <span className="font-semibold text-sm block text-foreground leading-snug">
                              {a.title}
                            </span>
                            <p className="text-xs text-muted-foreground line-clamp-1 max-w-sm">
                              {a.message}
                            </p>
                            <span className="text-[11px] text-muted-foreground block">
                              By {a.author} &bull; {a.publishTime ? (
                                <ClientFormattedDate date={a.publishTime} formatString="MMM d, yyyy" />
                              ) : a.scheduledTime ? (
                                <>Scheduled: <ClientFormattedDate date={a.scheduledTime} formatString="MMM d, yyyy h:mm a" /></>
                              ) : (
                                'Draft'
                              )}
                            </span>
                          </div>
                        </TableCell>

                        <TableCell>
                          <div className="flex flex-col gap-1 items-start">
                            <Badge variant="outline" className="text-[10px] uppercase font-mono capitalize">
                              {a.category}
                            </Badge>
                            <Badge
                              variant="outline"
                              className={`text-[10px] font-mono capitalize ${
                                a.priority === 'urgent' || a.priority === 'critical'
                                  ? 'border-destructive/40 text-destructive bg-destructive/10'
                                  : 'text-muted-foreground'
                              }`}
                            >
                              {a.priority}
                            </Badge>
                          </div>
                        </TableCell>

                        <TableCell>
                          <div className="space-y-1">
                            <Badge variant="secondary" className="text-[10px] uppercase font-mono">
                              {a.targetType.replace('_', ' ')}
                            </Badge>
                          </div>
                        </TableCell>

                        <TableCell>
                          <div className="space-y-1">
                            {a.isExpired ? (
                              <Badge variant="secondary" className="text-[10px] bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                Expired
                              </Badge>
                            ) : a.status === 'published' ? (
                              <Badge variant="outline" className="text-[10px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                                Published
                              </Badge>
                            ) : a.status === 'scheduled' ? (
                              <Badge variant="outline" className="text-[10px] border-amber-500/40 text-amber-600 bg-amber-500/10">
                                Scheduled
                              </Badge>
                            ) : (
                              <Badge variant="outline" className="text-[10px]">
                                {a.status}
                              </Badge>
                            )}

                            {a.expiresAt && (
                              <span className="text-[10px] text-muted-foreground block">
                                Exp: <ClientFormattedDate date={a.expiresAt} formatString="MMM d, yyyy" />
                              </span>
                            )}
                          </div>
                        </TableCell>

                        <TableCell>
                          <div className="space-y-1">
                            <div className="flex items-center gap-2 text-xs font-medium">
                              <Users className="h-3.5 w-3.5 text-primary" />
                              <span>{a.recipientCount} targeted</span>
                            </div>
                            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground flex-wrap">
                              <span className="text-emerald-600 dark:text-emerald-400 font-medium">{a.deliveredCount} delivered</span>
                              {a.readCount !== undefined && (
                                <span className="text-sky-600 dark:text-sky-400 font-medium">&bull; {a.readCount} read</span>
                              )}
                              {a.failureCount > 0 && (
                                <span className="text-destructive font-medium">&bull; {a.failureCount} failed</span>
                              )}
                            </div>
                            {a.requiresAcknowledgement && (
                              <div className="pt-0.5">
                                <Badge variant="outline" className="border-indigo-500/40 text-indigo-600 bg-indigo-500/10 text-[9px] py-0 font-medium flex items-center gap-1 w-fit">
                                  <ShieldCheck className="h-2.5 w-2.5 text-indigo-600" />
                                  <span>Ack: {a.acknowledgedCount ?? 0}/{a.recipientCount}</span>
                                </Badge>
                              </div>
                            )}
                          </div>
                        </TableCell>

                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            {a.status === 'published' && (
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => onViewDeliveryReport(a.id)}
                                className="h-7 text-xs flex items-center gap-1"
                              >
                                <Radio className="h-3 w-3 text-primary" />
                                <span>Report</span>
                              </Button>
                            )}

                            {a.status === 'scheduled' && (
                              <Button
                                size="sm"
                                variant="destructive"
                                disabled={cancellingId === a.id}
                                onClick={() => onCancelAnnouncement(a.id, a.title)}
                                className="h-7 text-xs flex items-center gap-1"
                              >
                                <Trash2 className="h-3 w-3" />
                                <span>{cancellingId === a.id ? 'Cancelling...' : 'Cancel'}</span>
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

// =========================================================================
// SCREEN 3: Audience Preview (Targeting & Deduplication Explorer)
// =========================================================================
function AudiencePreviewScreen({
  streets,
  blocks,
  sections,
  community,
  onComposeWithAudience,
}: {
  streets: string[];
  blocks: string[];
  sections: string[];
  community: string;
  onComposeWithAudience: () => void;
}) {
  const { toast } = useToast();
  const [targetType, setTargetType] = useState<string>('entire_community');
  const [selectedStreet, setSelectedStreet] = useState<string>('');
  const [selectedBlock, setSelectedBlock] = useState<string>('');
  const [selectedSection, setSelectedSection] = useState<string>('');
  const [customStreets, setCustomStreets] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const [previewResult, setPreviewResult] = useState<{
    totalRecipients: number;
    uniqueRecipients: number;
    overlapCount: number;
    hasOverlaps: boolean;
    inAppCount: number;
    emailCount: number;
    phoneCount: number;
    sample: Array<{
      id: number;
      name: string;
      street: string;
      lot: string;
      hasEmail: boolean;
      hasPhone: boolean;
    }>;
  } | null>(null);

  const runAudienceCalculation = async () => {
    setIsLoading(true);
    try {
      const criteria: Record<string, any> = {};
      if (targetType === 'street' && selectedStreet) {
        criteria.street = selectedStreet;
      } else if (targetType === 'block' && selectedBlock) {
        criteria.block = selectedBlock;
      } else if (targetType === 'section' && selectedSection) {
        criteria.section = selectedSection;
      } else if (targetType === 'custom_group') {
        criteria.streets = customStreets;
        if (selectedBlock) criteria.blocks = [selectedBlock];
      }

      const res = await axios.post('/dashboard/notifications/preview-audience', {
        target_type: targetType,
        target_criteria: criteria,
      });

      setPreviewResult(res.data);
    } catch (err: any) {
      toast({
        variant: 'destructive',
        title: 'Preview Failed',
        description: err.response?.data?.message ?? 'Could not calculate audience metrics.',
      });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <Card className="border shadow-sm">
        <CardHeader>
          <CardTitle className="text-xl flex items-center gap-2">
            <Users className="h-5 w-5 text-primary" />
            <span>Audience Target & Deduplication Explorer</span>
          </CardTitle>
          <CardDescription>
            Simulate and inspect audience scope across streets, blocks, and sections before sending. Detect overlapping boundaries and guarantee single-delivery deduplication.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Target Query Simulator Controls */}
          <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 p-4 rounded-xl border bg-muted/20">
            <div>
              <label className="text-xs font-semibold text-foreground block mb-1.5">Targeting Scope</label>
              <Select value={targetType} onValueChange={(val) => setTargetType(val)}>
                <SelectTrigger className="h-9 text-xs">
                  <SelectValue placeholder="Select target scope" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="entire_community">Entire Community</SelectItem>
                  <SelectItem value="street">Specific Street</SelectItem>
                  <SelectItem value="block">Specific Block</SelectItem>
                  <SelectItem value="section">Specific Section</SelectItem>
                  <SelectItem value="custom_group">Custom Multi-Target</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {targetType === 'street' && (
              <div>
                <label className="text-xs font-semibold text-foreground block mb-1.5">Select Street</label>
                <Select value={selectedStreet} onValueChange={setSelectedStreet}>
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue placeholder="Choose street..." />
                  </SelectTrigger>
                  <SelectContent>
                    {streets.map((st) => (
                      <SelectItem key={st} value={st}>{st}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {targetType === 'block' && (
              <div>
                <label className="text-xs font-semibold text-foreground block mb-1.5">Select Block</label>
                <Select value={selectedBlock} onValueChange={setSelectedBlock}>
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue placeholder="Choose block..." />
                  </SelectTrigger>
                  <SelectContent>
                    {blocks.map((bl) => (
                      <SelectItem key={bl} value={bl}>Block {bl}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {targetType === 'section' && (
              <div>
                <label className="text-xs font-semibold text-foreground block mb-1.5">Select Section / Type</label>
                <Select value={selectedSection} onValueChange={setSelectedSection}>
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue placeholder="Choose section..." />
                  </SelectTrigger>
                  <SelectContent>
                    {sections.map((sec) => (
                      <SelectItem key={sec} value={sec}>{sec}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {targetType === 'custom_group' && (
              <>
                <div>
                  <label className="text-xs font-semibold text-foreground block mb-1.5">Target Street</label>
                  <Select
                    value={customStreets[0] ?? ''}
                    onValueChange={(val) => setCustomStreets(val ? [val] : [])}
                  >
                    <SelectTrigger className="h-9 text-xs">
                      <SelectValue placeholder="Choose street..." />
                    </SelectTrigger>
                    <SelectContent>
                      {streets.map((st) => (
                        <SelectItem key={st} value={st}>{st}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <label className="text-xs font-semibold text-foreground block mb-1.5">Target Block</label>
                  <Select value={selectedBlock} onValueChange={setSelectedBlock}>
                    <SelectTrigger className="h-9 text-xs">
                      <SelectValue placeholder="Choose block..." />
                    </SelectTrigger>
                    <SelectContent>
                      {blocks.map((bl) => (
                        <SelectItem key={bl} value={bl}>Block {bl}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </>
            )}

            <div className="flex items-end">
              <Button
                onClick={runAudienceCalculation}
                disabled={isLoading}
                className="w-full h-9 text-xs flex items-center justify-center gap-1.5"
              >
                {isLoading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Eye className="h-3.5 w-3.5" />}
                <span>Calculate Audience</span>
              </Button>
            </div>
          </div>

          {/* Results Display */}
          {previewResult && (
            <div className="space-y-4 pt-2">
              {/* Overlap & Deduplication Notice */}
              {previewResult.hasOverlaps ? (
                <div className="p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 flex items-start gap-3">
                  <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                  <div className="space-y-1">
                    <h4 className="text-sm font-semibold text-amber-800 dark:text-amber-300">
                      Overlapping Selections Detected ({previewResult.overlapCount} overlaps)
                    </h4>
                    <p className="text-xs text-amber-700/90 dark:text-amber-400/90 leading-relaxed">
                      Multiple selected criteria overlap on identical resident properties (e.g. properties located on the selected street inside the selected block).
                      <strong className="block mt-1 font-semibold">
                        &bull; Antigravity Deduplication Active: Overlapping criteria are automatically unified using unique resident IDs. Exactly {previewResult.uniqueRecipients} distinct notices will be dispatched.
                      </strong>
                    </p>
                  </div>
                </div>
              ) : (
                <div className="p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 flex items-start gap-3">
                  <CheckCircle2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                  <div className="space-y-1">
                    <h4 className="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                      Clean Audience Separation (Zero Overlaps)
                    </h4>
                    <p className="text-xs text-emerald-700/90 dark:text-emerald-400/90">
                      The selected targeting boundary contains {previewResult.uniqueRecipients} distinct recipients with zero boundary overlaps.
                    </p>
                  </div>
                </div>
              )}

              {/* KPI Metrics */}
              <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <Card className="p-3 border bg-card/60">
                  <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Raw Matches</span>
                  <div className="text-2xl font-bold mt-1">{previewResult.totalRecipients}</div>
                  <span className="text-[10px] text-muted-foreground">Before deduplication</span>
                </Card>

                <Card className="p-3 border bg-primary/5 border-primary/20">
                  <span className="text-[11px] font-medium text-primary uppercase tracking-wider">Unique Recipients</span>
                  <div className="text-2xl font-bold text-primary mt-1">{previewResult.uniqueRecipients}</div>
                  <span className="text-[10px] text-muted-foreground">Net recipients to dispatch</span>
                </Card>

                <Card className="p-3 border bg-card/60">
                  <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Email Reachable</span>
                  <div className="text-2xl font-bold text-foreground mt-1">
                    {previewResult.emailCount}{' '}
                    <span className="text-xs text-muted-foreground font-normal">
                      ({previewResult.uniqueRecipients > 0 ? Math.round((previewResult.emailCount / previewResult.uniqueRecipients) * 100) : 0}%)
                    </span>
                  </div>
                  <span className="text-[10px] text-muted-foreground">Verified emails on file</span>
                </Card>

                <Card className="p-3 border bg-card/60">
                  <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Phone / SMS Reachable</span>
                  <div className="text-2xl font-bold text-foreground mt-1">
                    {previewResult.phoneCount}{' '}
                    <span className="text-xs text-muted-foreground font-normal">
                      ({previewResult.uniqueRecipients > 0 ? Math.round((previewResult.phoneCount / previewResult.uniqueRecipients) * 100) : 0}%)
                    </span>
                  </div>
                  <span className="text-[10px] text-muted-foreground">Mobile numbers on file</span>
                </Card>
              </div>

              {/* Sample Recipient Roster */}
              {previewResult.sample && previewResult.sample.length > 0 && (
                <div className="space-y-2 pt-2">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-foreground">
                      Sample Recipient Roster ({previewResult.sample.length} of {previewResult.uniqueRecipients})
                    </span>
                    <Button
                      size="sm"
                      onClick={onComposeWithAudience}
                      className="h-8 text-xs flex items-center gap-1.5"
                    >
                      <Send className="h-3.5 w-3.5" />
                      <span>Compose Announcement for this Target</span>
                    </Button>
                  </div>

                  <div className="rounded-xl border overflow-hidden">
                    <Table>
                      <TableHeader className="bg-muted/40">
                        <TableRow>
                          <TableHead>Resident Name</TableHead>
                          <TableHead>Street Address</TableHead>
                          <TableHead>Lot / Block</TableHead>
                          <TableHead>In-App</TableHead>
                          <TableHead>Email on File</TableHead>
                          <TableHead>Phone on File</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {previewResult.sample.map((r) => (
                          <TableRow key={r.id}>
                            <TableCell className="font-medium text-xs">{r.name}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">{r.street || '—'}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">{r.lot || '—'}</TableCell>
                            <TableCell>
                              <Badge variant="outline" className="text-[9px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                                Active
                              </Badge>
                            </TableCell>
                            <TableCell>
                              {r.hasEmail ? (
                                <Check className="h-4 w-4 text-emerald-600" />
                              ) : (
                                <X className="h-4 w-4 text-muted-foreground/40" />
                              )}
                            </TableCell>
                            <TableCell>
                              {r.hasPhone ? (
                                <Check className="h-4 w-4 text-emerald-600" />
                              ) : (
                                <X className="h-4 w-4 text-muted-foreground/40" />
                              )}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

// =========================================================================
// SCREEN 4: Delivery Report
// =========================================================================
function DeliveryReportScreen({
  deliveryReports,
  selectedReportId,
  onSelectReport,
}: {
  deliveryReports: DeliveryReport[];
  selectedReportId: number | null;
  onSelectReport: (id: number) => void;
}) {
  const [recipientFilter, setRecipientFilter] = useState<'all' | 'delivered' | 'read' | 'acknowledged' | 'pending_ack' | 'failed'>('all');
  const [recipientSearch, setRecipientSearch] = useState('');

  const currentReport = useMemo(() => {
    if (!selectedReportId) return deliveryReports[0] ?? null;
    return deliveryReports.find((r) => r.announcementId === selectedReportId) ?? deliveryReports[0] ?? null;
  }, [deliveryReports, selectedReportId]);

  const filteredRecipients = useMemo(() => {
    if (!currentReport) return [];
    return currentReport.recipients.filter((rec) => {
      const matchesSearch =
        recipientSearch === '' ||
        rec.name.toLowerCase().includes(recipientSearch.toLowerCase()) ||
        (rec.email && rec.email.toLowerCase().includes(recipientSearch.toLowerCase())) ||
        (rec.phone && rec.phone.includes(recipientSearch));

      if (!matchesSearch) return false;

      if (recipientFilter === 'delivered') return rec.deliveryState === 'delivered';
      if (recipientFilter === 'read') return rec.isRead;
      if (recipientFilter === 'acknowledged') return Boolean(rec.isAcknowledged);
      if (recipientFilter === 'pending_ack') return !rec.isAcknowledged && rec.deliveryState === 'delivered';
      if (recipientFilter === 'failed') return rec.deliveryState === 'failed';
      return true;
    });
  }, [currentReport, recipientFilter, recipientSearch]);

  if (deliveryReports.length === 0) {
    return (
      <Card className="border shadow-sm">
        <CardContent className="py-16 text-center text-muted-foreground">
          <Radio className="h-10 w-10 text-muted-foreground/40 mx-auto mb-3" />
          <h3 className="text-base font-semibold text-foreground">No Delivery Reports Available</h3>
          <p className="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
            Once announcements are published to the community, channel delivery telemetry, recipient read receipts, and explicit acknowledgements will be audited here.
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {/* Selector & Header Card */}
      <Card className="border shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <CardTitle className="text-xl flex items-center gap-2">
                <Radio className="h-5 w-5 text-primary" />
                <span>Delivery & Telemetry Report</span>
              </CardTitle>
              <CardDescription className="text-xs">
                Audits queued, sent, and failed transmissions with channel-specific status, verified recipient read receipts, and explicit acknowledgements.
              </CardDescription>
            </div>

            <div className="w-full sm:w-72">
              <Select
                value={currentReport?.announcementId ? String(currentReport.announcementId) : ''}
                onValueChange={(val) => onSelectReport(Number(val))}
              >
                <SelectTrigger className="h-9 text-xs">
                  <SelectValue placeholder="Select announcement..." />
                </SelectTrigger>
                <SelectContent>
                  {deliveryReports.map((r) => (
                    <SelectItem key={r.announcementId} value={String(r.announcementId)}>
                      {r.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardHeader>

        {currentReport && (
          <CardContent className="space-y-6">
            {/* Announcement Meta */}
            <div className="p-4 rounded-xl border bg-muted/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <h3 className="text-base font-bold text-foreground">{currentReport.title}</h3>
                <div className="flex flex-wrap items-center gap-2 mt-1">
                  <Badge variant="outline" className="text-[10px] uppercase font-mono capitalize">
                    {currentReport.category}
                  </Badge>
                  <Badge variant="outline" className="text-[10px] font-mono capitalize">
                    {currentReport.priority}
                  </Badge>
                  {currentReport.requiresAcknowledgement && (
                    <Badge variant="outline" className="text-[10px] border-indigo-500/40 text-indigo-600 bg-indigo-500/10 font-semibold flex items-center gap-1">
                      <ShieldCheck className="h-3 w-3 text-indigo-600" />
                      <span>Acknowledgement Mandated</span>
                    </Badge>
                  )}
                  {currentReport.publishedAt && (
                    <span className="text-xs text-muted-foreground">
                      Published: <ClientFormattedDate date={currentReport.publishedAt} formatString="MMM d, yyyy h:mm a" />
                    </span>
                  )}
                  {currentReport.acknowledgementDeadline && (
                    <span className="text-xs text-amber-600 dark:text-amber-400 font-medium">
                      &bull; Ack Deadline: <ClientFormattedDate date={currentReport.acknowledgementDeadline} formatString="MMM d, yyyy h:mm a" />
                    </span>
                  )}
                </div>
              </div>
            </div>

            {/* Provider Acceptance vs Read Proof vs Explicit Acknowledgement Distinction Callout */}
            <div className="p-4 rounded-xl border border-sky-500/30 bg-sky-500/10 flex items-start gap-3">
              <Info className="h-5 w-5 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" />
              <div className="space-y-1 text-xs">
                <h4 className="font-semibold text-sky-900 dark:text-sky-300">
                  Proof of Receipt vs Explicit Acknowledgement Standard
                </h4>
                <p className="text-sky-800/90 dark:text-sky-300/90 leading-relaxed">
                  <strong>&bull; Provider Acceptance (Transport Layer):</strong> Confirms that the external provider gateway (SMTP mail server, Twilio SMS carrier, or In-App dispatcher) accepted the payload for delivery.
                  <br />
                  <strong>&bull; Recipient Proof (Application Layer):</strong> Verified confirmation that the resident opened their notification center and recorded an in-app read receipt. <em>Reading does not constitute agreement or consent.</em>
                  <br />
                  <strong>&bull; Explicit Acknowledgement (Compliance Layer):</strong> Formal resident sign-off confirming understanding and compliance with critical notices (e.g. water interruptions, office closures, security protocols) with recorded timestamp and optional resident remarks.
                </p>
              </div>
            </div>

            {/* Mandated Acknowledgement Alert Banner */}
            {currentReport.requiresAcknowledgement && (
              <div className="p-3.5 rounded-xl border border-indigo-500/30 bg-indigo-500/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div className="flex items-center gap-2">
                  <ShieldCheck className="h-4 w-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                  <span className="font-semibold text-indigo-950 dark:text-indigo-200">
                    Mandatory Sign-off Notice: Explicit acknowledgement is enforced for this broadcast.
                  </span>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <Badge variant="outline" className="border-indigo-500/40 text-indigo-600 bg-indigo-500/10 font-mono text-[11px] px-2 py-0.5">
                    {currentReport.acknowledgedCount ?? 0} Signed ({currentReport.totalRecipients > 0 ? Math.round(((currentReport.acknowledgedCount ?? 0) / currentReport.totalRecipients) * 100) : 0}%)
                  </Badge>
                  <Badge variant="outline" className="border-amber-500/40 text-amber-600 bg-amber-500/10 font-mono text-[11px] px-2 py-0.5">
                    {currentReport.pendingAcknowledgementCount ?? 0} Pending
                  </Badge>
                </div>
              </div>
            )}

            {/* KPI Metrics */}
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
              <Card className="p-3 border bg-card/60">
                <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total Dispatched</span>
                <div className="text-2xl font-bold mt-1">{currentReport.totalRecipients}</div>
                <span className="text-[10px] text-muted-foreground">Audited recipients</span>
              </Card>

              <Card className="p-3 border bg-card/60">
                <span className="text-[11px] font-medium text-emerald-600 uppercase tracking-wider">Provider Accepted</span>
                <div className="text-2xl font-bold text-emerald-600 mt-1">
                  {currentReport.deliveredCount}{' '}
                  <span className="text-xs text-muted-foreground font-normal">
                    ({currentReport.totalRecipients > 0 ? Math.round((currentReport.deliveredCount / currentReport.totalRecipients) * 100) : 0}%)
                  </span>
                </div>
                <span className="text-[10px] text-muted-foreground">Delivered to transport</span>
              </Card>

              <Card className="p-3 border bg-sky-500/5 border-sky-500/20">
                <span className="text-[11px] font-medium text-sky-600 dark:text-sky-400 uppercase tracking-wider">Verified Read Proof</span>
                <div className="text-2xl font-bold text-sky-600 dark:text-sky-400 mt-1">
                  {currentReport.readCount}{' '}
                  <span className="text-xs text-muted-foreground font-normal">
                    ({currentReport.totalRecipients > 0 ? Math.round((currentReport.readCount / currentReport.totalRecipients) * 100) : 0}%)
                  </span>
                </div>
                <span className="text-[10px] text-muted-foreground">Confirmed user opens</span>
              </Card>

              <Card className="p-3 border bg-indigo-500/5 border-indigo-500/20">
                <span className="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Explicit Sign-offs</span>
                <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                  {currentReport.acknowledgedCount ?? 0}{' '}
                  <span className="text-xs text-muted-foreground font-normal">
                    ({currentReport.totalRecipients > 0 ? Math.round(((currentReport.acknowledgedCount ?? 0) / currentReport.totalRecipients) * 100) : 0}%)
                  </span>
                </div>
                <span className="text-[10px] text-muted-foreground">
                  {currentReport.requiresAcknowledgement ? `${currentReport.pendingAcknowledgementCount ?? 0} pending` : 'Optional / not required'}
                </span>
              </Card>

              <Card className="p-3 border bg-destructive/5 border-destructive/20">
                <span className="text-[11px] font-medium text-destructive uppercase tracking-wider">Delivery Failures</span>
                <div className="text-2xl font-bold text-destructive mt-1">{currentReport.failedCount}</div>
                <span className="text-[10px] text-muted-foreground">Undeliverable / rejected</span>
              </Card>
            </div>

            {/* Recipient Telemetry Table */}
            <div className="space-y-3">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                  <h4 className="text-sm font-bold text-foreground">Recipient Delivery & Acknowledgement Audit</h4>
                  <Badge variant="secondary" className="font-mono text-xs">
                    {filteredRecipients.length} Records
                  </Badge>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <div className="relative w-48">
                    <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                    <Input
                      placeholder="Search recipient..."
                      value={recipientSearch}
                      onChange={(e) => setRecipientSearch(e.target.value)}
                      className="pl-8 h-8 text-xs"
                    />
                  </div>

                  <Select value={recipientFilter} onValueChange={(val: any) => setRecipientFilter(val)}>
                    <SelectTrigger className="h-8 w-44 text-xs">
                      <SelectValue placeholder="All States" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All States</SelectItem>
                      <SelectItem value="delivered">Delivered Only</SelectItem>
                      <SelectItem value="read">Read Only</SelectItem>
                      <SelectItem value="acknowledged">Acknowledged Only</SelectItem>
                      <SelectItem value="pending_ack">Pending Ack Only</SelectItem>
                      <SelectItem value="failed">Failed Only</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="rounded-xl border overflow-hidden">
                <Table>
                  <TableHeader className="bg-muted/40">
                    <TableRow>
                      <TableHead>Recipient</TableHead>
                      <TableHead>Contact Info</TableHead>
                      <TableHead>Attempted Channels</TableHead>
                      <TableHead>Provider Status</TableHead>
                      <TableHead>Verified Read Proof</TableHead>
                      <TableHead>Explicit Acknowledgement</TableHead>
                      <TableHead>Delivered At</TableHead>
                      <TableHead>Diagnostics</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredRecipients.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={8} className="text-center py-8 text-xs text-muted-foreground">
                          No recipients match the specified filter.
                        </TableCell>
                      </TableRow>
                    ) : (
                      filteredRecipients.map((rec) => (
                        <TableRow key={rec.id} className="hover:bg-muted/20">
                          <TableCell className="font-medium text-xs">
                            {rec.name}
                          </TableCell>

                          <TableCell className="text-xs text-muted-foreground">
                            <div>{rec.email || '—'}</div>
                            {rec.phone && <div className="text-[11px] text-muted-foreground/70">{rec.phone}</div>}
                          </TableCell>

                          <TableCell>
                            <div className="flex flex-wrap items-center gap-1">
                              {rec.attemptedChannels.map((ch) => (
                                <Badge key={ch} variant="outline" className="text-[9px] uppercase font-mono">
                                  {ch}
                                </Badge>
                              ))}
                            </div>
                          </TableCell>

                          <TableCell>
                            {rec.deliveryState === 'delivered' ? (
                              <Badge variant="outline" className="text-[10px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                                Delivered
                              </Badge>
                            ) : rec.deliveryState === 'failed' ? (
                              <Badge variant="outline" className="text-[10px] border-destructive/40 text-destructive bg-destructive/10">
                                Failed
                              </Badge>
                            ) : (
                              <Badge variant="outline" className="text-[10px] border-amber-500/40 text-amber-600 bg-amber-500/10">
                                Queued
                              </Badge>
                            )}
                          </TableCell>

                          <TableCell>
                            {rec.isRead ? (
                              <div className="space-y-0.5">
                                <Badge variant="outline" className="text-[10px] border-sky-500/40 text-sky-600 bg-sky-500/10 flex items-center gap-1 w-fit">
                                  <CheckCircle2 className="h-3 w-3 text-sky-600" />
                                  <span>Verified Read</span>
                                </Badge>
                                {rec.readAt && (
                                  <span className="text-[10px] text-muted-foreground block">
                                    <ClientFormattedDate date={rec.readAt} formatString="MMM d, h:mm a" />
                                  </span>
                                )}
                              </div>
                            ) : (
                              <span className="text-[11px] text-muted-foreground">Unread</span>
                            )}
                          </TableCell>

                          <TableCell>
                            {rec.isAcknowledged ? (
                              <div className="space-y-0.5">
                                <Badge variant="outline" className="text-[10px] border-indigo-500/40 text-indigo-600 bg-indigo-500/10 flex items-center gap-1 w-fit">
                                  <ShieldCheck className="h-3 w-3 text-indigo-600" />
                                  <span>Acknowledged</span>
                                </Badge>
                                {rec.acknowledgedAt && (
                                  <span className="text-[10px] text-muted-foreground block">
                                    <ClientFormattedDate date={rec.acknowledgedAt} formatString="MMM d, h:mm a" />
                                  </span>
                                )}
                                {rec.acknowledgementNote && (
                                  <span className="text-[10px] italic text-muted-foreground block line-clamp-1 max-w-[140px]" title={rec.acknowledgementNote}>
                                    "{rec.acknowledgementNote}"
                                  </span>
                                )}
                              </div>
                            ) : currentReport.requiresAcknowledgement ? (
                              <Badge variant="outline" className="text-[10px] border-amber-500/40 text-amber-600 bg-amber-500/10 flex items-center gap-1 w-fit">
                                <AlertTriangle className="h-3 w-3 text-amber-600" />
                                <span>Pending Ack</span>
                              </Badge>
                            ) : (
                              <span className="text-[11px] text-muted-foreground/60">—</span>
                            )}
                          </TableCell>

                          <TableCell className="text-xs text-muted-foreground">
                            {rec.deliveredAt ? (
                              <ClientFormattedDate date={rec.deliveredAt} formatString="MMM d, h:mm a" />
                            ) : (
                              '—'
                            )}
                          </TableCell>

                          <TableCell className="text-xs">
                            {rec.failureDetails ? (
                              <span className="text-destructive font-mono text-[10px]" title={rec.failureDetails}>
                                {rec.failureDetails}
                              </span>
                            ) : (
                              <span className="text-muted-foreground text-[11px]">Normal</span>
                            )}
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </div>
            </div>
          </CardContent>
        )}
      </Card>
    </div>
  );
}

// =========================================================================
// RESIDENT VIEW: Published Notices Card
// =========================================================================
function PublishedNoticesCard({
  notifications,
  canBroadcast,
}: {
  notifications: Paginated<NotificationRow>;
  canBroadcast: boolean;
}) {
  const { toast } = useToast();
  const [markingReadId, setMarkingReadId] = useState<number | null>(null);
  const [acknowledgingNotice, setAcknowledgingNotice] = useState<NotificationRow | null>(null);
  const [ackNote, setAckNote] = useState<string>('');
  const [isSubmittingAck, setIsSubmittingAck] = useState<boolean>(false);

  const handleMarkAsRead = (id: number) => {
    setMarkingReadId(id);
    router.post(
      `/dashboard/notifications/${id}/read`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Notice Marked as Read',
            description: 'Your verified read receipt has been registered.',
          });
        },
        onError: () => {
          toast({
            variant: 'destructive',
            title: 'Error',
            description: 'Could not register read receipt. Please try again.',
          });
        },
        onFinish: () => setMarkingReadId(null),
      }
    );
  };

  const handleSubmitAcknowledgement = (e: React.FormEvent) => {
    e.preventDefault();
    if (!acknowledgingNotice) return;

    setIsSubmittingAck(true);
    router.post(
      `/dashboard/notifications/${acknowledgingNotice.id}/acknowledge`,
      { note: ackNote },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Acknowledgement Confirmed',
            description: `You have formally acknowledged "${acknowledgingNotice.title}".`,
          });
          setAcknowledgingNotice(null);
          setAckNote('');
        },
        onError: (errors) => {
          toast({
            variant: 'destructive',
            title: 'Could not submit acknowledgement',
            description: Object.values(errors)[0] ?? 'An error occurred.',
          });
        },
        onFinish: () => setIsSubmittingAck(false),
      }
    );
  };

  return (
    <>
      <Card className="border shadow-sm">
        <CardHeader>
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <CardTitle className="text-lg flex items-center gap-2">
                <Bell className="h-5 w-5 text-primary" />
                <span>Community Announcements & Notices</span>
              </CardTitle>
              <CardDescription>
                Notices and broadcasts addressed to you and your community.
                {notifications.total > 0 &&
                  ` Showing ${notifications.from}–${notifications.to} of ${notifications.total}.`}
              </CardDescription>
            </div>
            <div className="text-xs text-muted-foreground flex items-center gap-1.5 bg-muted/40 px-3 py-1.5 rounded-lg border">
              <Info className="h-3.5 w-3.5 text-primary shrink-0" />
              <span>Reading a notice records delivery receipt; explicit acknowledgement is recorded separately.</span>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-6">
          {notifications.data.length === 0 && (
            <div className="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
              <Bell className="h-9 w-9 text-muted-foreground/60" />
              <p className="text-sm font-medium">No published announcements found.</p>
            </div>
          )}

          {notifications.data.map((notification) => (
            <div
              key={notification.id}
              className={`flex gap-4 p-5 rounded-xl border transition-colors ${
                notification.requiresAcknowledgement && !notification.isAcknowledged
                  ? 'border-amber-500/40 bg-amber-500/[0.03]'
                  : 'bg-card/60'
              }`}
            >
              <Avatar className="h-10 w-10 shrink-0">
                <AvatarFallback className="bg-primary/10 text-primary font-bold text-sm">
                  {initial(notification.author)}
                </AvatarFallback>
              </Avatar>

              <div className="flex-1 space-y-3">
                {/* Notice Top Header */}
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <h4 className="font-semibold text-base text-foreground">{notification.title}</h4>
                    {notification.category && (
                      <Badge variant="outline" className="text-[10px] uppercase font-mono capitalize">
                        {notification.category}
                      </Badge>
                    )}
                    {notification.priority && (
                      <Badge
                        variant="outline"
                        className={`text-[10px] font-mono capitalize ${
                          notification.priority === 'urgent' || notification.priority === 'critical'
                            ? 'border-destructive/40 text-destructive bg-destructive/10'
                            : 'text-muted-foreground'
                        }`}
                      >
                        {notification.priority}
                      </Badge>
                    )}
                  </div>

                  <div className="flex items-center gap-2">
                    {/* Read Status Badge or Action */}
                    {notification.isRead ? (
                      <Badge variant="outline" className="text-[10px] border-sky-500/40 text-sky-600 bg-sky-500/10 flex items-center gap-1">
                        <CheckCircle2 className="h-3 w-3 text-sky-600" />
                        <span>Read</span>
                        {notification.readAt && (
                          <span className="text-[9px] text-sky-700/80 font-normal">
                            (<ClientFormattedDate date={notification.readAt} formatString="MMM d, h:mm a" />)
                          </span>
                        )}
                      </Badge>
                    ) : (
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleMarkAsRead(notification.id)}
                        disabled={markingReadId === notification.id}
                        className="h-6 text-[11px] px-2 text-muted-foreground hover:text-foreground flex items-center gap-1 border border-border/60"
                        title="Mark as read without formal acknowledgement"
                      >
                        {markingReadId === notification.id ? (
                          <Loader2 className="h-3 w-3 animate-spin" />
                        ) : (
                          <Eye className="h-3 w-3" />
                        )}
                        <span>Mark as Read</span>
                      </Button>
                    )}

                    <span className="text-xs text-muted-foreground font-mono">
                      <ClientFormattedDate
                        date={notification.timestamp}
                        formatString="MMM d, yyyy h:mm a"
                      />
                    </span>
                  </div>
                </div>

                {/* Content */}
                <p className="text-sm text-foreground/90 whitespace-pre-line leading-relaxed">
                  {notification.content}
                </p>

                {/* Mandatory Acknowledgement Banner */}
                {notification.requiresAcknowledgement && (
                  <div className="pt-1">
                    {notification.isAcknowledged ? (
                      <div className="p-3.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 flex items-start gap-3">
                        <ShieldCheck className="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" />
                        <div className="text-xs space-y-1">
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold text-emerald-950 dark:text-emerald-200">
                              Formally Acknowledged
                            </span>
                            {notification.acknowledgedAt && (
                              <span className="text-[11px] text-emerald-700 dark:text-emerald-400">
                                on <ClientFormattedDate date={notification.acknowledgedAt} formatString="MMM d, yyyy h:mm a" />
                              </span>
                            )}
                          </div>
                          {notification.acknowledgementNote && (
                            <p className="text-[11px] text-emerald-800 dark:text-emerald-300 italic">
                              "{notification.acknowledgementNote}"
                            </p>
                          )}
                          <p className="text-[10px] text-emerald-700/80 dark:text-emerald-400/85">
                            Your acknowledgement receipt is officially recorded for community compliance auditing.
                          </p>
                        </div>
                      </div>
                    ) : (
                      <div className="p-3.5 rounded-xl border border-amber-500/40 bg-amber-500/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-start gap-2.5">
                          <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0 mt-0.5" />
                          <div className="text-xs space-y-0.5">
                            <div className="font-semibold text-amber-950 dark:text-amber-200">
                              Action Required: Notice Requires Formal Acknowledgement
                            </div>
                            <p className="text-[11px] text-amber-900/80 dark:text-amber-300/80 leading-relaxed">
                              Reading this notice records delivery, but does not indicate your agreement or formal acknowledgement.
                              {notification.acknowledgementDeadline && (
                                <> Acknowledgement deadline: <strong className="text-amber-950 dark:text-amber-200"><ClientFormattedDate date={notification.acknowledgementDeadline} formatString="MMM d, yyyy h:mm a" /></strong>.</>
                              )}
                            </p>
                          </div>
                        </div>

                        <Button
                          size="sm"
                          onClick={() => {
                            setAcknowledgingNotice(notification);
                            setAckNote('');
                          }}
                          className="h-8 text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shrink-0 flex items-center gap-1.5 shadow-sm"
                        >
                          <ShieldCheck className="h-3.5 w-3.5" />
                          <span>Acknowledge Notice</span>
                        </Button>
                      </div>
                    )}
                  </div>
                )}

                {/* Notice Footer Meta */}
                <div className="mt-2 flex flex-wrap items-center gap-2 pt-2 border-t border-border/50">
                  <span className="text-xs text-muted-foreground font-medium">
                    By {notification.author}
                  </span>

                  {notification.targetType && (
                    <Badge variant="outline" className="text-[10px] uppercase font-mono">
                      {notification.targetType.replace('_', ' ')}
                    </Badge>
                  )}

                  {canBroadcast &&
                    (notification.targetRoles?.length ? (
                      notification.targetRoles.map((role) => (
                        <Badge key={role} variant="secondary" className="text-[10px]">
                          {role}
                        </Badge>
                      ))
                    ) : (
                      <Badge variant="outline" className="text-[10px]">
                        Everyone
                      </Badge>
                    ))}

                  {notification.channels && notification.channels.length > 0 && (
                    <div className="flex items-center gap-1.5 ml-auto">
                      {notification.channels.map((ch) => (
                        <span
                          key={ch}
                          title={`Delivered via ${ch.toUpperCase()}`}
                          className="p-1 rounded bg-muted/60 text-muted-foreground text-[10px] flex items-center gap-1"
                        >
                          {ch === 'email' && <Mail className="h-3 w-3 text-sky-500" />}
                          {ch === 'sms' && <Smartphone className="h-3 w-3 text-emerald-500" />}
                          {ch === 'whatsapp' && <MessageSquare className="h-3 w-3 text-green-600" />}
                          {ch === 'in_app' && <Bell className="h-3 w-3 text-primary" />}
                          <span className="capitalize">{ch.replace('_', ' ')}</span>
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </div>
          ))}

          {notifications.links.length > 3 && (
            <nav
              className="flex flex-wrap items-center justify-center gap-1 pt-4"
              aria-label="Pagination"
            >
              {notifications.links.map((link, index) => (
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

      {/* Formal Acknowledgement Modal Dialog */}
      <Dialog
        open={acknowledgingNotice !== null}
        onOpenChange={(open) => {
          if (!open) {
            setAcknowledgingNotice(null);
            setAckNote('');
          }
        }}
      >
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-base">
              <ShieldCheck className="h-5 w-5 text-indigo-600" />
              <span>Acknowledge Notice</span>
            </DialogTitle>
            <DialogDescription className="text-xs">
              Confirm that you have received and reviewed this community announcement.
            </DialogDescription>
          </DialogHeader>

          {acknowledgingNotice && (
            <form onSubmit={handleSubmitAcknowledgement} className="space-y-4 pt-1">
              <div className="p-3 rounded-lg border bg-muted/30 space-y-1">
                <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                  Notice Details
                </span>
                <h5 className="font-semibold text-sm text-foreground">{acknowledgingNotice.title}</h5>
                <p className="text-xs text-muted-foreground line-clamp-3">
                  {acknowledgingNotice.content}
                </p>
              </div>

              <div className="p-3 rounded-lg border border-indigo-500/30 bg-indigo-500/10 flex items-start gap-2.5 text-xs">
                <Info className="h-4 w-4 text-indigo-600 shrink-0 mt-0.5" />
                <div className="space-y-1 text-indigo-950 dark:text-indigo-200">
                  <p className="leading-relaxed">
                    By submitting this acknowledgement, you confirm that you have read and understood the instructions in this notice.
                  </p>
                  <p className="text-[10px] text-indigo-800/80 dark:text-indigo-300/80 italic">
                    Ordinary read receipts only verify delivery; this step enters a signed acknowledgement into community records.
                  </p>
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-medium text-foreground">
                  Optional Note or Remarks
                </label>
                <Textarea
                  placeholder="e.g., Unit 14B aware and prepared; valves confirmed closed."
                  value={ackNote}
                  onChange={(e) => setAckNote(e.target.value)}
                  className="text-xs min-h-[70px] resize-none"
                  maxLength={500}
                />
                <span className="text-[10px] text-muted-foreground block text-right">
                  {ackNote.length} / 500 characters
                </span>
              </div>

              <DialogFooter className="gap-2 sm:gap-0 pt-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setAcknowledgingNotice(null)}
                  disabled={isSubmittingAck}
                  className="h-8 text-xs"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingAck}
                  className="h-8 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1.5 shadow-sm"
                >
                  {isSubmittingAck ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <ShieldCheck className="h-3.5 w-3.5" />
                  )}
                  <span>{isSubmittingAck ? 'Recording...' : 'Submit Acknowledgement'}</span>
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}
