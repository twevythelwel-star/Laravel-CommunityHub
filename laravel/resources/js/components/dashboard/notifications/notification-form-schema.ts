import { z } from 'zod';

export const formSchema = z.object({
  title: z.string().min(1, 'Title is required').max(160, 'Title cannot exceed 160 characters'),
  document: z.string().optional(),
  content: z.string().min(1, 'Notification content is required').max(5000, 'Content cannot exceed 5000 characters'),
  category: z.enum(['general', 'maintenance', 'security', 'emergency', 'event', 'financial']).default('general'),
  priority: z.enum(['low', 'normal', 'urgent', 'critical']).default('normal'),
  targetType: z.enum(['entire_community', 'street', 'block', 'section', 'custom_group', 'roles']),
  targetStreet: z.string().optional(),
  targetBlock: z.string().optional(),
  targetSection: z.string().optional(),
  targetRoles: z.array(z.string()).default([]),
  customStreets: z.array(z.string()).default([]),
  channels: z.array(z.string()).default(['in_app']),
  isScheduled: z.boolean().default(false),
  scheduledFor: z.string().optional(),
  expiresAt: z.string().optional(),
  requiresAcknowledgement: z.boolean().default(false),
  acknowledgementDeadline: z.string().optional(),
});

export type NotificationFormValues = z.infer<typeof formSchema>;

/** An empty announcement: the form's starting state and what it resets to after sending. */
export const blankAnnouncement: NotificationFormValues = {
  title: '',
  document: '',
  content: '',
  category: 'general',
  priority: 'normal',
  targetType: 'entire_community',
  targetStreet: '',
  targetBlock: '',
  targetSection: '',
  targetRoles: [],
  customStreets: [],
  channels: ['in_app'],
  isScheduled: false,
  scheduledFor: '',
  expiresAt: '',
  requiresAcknowledgement: false,
  acknowledgementDeadline: '',
};

export type ChannelStatus = {
  channel: string;
  label: string;
  provider: string;
  configured: boolean;
  status: 'available' | 'unavailable';
};

export type AudiencePreview = {
  totalRecipients: number;
  uniqueRecipients: number;
  overlapCount: number;
  hasOverlaps: boolean;
  inAppCount: number;
  emailCount: number;
  phoneCount: number;
  sample?: Array<{
    id: number;
    name: string;
    street: string;
    lot: string;
    hasEmail: boolean;
    hasPhone: boolean;
  }>;
};
