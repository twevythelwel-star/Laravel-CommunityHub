import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Bell, Radio, Send, Users, Layers } from 'lucide-react';
import { NotificationForm, ChannelStatus } from '@/components/dashboard/notification-form';
import { EmergencyBroadcastDialog } from '@/components/dashboard/emergency-broadcast-dialog';
import { useToast } from '@/hooks/use-toast';
import { AnnouncementDashboardScreen } from '@/components/dashboard/notifications/announcement-dashboard-screen';
import { AudiencePreviewScreen } from '@/components/dashboard/notifications/audience-preview-screen';
import { DeliveryReportScreen } from '@/components/dashboard/notifications/delivery-report-screen';
import { PublishedNoticesCard } from '@/components/dashboard/notifications/published-notices-card';
import type {
  NotificationRow,
  ScheduledAnnouncementRow,
  AnnouncementSummaryRow,
  SummaryStats,
  DeliveryReport,
  Paginated,
} from '@/components/dashboard/notifications/notification-types';

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
