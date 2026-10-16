import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Calendar, Clock, CheckCircle2 } from 'lucide-react';
import type { UseFormReturn } from 'react-hook-form';
import type { NotificationFormValues } from './notification-form-schema';

/** Publish now or later, an optional expiry, and whether residents must acknowledge. */
export function DispatchTimingSection({ form }: { form: UseFormReturn<NotificationFormValues> }) {
  const isScheduled = form.watch('isScheduled');

  return (
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
  );
}
