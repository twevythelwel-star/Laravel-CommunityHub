import { useState, useMemo } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
  AlertTriangle,
  ShieldCheck,
  Search,
  Info,
} from 'lucide-react';
import { ClientFormattedDate } from '@/components/client-formatted-date';
import type { DeliveryReport } from './notification-types';

export function DeliveryReportScreen({
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
