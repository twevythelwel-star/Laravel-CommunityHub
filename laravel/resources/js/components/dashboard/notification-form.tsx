import { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useToast } from '@/hooks/use-toast';
import {
  Sparkles,
  Wand2,
  Users,
  MapPin,
  Building,
  Layers,
  ShieldCheck,
  Mail,
  MessageSquare,
  Smartphone,
  Bell,
  Calendar,
  Clock,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Send,
  Radio,
  ShieldAlert,
  Tag,
  AlertTriangle,
  Check,
  FileText,
} from 'lucide-react';
import { AIConsentDialog } from '@/components/ai-consent-dialog';
import { useAuth } from '@/context/auth-context';
import { describeHttpError } from '@/lib/http';

const formSchema = z.object({
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

export type ChannelStatus = {
  channel: string;
  label: string;
  provider: string;
  configured: boolean;
  status: 'available' | 'unavailable';
};

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

type AudiencePreview = {
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
  const [showSampleRoster, setShowSampleRoster] = useState(false);

  // Pre-Send Preview & Safeguards Modal State
  const [showPreSendReview, setShowPreSendReview] = useState(false);
  const [pendingValues, setPendingValues] = useState<NotificationFormValues | null>(null);

  const form = useForm<NotificationFormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
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
    },
  });

  const targetType = form.watch('targetType');
  const targetStreet = form.watch('targetStreet');
  const targetBlock = form.watch('targetBlock');
  const targetSection = form.watch('targetSection');
  const targetRoles = form.watch('targetRoles');
  const customStreets = form.watch('customStreets');
  const channels = form.watch('channels');
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
          form.reset({
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
          });
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

  const toggleChannel = (channelKey: string) => {
    if (channelKey === 'in_app') return;
    const current = form.getValues('channels');
    if (current.includes(channelKey)) {
      form.setValue('channels', current.filter((c) => c !== channelKey));
    } else {
      form.setValue('channels', [...current, channelKey]);
    }
  };

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
          {/* Example Template Banner */}
          <div className="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border bg-muted/30">
            <div className="flex items-center gap-2">
              <FileText className="h-4 w-4 text-primary" />
              <span className="text-xs font-semibold text-foreground">Example Template Available:</span>
              <span className="text-xs text-muted-foreground hidden sm:inline">Administrative notice &bull; Entire community &bull; Scheduled</span>
            </div>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={loadExampleAnnouncement}
              className="h-7 text-xs flex items-center gap-1.5 border-primary/30 text-primary hover:bg-primary/10"
            >
              <Sparkles className="h-3 w-3" />
              <span>Load "Community Office Closure"</span>
            </Button>
          </div>

          {/* Metadata: Category & Priority */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <FormField
              control={form.control}
              name="title"
              render={({ field }) => (
                <FormItem className="sm:col-span-2">
                  <FormLabel className="font-semibold text-xs">Announcement Title</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., Annual Road Maintenance & Gate Update" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="category"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="font-semibold text-xs flex items-center gap-1.5">
                    <Tag className="h-3.5 w-3.5 text-primary" />
                    <span>Category</span>
                  </FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger className="h-10 text-xs">
                        <SelectValue placeholder="Select category" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="general">General Advisory</SelectItem>
                      <SelectItem value="maintenance">Maintenance & Utilities</SelectItem>
                      <SelectItem value="security">Security & Access</SelectItem>
                      <SelectItem value="emergency">Emergency / Alert</SelectItem>
                      <SelectItem value="event">Community Event</SelectItem>
                      <SelectItem value="financial">Financial & Dues</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="priority"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="font-semibold text-xs flex items-center gap-1.5">
                    <ShieldAlert className="h-3.5 w-3.5 text-primary" />
                    <span>Priority Level</span>
                  </FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger className="h-10 text-xs">
                        <SelectValue placeholder="Select priority" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="low">Low &bull; Information</SelectItem>
                      <SelectItem value="normal">Normal &bull; Standard</SelectItem>
                      <SelectItem value="urgent">Urgent &bull; Action Needed</SelectItem>
                      <SelectItem value="critical">Critical &bull; Safety Impact</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          {/* AI Drafting & Main Message Content */}
          <div className="grid md:grid-cols-2 gap-6 items-start">
            <div className="space-y-4 rounded-xl border p-4 bg-muted/20">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                  <Sparkles className="h-4 w-4 text-primary" />
                  AI Document Summarizer
                </h3>
                <Badge variant="outline" className="text-[10px] font-mono">
                  {aiEnabled ? 'Gemini AI Ready' : 'Template Mode'}
                </Badge>
              </div>

              <FormField
                control={form.control}
                name="document"
                render={({ field }) => (
                  <FormItem>
                    <FormControl>
                      <Textarea
                        placeholder="Paste HOA minutes, utility notice, or complex bulletin to summarize..."
                        className="h-28 text-xs font-mono"
                        {...field}
                      />
                    </FormControl>
                    <FormDescription className="text-xs">
                      Drafted privately on the server for {community}.
                    </FormDescription>
                  </FormItem>
                )}
              />

              <Button
                type="button"
                onClick={handleGenerate}
                disabled={isGenerating}
                variant="secondary"
                className="w-full text-xs h-9 font-medium"
              >
                <Wand2 className="mr-2 h-3.5 w-3.5" />
                {isGenerating ? 'Synthesizing...' : 'Summarize into Bullet Points'}
              </Button>
            </div>

            <FormField
              control={form.control}
              name="content"
              render={({ field }) => (
                <FormItem className="flex flex-col h-full">
                  <FormLabel className="font-semibold text-xs">Announcement Message</FormLabel>
                  <FormControl>
                    <Textarea
                      placeholder="Write your announcement message. Markdown, bullet points, and paragraphs supported."
                      className="min-h-[180px] flex-1 text-xs leading-relaxed"
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          <Separator />

          {/* Audience Targeting Selection & Overlap Detection */}
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                  <Users className="h-4 w-4 text-primary" />
                  Audience Targeting & Overlap Protection
                </h3>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Select community boundaries. Deduplication engine guarantees zero duplicate deliveries to the same resident.
                </p>
              </div>
            </div>

            {/* Target Type Selector Buttons */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
              {[
                { id: 'entire_community', label: 'Entire Community', icon: Building },
                { id: 'street', label: 'By Street', icon: MapPin },
                { id: 'block', label: 'By Block', icon: Layers },
                { id: 'section', label: 'By Section', icon: Building },
                { id: 'custom_group', label: 'Custom Group', icon: Users },
                { id: 'roles', label: 'User Roles', icon: ShieldCheck },
              ].map((opt) => {
                const Icon = opt.icon;
                const isSelected = targetType === opt.id;
                return (
                  <button
                    key={opt.id}
                    type="button"
                    onClick={() => form.setValue('targetType', opt.id as any)}
                    className={`flex flex-col items-center justify-center p-3 rounded-lg border text-xs font-medium transition-all ${
                      isSelected
                        ? 'border-primary bg-primary/10 text-primary shadow-sm font-semibold'
                        : 'border-input hover:bg-muted/50 text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    <Icon className={`h-4 w-4 mb-1.5 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`} />
                    <span>{opt.label}</span>
                  </button>
                );
              })}
            </div>

            {/* Sub-criteria inputs */}
            {targetType === 'street' && (
              <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
                <FormLabel className="text-xs font-semibold">Select or Enter Street</FormLabel>
                {streets.length > 0 && (
                  <div className="flex flex-wrap gap-2 mb-2">
                    {streets.slice(0, 10).map((st) => (
                      <Badge
                        key={st}
                        variant={targetStreet === st ? 'default' : 'outline'}
                        className="cursor-pointer text-xs"
                        onClick={() => form.setValue('targetStreet', st)}
                      >
                        {st}
                      </Badge>
                    ))}
                  </div>
                )}
                <FormField
                  control={form.control}
                  name="targetStreet"
                  render={({ field }) => (
                    <FormItem>
                      <FormControl>
                        <Input placeholder="Enter street name (e.g. Palm Grove Way)" {...field} />
                      </FormControl>
                      <FormDescription className="text-xs">
                        Delivers to all occupants registered to properties on this street.
                      </FormDescription>
                    </FormItem>
                  )}
                />
              </div>
            )}

            {targetType === 'block' && (
              <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
                <FormLabel className="text-xs font-semibold">Select or Enter Block</FormLabel>
                {blocks.length > 0 && (
                  <div className="flex flex-wrap gap-2 mb-2">
                    {blocks.map((b) => (
                      <Badge
                        key={b}
                        variant={targetBlock === b ? 'default' : 'outline'}
                        className="cursor-pointer text-xs"
                        onClick={() => form.setValue('targetBlock', b)}
                      >
                        Block {b}
                      </Badge>
                    ))}
                  </div>
                )}
                <FormField
                  control={form.control}
                  name="targetBlock"
                  render={({ field }) => (
                    <FormItem>
                      <FormControl>
                        <Input placeholder="e.g. Block B or 12" {...field} />
                      </FormControl>
                    </FormItem>
                  )}
                />
              </div>
            )}

            {targetType === 'section' && (
              <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
                <FormLabel className="text-xs font-semibold">Section / Property Type</FormLabel>
                {sections.length > 0 && (
                  <div className="flex flex-wrap gap-2 mb-2">
                    {sections.map((s) => (
                      <Badge
                        key={s}
                        variant={targetSection === s ? 'default' : 'outline'}
                        className="cursor-pointer text-xs"
                        onClick={() => form.setValue('targetSection', s)}
                      >
                        {s}
                      </Badge>
                    ))}
                  </div>
                )}
                <FormField
                  control={form.control}
                  name="targetSection"
                  render={({ field }) => (
                    <FormItem>
                      <FormControl>
                        <Input placeholder="e.g. Residential, Villas, Townhouses" {...field} />
                      </FormControl>
                    </FormItem>
                  )}
                />
              </div>
            )}

            {targetType === 'custom_group' && (
              <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
                <FormLabel className="text-xs font-semibold">Select Permitted Streets for Custom Group</FormLabel>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  {streets.map((st) => (
                    <label key={st} className="flex items-center gap-2 text-xs cursor-pointer p-1.5 rounded hover:bg-muted">
                      <Checkbox
                        checked={customStreets.includes(st)}
                        onCheckedChange={(checked) => {
                          const updated = checked
                            ? [...customStreets, st]
                            : customStreets.filter((s) => s !== st);
                          form.setValue('customStreets', updated);
                        }}
                      />
                      <span className="truncate">{st}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}

            {targetType === 'roles' && (
              <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
                <FormLabel className="text-xs font-semibold">Select Permitted Roles</FormLabel>
                <div className="flex flex-wrap gap-4 pt-1">
                  {roles.map((role) => (
                    <label key={role} className="flex items-center gap-2 text-sm font-normal cursor-pointer">
                      <Checkbox
                        checked={targetRoles.includes(role)}
                        onCheckedChange={(checked) => {
                          const updated = checked
                            ? [...targetRoles, role]
                            : targetRoles.filter((r) => r !== role);
                          form.setValue('targetRoles', updated);
                        }}
                      />
                      {role}
                    </label>
                  ))}
                </div>
              </div>
            )}

            {/* Audience Preview & Overlap Detection Box */}
            <div className="rounded-xl border p-4 bg-card/60 space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-3 text-xs">
                <div className="flex items-center gap-2">
                  <Users className="h-4 w-4 text-primary" />
                  <span className="font-semibold text-foreground">Estimated Unique Recipients:</span>
                  {isPreviewLoading ? (
                    <span className="flex items-center gap-1 text-muted-foreground">
                      <Loader2 className="h-3 w-3 animate-spin" /> Calculating...
                    </span>
                  ) : preview ? (
                    <span className="font-bold text-foreground text-sm">
                      {preview.uniqueRecipients} {preview.uniqueRecipients === 1 ? 'Recipient' : 'Recipients'}
                    </span>
                  ) : (
                    <span className="text-muted-foreground">All eligible members</span>
                  )}
                </div>

                {preview && (
                  <div className="flex items-center gap-3 text-muted-foreground text-[11px]">
                    <span className="flex items-center gap-1">
                      <Bell className="h-3.5 w-3.5 text-primary" />
                      In-App: <strong className="text-foreground">{preview.inAppCount}</strong>
                    </span>
                    <span className="flex items-center gap-1">
                      <Mail className="h-3.5 w-3.5 text-sky-500" />
                      Email: <strong className="text-foreground">{preview.emailCount}</strong>
                    </span>
                    <span className="flex items-center gap-1">
                      <Smartphone className="h-3.5 w-3.5 text-emerald-500" />
                      SMS/WA: <strong className="text-foreground">{preview.phoneCount}</strong>
                    </span>
                  </div>
                )}
              </div>

              {/* Overlap Detection Alert */}
              {preview?.hasOverlaps ? (
                <div className="p-3 rounded-lg border border-amber-500/30 bg-amber-500/10 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2 text-amber-700 dark:text-amber-400">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    <span>
                      <strong>Overlap Detected:</strong> {preview.overlapCount} resident(s) match multiple targeting boundaries. Automatic deduplication prevents duplicate notifications.
                    </span>
                  </div>
                  <Badge variant="outline" className="border-emerald-500/40 text-emerald-600 bg-emerald-500/10 font-mono text-[10px]">
                    Deduplication Active
                  </Badge>
                </div>
              ) : preview ? (
                <div className="p-2.5 rounded-lg border border-emerald-500/20 bg-emerald-500/5 flex items-center gap-2 text-xs text-emerald-600">
                  <Check className="h-3.5 w-3.5" />
                  <span>Clean targeting boundaries: No overlapping resident duplicates detected.</span>
                </div>
              ) : null}

              {/* Sample Roster Drawer Toggle */}
              {preview?.sample && preview.sample.length > 0 && (
                <div className="pt-2 border-t border-border/50">
                  <div className="flex items-center justify-between">
                    <button
                      type="button"
                      onClick={() => setShowSampleRoster(!showSampleRoster)}
                      className="text-xs text-primary hover:underline font-medium"
                    >
                      {showSampleRoster ? 'Hide Sample Recipient Roster' : `Preview Sample Recipients (${preview.sample.length})`}
                    </button>
                    <span className="text-[11px] text-muted-foreground">Snapshot verification</span>
                  </div>

                  {showSampleRoster && (
                    <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                      {preview.sample.map((s) => (
                        <div key={s.id} className="p-2 rounded-lg border bg-background text-[11px] space-y-1">
                          <p className="font-semibold text-foreground truncate">{s.name}</p>
                          <p className="text-muted-foreground text-[10px] truncate">{s.street} &bull; {s.lot}</p>
                          <div className="flex items-center gap-1 text-[9px] font-mono">
                            <span className="text-primary font-bold">IN-APP</span>
                            {s.hasEmail && <span className="text-sky-500">&bull; EMAIL</span>}
                            {s.hasPhone && <span className="text-emerald-500">&bull; PHONE</span>}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>

          <Separator />

          {/* Delivery Channels */}
          <div className="space-y-3">
            <div>
              <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                <Radio className="h-4 w-4 text-primary" />
                Delivery Channels
              </h3>
              <p className="text-xs text-muted-foreground mt-0.5">
                In-app is the guaranteed baseline. External channels use verified active providers.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
              <div className="p-3.5 rounded-xl border border-primary/40 bg-primary/5 flex items-center justify-between">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-lg bg-primary/10 text-primary">
                    <Bell className="h-4 w-4" />
                  </div>
                  <div>
                    <p className="font-semibold text-xs">In-App Notification</p>
                    <p className="text-[11px] text-muted-foreground">Notification Centre</p>
                  </div>
                </div>
                <Badge variant="outline" className="font-mono text-[9px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                  Baseline
                </Badge>
              </div>

              <div
                onClick={() => toggleChannel('email')}
                className={`p-3.5 rounded-xl border cursor-pointer transition-all flex items-center justify-between ${
                  channels.includes('email')
                    ? 'border-sky-500/50 bg-sky-500/10 shadow-sm'
                    : 'border-input hover:bg-muted/40 opacity-70'
                }`}
              >
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-lg bg-sky-500/10 text-sky-600">
                    <Mail className="h-4 w-4" />
                  </div>
                  <div>
                    <p className="font-semibold text-xs">Email Broadcast</p>
                    <p className="text-[11px] text-muted-foreground">
                      {notificationChannels?.email?.provider ?? 'Mailgun / SMTP'}
                    </p>
                  </div>
                </div>
                <Checkbox
                  checked={channels.includes('email')}
                  onCheckedChange={() => toggleChannel('email')}
                />
              </div>

              <div
                onClick={() => toggleChannel('sms')}
                className={`p-3.5 rounded-xl border cursor-pointer transition-all flex items-center justify-between ${
                  channels.includes('sms')
                    ? 'border-emerald-500/50 bg-emerald-500/10 shadow-sm'
                    : 'border-input hover:bg-muted/40 opacity-70'
                }`}
              >
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-600">
                    <Smartphone className="h-4 w-4" />
                  </div>
                  <div>
                    <p className="font-semibold text-xs">SMS Broadcast</p>
                    <p className="text-[11px] text-muted-foreground">
                      {notificationChannels?.sms?.provider ?? 'Twilio SMS'}
                    </p>
                  </div>
                </div>
                <Checkbox
                  checked={channels.includes('sms')}
                  onCheckedChange={() => toggleChannel('sms')}
                />
              </div>

              <div
                onClick={() => toggleChannel('whatsapp')}
                className={`p-3.5 rounded-xl border cursor-pointer transition-all flex items-center justify-between ${
                  channels.includes('whatsapp')
                    ? 'border-green-600/50 bg-green-600/10 shadow-sm'
                    : 'border-input hover:bg-muted/40 opacity-70'
                }`}
              >
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-lg bg-green-600/10 text-green-700">
                    <MessageSquare className="h-4 w-4" />
                  </div>
                  <div>
                    <p className="font-semibold text-xs">WhatsApp</p>
                    <p className="text-[11px] text-muted-foreground">
                      {notificationChannels?.whatsapp?.provider ?? 'Twilio WhatsApp'}
                    </p>
                  </div>
                </div>
                <Checkbox
                  checked={channels.includes('whatsapp')}
                  onCheckedChange={() => toggleChannel('whatsapp')}
                />
              </div>
            </div>
          </div>

          <Separator />

          {/* Schedule & Expiry Controls */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Schedule Section */}
            <div className="p-4 rounded-xl border bg-card/60 space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="font-semibold text-xs flex items-center gap-1.5">
                    <Clock className="h-4 w-4 text-primary" />
                    <span>Dispatch Timing</span>
                  </h4>
                  <p className="text-[11px] text-muted-foreground">Instant vs Scheduled</p>
                </div>
                <div className="flex items-center gap-1.5">
                  <Button
                    type="button"
                    size="sm"
                    variant={!isScheduled ? 'default' : 'outline'}
                    onClick={() => form.setValue('isScheduled', false)}
                    className="h-7 text-xs px-2.5"
                  >
                    Publish Now
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant={isScheduled ? 'default' : 'outline'}
                    onClick={() => form.setValue('isScheduled', true)}
                    className="h-7 text-xs px-2.5"
                  >
                    Schedule
                  </Button>
                </div>
              </div>

              {isScheduled && (
                <FormField
                  control={form.control}
                  name="scheduledFor"
                  render={({ field }) => (
                    <FormItem className="pt-2">
                      <FormLabel className="text-xs font-medium">Future Date & Time</FormLabel>
                      <FormControl>
                        <Input
                          type="datetime-local"
                          className="h-9 text-xs"
                          min={new Date().toISOString().slice(0, 16)}
                          {...field}
                        />
                      </FormControl>
                    </FormItem>
                  )}
                />
              )}
            </div>

            {/* Optional Expiry Section */}
            <div className="p-4 rounded-xl border bg-card/60 space-y-3">
              <div>
                <h4 className="font-semibold text-xs flex items-center gap-1.5">
                  <Calendar className="h-4 w-4 text-primary" />
                  <span>Optional Expiry Date</span>
                </h4>
                <p className="text-[11px] text-muted-foreground">Automatically archive after this time</p>
              </div>

              <FormField
                control={form.control}
                name="expiresAt"
                render={({ field }) => (
                  <FormItem>
                    <FormControl>
                      <Input
                        type="datetime-local"
                        className="h-9 text-xs"
                        min={new Date().toISOString().slice(0, 16)}
                        {...field}
                      />
                    </FormControl>
                    <FormDescription className="text-[11px]">
                      Leave empty if the notice has no expiration.
                    </FormDescription>
                  </FormItem>
                )}
              />
            </div>

            {/* Acknowledgement Tracking Requirement */}
            <div className="col-span-1 md:col-span-2 p-4 rounded-xl border bg-card/60 space-y-3">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="space-y-0.5">
                  <h4 className="font-semibold text-xs flex items-center gap-1.5">
                    <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                    <span>Resident Acknowledgement Tracking</span>
                  </h4>
                  <p className="text-[11px] text-muted-foreground">
                    Require residents to explicitly confirm understanding (e.g. water interruptions, power maintenance, or office closures).
                  </p>
                </div>
                <FormField
                  control={form.control}
                  name="requiresAcknowledgement"
                  render={({ field }) => (
                    <FormItem className="flex items-center space-x-2 space-y-0">
                      <FormControl>
                        <Checkbox
                          checked={field.value}
                          onCheckedChange={field.onChange}
                          id="requiresAcknowledgement"
                        />
                      </FormControl>
                      <FormLabel htmlFor="requiresAcknowledgement" className="text-xs font-semibold cursor-pointer">
                        Require Explicit Agreement
                      </FormLabel>
                    </FormItem>
                  )}
                />
              </div>

              {form.watch('requiresAcknowledgement') && (
                <div className="pt-2 border-t grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                  <div className="text-xs text-muted-foreground">
                    <p className="font-medium text-foreground">Strict Separation Standard:</p>
                    <p className="text-[11px]">
                      Ordinary read receipts are recorded automatically when opened. Acknowledgement requires explicit active resident sign-off.
                    </p>
                  </div>
                  <FormField
                    control={form.control}
                    name="acknowledgementDeadline"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel className="text-[11px]">Optional Acknowledgement Deadline</FormLabel>
                        <FormControl>
                          <Input
                            type="datetime-local"
                            className="h-8 text-xs"
                            min={new Date().toISOString().slice(0, 16)}
                            {...field}
                          />
                        </FormControl>
                      </FormItem>
                    )}
                  />
                </div>
              )}
            </div>
          </div>

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

      {/* Safeguard: Pre-Send Preview Dialog showing exact message, recipient count & deduplication */}
      <Dialog open={showPreSendReview} onOpenChange={setShowPreSendReview}>
        <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="text-lg flex items-center gap-2">
              <ShieldCheck className="h-5 w-5 text-primary" />
              <span>Review Announcement Before Dispatch</span>
            </DialogTitle>
            <DialogDescription className="text-xs">
              Inspect the exact rendered message and verified recipient count before broadcasting to the community.
            </DialogDescription>
          </DialogHeader>

          {pendingValues && (
            <div className="space-y-4 py-2 text-xs">
              {/* Message Preview Box */}
              <div className="p-4 rounded-xl border bg-muted/30 space-y-2">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-bold text-sm text-foreground">{pendingValues.title}</span>
                  <div className="flex items-center gap-1.5 shrink-0">
                    <Badge variant="outline" className="text-[10px] uppercase font-mono capitalize">
                      {pendingValues.category}
                    </Badge>
                    <Badge variant="outline" className="text-[10px] font-mono capitalize">
                      {pendingValues.priority}
                    </Badge>
                  </div>
                </div>
                <div className="text-xs text-muted-foreground whitespace-pre-line leading-relaxed max-h-48 overflow-y-auto border-t pt-2 mt-2">
                  {pendingValues.content}
                </div>
              </div>

              {/* Target & Recipient Stats */}
              <div className="grid grid-cols-2 gap-3">
                <div className="p-3 rounded-lg border bg-card">
                  <span className="text-[11px] font-medium text-muted-foreground uppercase">Target Audience</span>
                  <div className="font-semibold text-sm capitalize mt-0.5">
                    {pendingValues.targetType.replace('_', ' ')}
                  </div>
                  <span className="text-[10px] text-muted-foreground">
                    {pendingValues.targetStreet || pendingValues.targetBlock || pendingValues.targetSection || community}
                  </span>
                </div>

                <div className="p-3 rounded-lg border bg-primary/5 border-primary/20">
                  <span className="text-[11px] font-medium text-primary uppercase">Intended Recipients</span>
                  <div className="font-bold text-xl text-primary mt-0.5">
                    {preview?.uniqueRecipients ?? 0}
                  </div>
                  <span className="text-[10px] text-muted-foreground">
                    Deduplicated resident recipients
                  </span>
                </div>
              </div>

              {/* Deduplication & Overlap Check */}
              {preview?.hasOverlaps ? (
                <div className="p-3 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300 text-[11px]">
                  <strong>Overlapping Selections ({preview.overlapCount} overlaps):</strong> Active deduplication ensures overlapping boundaries are merged so each resident receives exactly one notification.
                </div>
              ) : (
                <div className="p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 text-[11px] flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                  <span>Clean targeting boundaries. Zero overlapping recipient duplicates detected.</span>
                </div>
              )}

              {/* Channels List */}
              <div className="flex items-center gap-2 pt-1">
                <span className="text-muted-foreground">Selected Channels:</span>
                <div className="flex flex-wrap gap-1">
                  {pendingValues.channels.map((ch) => (
                    <Badge key={ch} variant="secondary" className="text-[10px] uppercase font-mono">
                      {ch.replace('_', ' ')}
                    </Badge>
                  ))}
                </div>
              </div>

              {/* Urgent Priority / Emergency Disclaimer */}
              {(pendingValues.priority === 'critical' || pendingValues.category === 'emergency') && (
                <div className="p-3 rounded-lg border border-destructive/40 bg-destructive/10 text-destructive text-[11px] space-y-1">
                  <div className="font-bold flex items-center gap-1.5">
                    <AlertTriangle className="h-4 w-4" />
                    <span>Emergency Priority Dispatch Warning</span>
                  </div>
                  <p>
                    Emergency notices initiate multi-channel delivery across push, in-app, SMS, and email. Best-effort delivery is subject to carrier latency and recipient device connectivity; guaranteed delivery cannot be claimed.
                  </p>
                </div>
              )}

              {/* Scheduled / Expiry Notice */}
              {pendingValues.isScheduled && (
                <div className="p-2.5 rounded-lg border bg-amber-500/10 border-amber-500/20 text-amber-800 dark:text-amber-300 text-[11px] flex items-center gap-2">
                  <Clock className="h-4 w-4 shrink-0" />
                  <span>
                    Will be queued for dispatch at <strong>{pendingValues.scheduledFor}</strong> in community timezone. Requires active Laravel scheduler.
                  </span>
                </div>
              )}

              {pendingValues.expiresAt && (
                <div className="p-2.5 rounded-lg border bg-muted/40 text-muted-foreground text-[11px] flex items-center gap-2">
                  <Calendar className="h-4 w-4 shrink-0" />
                  <span>Notice automatically expires and hides from resident feeds on <strong>{pendingValues.expiresAt}</strong>.</span>
                </div>
              )}

              {pendingValues.requiresAcknowledgement && (
                <div className="p-2.5 rounded-lg border bg-emerald-500/10 border-emerald-500/20 text-emerald-800 dark:text-emerald-300 text-[11px] flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                  <span>
                    <strong>Acknowledgement Tracking Active:</strong> Residents will be required to explicitly acknowledge this notice. Ordinary read receipts will be tracked separately.
                  </span>
                </div>
              )}
            </div>
          )}

          <DialogFooter className="flex items-center justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setShowPreSendReview(false)}
              className="text-xs"
            >
              Back to Edit
            </Button>
            <Button
              type="button"
              size="sm"
              disabled={isSubmitting}
              onClick={() => {
                if (pendingValues) {
                  setShowPreSendReview(false);
                  executeDispatch(pendingValues);
                }
              }}
              className="text-xs flex items-center gap-1.5"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  <span>Dispatching...</span>
                </>
              ) : pendingValues?.isScheduled ? (
                <>
                  <Calendar className="h-3.5 w-3.5" />
                  <span>Confirm & Schedule</span>
                </>
              ) : (
                <>
                  <Send className="h-3.5 w-3.5" />
                  <span>Confirm & Dispatch Now</span>
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
