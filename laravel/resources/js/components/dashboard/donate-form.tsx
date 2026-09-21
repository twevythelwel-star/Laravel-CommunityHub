import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
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
import { useToast } from '@/hooks/use-toast';
import { Switch } from '../ui/switch';
import type { FundraiserRow } from './fundraiser-progress-card';
import { PaymentChannelPicker } from './payment-channel-picker';
import {
  channelLabel,
  channelSurcharge,
  totalWithSurcharge,
  type PaymentChannel,
} from '@/lib/payment-channels';

/**
 * Record a donation against a fundraiser.
 *
 * Two things changed:
 *
 *   - **The currency picker is gone.** It offered JMD, USD, GBP, EUR and CAD
 *     and defaulted to USD, while the server sums `amount_minor` across every
 *     donation with no conversion. Choosing USD against a JMD goal therefore
 *     recorded about 1/155th of what was given. Donations are now always in the
 *     fundraiser's own currency, which the server also enforces.
 *   - **The toast no longer says "Donation Successful!".** No payment is taken
 *     anywhere in this flow — `FundraisingController::donate()` writes a row.
 *     It is a pledge the resident records against their own name, so that is
 *     what it now says.
 */

const formSchema = z
  .object({
    amount: z.coerce.number().min(1, 'Donation must be at least 1.'),
    donorName: z.string().optional(),
    isAnonymous: z.boolean().default(false),
  })
  .refine((data) => (data.isAnonymous ? true : !!data.donorName && data.donorName.length > 0), {
    message: 'Name is required for non-anonymous donations.',
    path: ['donorName'],
  });

type DonateFormValues = z.infer<typeof formSchema>;

type DonateFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  fundraiser: FundraiserRow;
  /**
   * The estate's enabled payment methods, from
   * PaymentOrchestratorService::getAvailableChannels() — the same list the
   * Billing page uses, so both offer the same options.
   */
  channels?: PaymentChannel[];
};

export function DonateForm({
  children,
  open,
  onOpenChange,
  fundraiser,
  channels = [],
}: DonateFormProps) {
  const { toast } = useToast();
  const [submitting, setSubmitting] = useState(false);
  const [channelKey, setChannelKey] = useState<string | null>(channels[0]?.key ?? null);

  const selectedChannel = channels.find((c) => c.key === channelKey) ?? null;

  const form = useForm<DonateFormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      amount: 25,
      donorName: '',
      isAnonymous: false,
    },
  });

  const isAnonymous = form.watch('isAnonymous');

  function onSubmit(values: DonateFormValues) {
    setSubmitting(true);

    router.post(
      `/dashboard/fundraising/${fundraiser.id}/donate`,
      {
        amount: values.amount,
        donor_name: values.isAnonymous ? null : values.donorName,
        is_anonymous: values.isAnonymous,
        channel: channelKey,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          form.reset();
          onOpenChange(false);
          toast({
            title: 'Pledge recorded',
            description: selectedChannel
              ? `Thank you — ${values.amount} ${fundraiser.currency} recorded against "${fundraiser.title}" via ${channelLabel(selectedChannel)}.`
              : `Thank you — ${values.amount} ${fundraiser.currency} has been recorded against "${fundraiser.title}".`,
          });
        },
        onError: (errors) =>
          toast({
            variant: 'destructive',
            title: 'Could not record your pledge',
            description: Object.values(errors)[0] ?? 'Please check the amount and try again.',
          }),
        onFinish: () => setSubmitting(false),
      },
    );
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(isOpen) => {
        onOpenChange(isOpen);
        if (!isOpen) {
          form.reset();
        }
      }}
    >
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Donate to: {fundraiser.title}</DialogTitle>
              <DialogDescription>
                This records your pledge against the fundraiser. No payment is taken here —
                arrange it with the community office.
              </DialogDescription>
            </DialogHeader>

            <FormField
              control={form.control}
              name="amount"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Amount ({fundraiser.currency})</FormLabel>
                  <FormControl>
                    <Input type="number" min="1" step="0.01" {...field} />
                  </FormControl>
                  <FormDescription>
                    In {fundraiser.currency}, the currency this fundraiser is run in.
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            {channels.length > 0 && (
              <div className="space-y-2">
                <PaymentChannelPicker
                  channels={channels}
                  value={channelKey}
                  onChange={setChannelKey}
                  legend="How would you like to give?"
                  disabled={submitting}
                />

                {/*
                  If the chosen method carries a surcharge, show what it adds
                  before the donor commits. The column existed and was never
                  surfaced anywhere.
                */}
                {selectedChannel && channelSurcharge(selectedChannel) > 0 && (
                  <p className="rounded-md bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    {channelLabel(selectedChannel)} adds {channelSurcharge(selectedChannel)}%.
                    A {form.watch('amount') || 0} {fundraiser.currency} gift is recorded as{' '}
                    {totalWithSurcharge(Number(form.watch('amount')) || 0, selectedChannel)}{' '}
                    {fundraiser.currency}.
                  </p>
                )}
              </div>
            )}

            <FormField
              control={form.control}
              name="isAnonymous"
              render={({ field }) => (
                <FormItem className="flex flex-row items-center justify-between rounded-lg border p-3">
                  <div className="space-y-0.5">
                    <FormLabel>Donate anonymously</FormLabel>
                    <FormDescription className="text-xs">
                      Your name is hidden from other residents.
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} />
                  </FormControl>
                </FormItem>
              )}
            />

            {!isAnonymous && (
              <FormField
                control={form.control}
                name="donorName"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Display name</FormLabel>
                    <FormControl>
                      <Input placeholder="e.g., Marcus V." {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Recording…' : 'Record Pledge'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
