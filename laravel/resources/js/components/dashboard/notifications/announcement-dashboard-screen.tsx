import { useState, useMemo } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
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
  Radio,
  CheckCircle2,
  ShieldCheck,
  Clock,
  Trash2,
  Send,
  Users,
  Search,
  Filter,
  Layers,
  Eye,
} from 'lucide-react';
import type { ChannelStatus } from './notification-form-schema';
import { ClientFormattedDate } from '@/components/client-formatted-date';
import type { AnnouncementSummaryRow, SummaryStats } from './notification-types';

export function AnnouncementDashboardScreen({
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
