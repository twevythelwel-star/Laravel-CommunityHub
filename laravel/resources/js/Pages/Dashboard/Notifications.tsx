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
  author: string;
  timestamp: string;
  targetRoles: string[] | null;
  targetType?: string | null;
  channels?: string[] | null;
  stats?: Record<string, number> | null;
};

export type ScheduledAnnouncementRow = {
  id: number;
  title: string;
  content: string;
  author: string;
  scheduledFor: string;
  targetType?: string | null;
  channels?: string[] | null;
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
  author: string;
  targetType: string;
  targetCriteria?: Record<string, any> | null;
  recipientCount: number;
  deliveredCount: number;
  failureCount: number;
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
};

export type DeliveryReport = {
  announcementId: number;
  title: string;
  category: string;
  priority: string;
  publishedAt: string | null;
  stats: Record<string, any> | null;
  totalRecipients: number;
  deliveredCount: number;
  failedCount: number;
  readCount: number;
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
        <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
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

          <Card className="p-3 border bg-card/60 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Drafts</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-muted-foreground">{summaryStats.drafts}</span>
              <FileText className="h-4 w-4 text-muted-foreground/60" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">Unpublished</span>
          </Card>

          <Card className="p-3 border bg-card/60 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Expired</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-slate-500">{summaryStats.expired}</span>
              <Calendar className="h-4 w-4 text-slate-400" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">Past expiry date</span>
          </Card>

          <Card className="p-3 border bg-primary/5 border-primary/20 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-primary uppercase tracking-wider">Recipients</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-primary">{summaryStats.totalRecipientsReached}</span>
              <Users className="h-4 w-4 text-primary" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">Unique reached</span>
          </Card>

          <Card className="p-3 border bg-destructive/5 border-destructive/20 flex flex-col justify-between">
            <span className="text-[11px] font-medium text-destructive uppercase tracking-wider">Failures</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-2xl font-bold text-destructive">{summaryStats.totalDeliveryFailures}</span>
              <AlertTriangle className="h-4 w-4 text-destructive" />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1">Delivery errors</span>
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
                            <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
                              <span className="text-emerald-600 dark:text-emerald-400 font-medium">{a.deliveredCount} delivered</span>
                              {a.failureCount > 0 && (
                                <span className="text-destructive font-medium">&bull; {a.failureCount} failed</span>
                              )}
                            </div>
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
  const [recipientFilter, setRecipientFilter] = useState<'all' | 'delivered' | 'read' | 'failed'>('all');
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
            Once announcements are published to the community, channel delivery telemetry and verified recipient read receipts will be audited here.
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
                Audits queued, sent, and failed transmissions with channel-specific status and verified recipient read receipts.
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
                  {currentReport.publishedAt && (
                    <span className="text-xs text-muted-foreground">
                      Published: <ClientFormattedDate date={currentReport.publishedAt} formatString="MMM d, yyyy h:mm a" />
                    </span>
                  )}
                </div>
              </div>
            </div>

            {/* Provider Acceptance vs Read Proof Distinction Callout */}
            <div className="p-4 rounded-xl border border-sky-500/30 bg-sky-500/10 flex items-start gap-3">
              <Info className="h-5 w-5 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" />
              <div className="space-y-1 text-xs">
                <h4 className="font-semibold text-sky-900 dark:text-sky-300">
                  Proof of Receipt vs Provider Acceptance Standard
                </h4>
                <p className="text-sky-800/90 dark:text-sky-300/90 leading-relaxed">
                  <strong>&bull; Provider Acceptance (Transport Layer):</strong> Confirms that the external provider gateway (SMTP mail server, Twilio SMS carrier, or In-App dispatcher) accepted the payload for delivery.
                  <br />
                  <strong>&bull; Recipient Proof (Application Layer):</strong> Verified cryptographic confirmation that the resident opened their notification center and recorded an in-app read receipt.
                </p>
              </div>
            </div>

            {/* KPI Metrics */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
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

              <Card className="p-3 border bg-primary/5 border-primary/20">
                <span className="text-[11px] font-medium text-primary uppercase tracking-wider">Verified Read Proof</span>
                <div className="text-2xl font-bold text-primary mt-1">
                  {currentReport.readCount}{' '}
                  <span className="text-xs text-muted-foreground font-normal">
                    ({currentReport.totalRecipients > 0 ? Math.round((currentReport.readCount / currentReport.totalRecipients) * 100) : 0}%)
                  </span>
                </div>
                <span className="text-[10px] text-muted-foreground">Confirmed user opens</span>
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
                  <h4 className="text-sm font-bold text-foreground">Recipient Delivery Audit</h4>
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
                    <SelectTrigger className="h-8 w-36 text-xs">
                      <SelectValue placeholder="All States" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All States</SelectItem>
                      <SelectItem value="delivered">Delivered Only</SelectItem>
                      <SelectItem value="read">Read Only</SelectItem>
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
                      <TableHead>Delivered At</TableHead>
                      <TableHead>Diagnostics</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredRecipients.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={7} className="text-center py-8 text-xs text-muted-foreground">
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
                              <Badge variant="outline" className="text-[10px] border-primary/40 text-primary bg-primary/10 flex items-center gap-1 w-fit">
                                <CheckCircle2 className="h-3 w-3 text-primary" />
                                <span>Verified Read</span>
                              </Badge>
                            ) : (
                              <span className="text-[11px] text-muted-foreground">Unread</span>
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
  return (
    <Card className="border shadow-sm">
      <CardHeader>
        <CardTitle className="text-lg">Community Announcements</CardTitle>
        <CardDescription>
          Notices and broadcasts addressed to you and your community.
          {notifications.total > 0 &&
            ` Showing ${notifications.from}–${notifications.to} of ${notifications.total}.`}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        {notifications.data.length === 0 && (
          <div className="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
            <Bell className="h-9 w-9 text-muted-foreground/60" />
            <p className="text-sm font-medium">No published announcements found.</p>
          </div>
        )}

        {notifications.data.map((notification) => (
          <div key={notification.id} className="flex gap-4 p-4 rounded-xl border bg-card/60 transition-colors">
            <Avatar className="h-10 w-10">
              <AvatarFallback className="bg-primary/10 text-primary font-bold text-sm">
                {initial(notification.author)}
              </AvatarFallback>
            </Avatar>
            <div className="flex-1">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h4 className="font-semibold text-base text-foreground">{notification.title}</h4>
                <span className="text-xs text-muted-foreground">
                  <ClientFormattedDate
                    date={notification.timestamp}
                    formatString="MMM d, yyyy h:mm a"
                  />
                </span>
              </div>
              <p className="text-sm text-muted-foreground mt-2 whitespace-pre-line leading-relaxed">
                {notification.content}
              </p>

              <div className="mt-3 flex flex-wrap items-center gap-2 pt-2 border-t border-border/50">
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
  );
}
