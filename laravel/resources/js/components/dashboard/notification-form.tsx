import { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Form } from '@/components/ui/form';
import { Separator } from '@/components/ui/separator';
import { useToast } from '@/hooks/use-toast';
import { Calendar, Loader2, Send } from 'lucide-react';
import { AIConsentDialog } from '@/components/ai-consent-dialog';
import { useAuth } from '@/context/auth-context';
import { describeHttpError } from '@/lib/http';
import {
  blankAnnouncement,
  formSchema,
  type AudiencePreview,
  type ChannelStatus,
  type NotificationFormValues,
} from '@/components/dashboard/notifications/notification-form-schema';
import { AnnouncementMessageSection } from '@/components/dashboard/notifications/announcement-message-section';
import { AudienceTargetingSection } from '@/components/dashboard/notifications/audience-targeting-section';
import { DeliveryChannelsSection } from '@/components/dashboard/notifications/delivery-channels-section';
import { DispatchTimingSection } from '@/components/dashboard/notifications/dispatch-timing-section';
import { PreSendReviewDialog } from '@/components/dashboard/notifications/pre-send-review-dialog';

export type { ChannelStatus, NotificationFormValues };

type NotificationFormProps = {
  roles: string[];
  community: string;
  aiEnabled: boolean;
  streets?: string[];
  blocks?: string[];
  sections?: string[];
  notificationChannels?: Record<string, ChannelStatus>;
  onSuccess?: () => void;
};

