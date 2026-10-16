import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Mail, MessageSquare, Smartphone, Bell, Radio } from 'lucide-react';
import type { UseFormReturn } from 'react-hook-form';
import type { ChannelStatus, NotificationFormValues } from './notification-form-schema';

type DeliveryChannelsSectionProps = {
  form: UseFormReturn<NotificationFormValues>;
  notificationChannels: Record<string, ChannelStatus>;
};

/** In-app is always on; email, SMS and WhatsApp are opt-in. */
export function DeliveryChannelsSection({ form, notificationChannels }: DeliveryChannelsSectionProps) {
  const channels = form.watch('channels');

  const toggleChannel = (channelKey: string) => {
    if (channelKey === 'in_app') return;
    const current = form.getValues('channels');
    if (current.includes(channelKey)) {
      form.setValue('channels', current.filter((c) => c !== channelKey));
    } else {
      form.setValue('channels', [...current, channelKey]);
    }
  };

  return (
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
  );
}
