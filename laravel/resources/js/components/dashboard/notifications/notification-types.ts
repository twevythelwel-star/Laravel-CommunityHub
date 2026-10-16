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