export function NotificationForm({
  roles,
  community,
  aiEnabled,
  streets = [],
  blocks = [],
  sections = [],
  notificationChannels = {},
  onSuccess,
}: NotificationFormProps) {
  const { user } = useAuth();
  const { toast } = useToast();

  const [isGenerating, setGenerating] = useState(false);
  const [isSubmitting, setSubmitting] = useState(false);
  const [showConsent, setShowConsent] = useState(false);

  // Live audience estimation state
  const [preview, setPreview] = useState<AudiencePreview | null>(null);
  const [isPreviewLoading, setPreviewLoading] = useState(false);

  // Pre-Send Preview & Safeguards Modal State
  const [showPreSendReview, setShowPreSendReview] = useState(false);
  const [pendingValues, setPendingValues] = useState<NotificationFormValues | null>(null);

  const form = useForm<NotificationFormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: blankAnnouncement,
  });

  const targetType = form.watch('targetType');
  const targetStreet = form.watch('targetStreet');
  const targetBlock = form.watch('targetBlock');
  const targetSection = form.watch('targetSection');
  const customStreets = form.watch('customStreets');
  const isScheduled = form.watch('isScheduled');

  // Live fetch audience preview
  const fetchAudiencePreview = useCallback(async () => {
    setPreviewLoading(true);
    try {
      const criteria: Record<string, any> = {};
      if (targetType === 'street') {
        criteria.street = targetStreet;
      } else if (targetType === 'block') {
        criteria.block = targetBlock;
      } else if (targetType === 'section') {
        criteria.section = targetSection;
      } else if (targetType === 'custom_group') {
        criteria.streets = customStreets;
      }

      const { data } = await axios.post<AudiencePreview>(
        '/dashboard/notifications/preview-audience',
        {
          target_type: targetType,
          target_criteria: criteria,
        },
      );
      setPreview(data);
    } catch {
      setPreview(null);
    } finally {
      setPreviewLoading(false);
    }
  }, [targetType, targetStreet, targetBlock, targetSection, customStreets]);

  useEffect(() => {
    const timer = setTimeout(() => {
      fetchAudiencePreview();
    }, 250);
    return () => clearTimeout(timer);
  }, [fetchAudiencePreview]);

  async function handleGenerate() {
    if (!user?.aiConsent) {
      setShowConsent(true);
      return;
    }
    await executeGenerate();
  }

  async function executeGenerate() {
    const document = form.getValues('document');
    if (!document?.trim()) {
      toast({
        variant: 'destructive',
        title: 'Nothing to summarise',
        description: 'Paste the document you want turned into notice items first.',
      });
      return;
    }

    setGenerating(true);
    try {
      const { data } = await axios.post<{ notificationItems: string[]; source: string }>(
        '/dashboard/notifications/suggest-audience',
        { document },
      );

      form.setValue('content', data.notificationItems.map((item) => '- ' + item).join('\n'), {
        shouldValidate: true,
      });

      toast({
        title: 'Draft ready',
        description:
          data.source === 'gemini'
            ? 'Gemini drafted the notice items. Review them before publishing.'
            : 'Placeholder items were inserted. Edit them before publishing.',
      });
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Generation failed',
        description: describeHttpError(error),
      });
    } finally {
      setGenerating(false);
    }
  }

  function onSubmit(values: NotificationFormValues) {
    if (values.isScheduled && !values.scheduledFor) {
      toast({
        variant: 'destructive',
        title: 'Scheduled Date Required',
        description: 'Please pick a future date and time for scheduled delivery.',
      });
      return;
    }

    // Safeguard: Preview exact message and recipient metrics before sending
    setPendingValues(values);
    setShowPreSendReview(true);
  }

  function executeDispatch(values: NotificationFormValues) {
    setSubmitting(true);

    const criteria: Record<string, any> = {};
    if (values.targetType === 'street') {
      criteria.street = values.targetStreet;
    } else if (values.targetType === 'block') {
      criteria.block = values.targetBlock;
    } else if (values.targetType === 'section') {
      criteria.section = values.targetSection;
    } else if (values.targetType === 'custom_group') {
      criteria.streets = values.customStreets;
    }

    const activeChannels = values.channels.includes('in_app')
      ? values.channels
      : ['in_app', ...values.channels];

    router.post(
      '/dashboard/notifications',
      {
        title: values.title,
        content: values.content,
        category: values.category,
        priority: values.priority,
        target_type: values.targetType,
        target_criteria: Object.keys(criteria).length > 0 ? criteria : null,
        target_roles: values.targetType === 'roles' ? values.targetRoles : [],
        channels: activeChannels,
        scheduled_for: values.isScheduled ? values.scheduledFor : null,
        expires_at: values.expiresAt ? values.expiresAt : null,
        requires_acknowledgement: values.requiresAcknowledgement ? 1 : 0,
        acknowledgement_deadline: values.requiresAcknowledgement && values.acknowledgementDeadline ? values.acknowledgementDeadline : null,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          form.reset(blankAnnouncement);
          toast({
            title: values.isScheduled ? 'Broadcast Scheduled' : 'Announcement Dispatched',
            description: values.isScheduled
              ? `Delivery scheduled for ${values.scheduledFor}.`
              : `Dispatched to ${values.targetType.replace('_', ' ')} across selected channels.`,
          });
          if (onSuccess) onSuccess();
        },
        onError: (errors) => {
          toast({
            variant: 'destructive',
            title: 'Could not dispatch announcement',
            description: Object.values(errors)[0] ?? 'Please check the form and try again.',
          });
        },
        onFinish: () => setSubmitting(false),
      },
    );
  }

  const loadExampleAnnouncement = () => {
    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 2);
    futureDate.setHours(9, 0, 0, 0);
    const formattedSchedule = futureDate.toISOString().slice(0, 16);

    form.reset({
      title: 'Community Office Closure',
      category: 'general',
      priority: 'normal',
      targetType: 'entire_community',
      targetStreet: '',
      targetBlock: '',
      targetSection: '',
      targetRoles: [],
      customStreets: [],
      content:
        'Please be advised that the community management office will be closed on the designated public holiday. Regular office hours will resume on the next business day.\n\nFor urgent security matters, please use the established community security contact procedure.',
      channels: ['in_app', 'email'],
      isScheduled: true,
      scheduledFor: formattedSchedule,
      expiresAt: '',
    });

    toast({
      title: 'Example Announcement Loaded',
      description: 'Loaded "Community Office Closure" (Administrative notice · Entire community · Scheduled).',
    });
  };

  return (
    <>
      {showConsent && (
        <AIConsentDialog
          onAccept={() => {
            setShowConsent(false);
            executeGenerate();
          }}
          onDecline={() => setShowConsent(false)}
        />
      )}

      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
          <AnnouncementMessageSection
            form={form}
            community={community}
            aiEnabled={aiEnabled}
            isGenerating={isGenerating}
            onGenerate={handleGenerate}
            onLoadExample={loadExampleAnnouncement}
          />

          <Separator />

          <AudienceTargetingSection
            form={form}
            roles={roles}
            streets={streets}
            blocks={blocks}
            sections={sections}
            preview={preview}
            isPreviewLoading={isPreviewLoading}
          />

          <Separator />

          <DeliveryChannelsSection form={form} notificationChannels={notificationChannels} />

          <Separator />

          <DispatchTimingSection form={form} />

          <div className="flex items-center justify-end gap-3 pt-2">
            <Button
              type="submit"
              disabled={isSubmitting}
              className="px-6 h-10 font-semibold flex items-center gap-2"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>Processing...</span>
                </>
              ) : isScheduled ? (
                <>
                  <Calendar className="h-4 w-4" />
                  <span>Schedule Announcement</span>
                </>
              ) : (
                <>
                  <Send className="h-4 w-4" />
                  <span>Publish & Dispatch Now</span>
                </>
              )}
            </Button>
          </div>
        </form>
      </Form>

      <PreSendReviewDialog
        open={showPreSendReview}
        onOpenChange={setShowPreSendReview}
        values={pendingValues}
        community={community}
        preview={preview}
        isSubmitting={isSubmitting}
        onConfirm={executeDispatch}
      />
    </>
  );
}
