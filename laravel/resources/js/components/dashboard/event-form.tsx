import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { format, setHours, setMinutes } from 'date-fns';
import { DateField } from '@/components/dashboard/date-field';
import { FormDialog } from '@/components/dashboard/form-dialog';
import { TextField } from '@/components/dashboard/text-field';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useToast } from '@/hooks/use-toast';
import type { CommunityEvent, CommunityEventType } from '@/types';

/** The date at the given "HH:mm" time, or the date unchanged when there is no time. */
function withTime(date: Date, time?: string): Date {
  if (!time) {
    return date;
  }
  const [hours, minutes] = time.split(':').map((part) => parseInt(part, 10));

  return setMinutes(setHours(date, hours), minutes);
}

const formSchema = z
  .object({
    title: z.string().min(5, 'Title must be at least 5 characters.'),
    description: z.string().min(10, 'Description must be at least 10 characters.'),
    eventType: z.enum([
      'event',
      'public_holiday',
      'office_closure',
      'meeting',
      'maintenance_window',
      'scheduled_notice',
    ]).default('event'),
    location: z.string().optional().or(z.literal('')),
    changeSummary: z.string().max(500).optional().or(z.literal('')),
    startDate: z.date({ required_error: 'A start date is required.' }),
    startTime: z.string().optional(),
    hasEndTime: z.boolean().default(false),
    endDate: z.date().optional(),
    endTime: z.string().optional(),
    imageUrl: z.string().url().optional().or(z.literal('')),
  })
  .refine(
    (data) => {
      if (!data.hasEndTime) return true;
      if (!data.endDate) return false;

      return withTime(data.endDate, data.endTime) >= withTime(data.startDate, data.startTime);
    },
    {
      message: 'End date and time must be after the start date and time.',
      path: ['endDate'],
    }
  );

type FormValues = z.infer<typeof formSchema>;

type EventFormProps = {
  children?: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSave: (data: Omit<CommunityEvent, 'id'>, id?: string) => Promise<void>;
  event?: CommunityEvent;
};

export function EventForm({ children, open, onOpenChange, onSave, event }: EventFormProps) {
  const { toast } = useToast();
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      hasEndTime: false,
      eventType: 'event',
    },
  });

  const hasEndTime = form.watch('hasEndTime');

  async function onSubmit(values: FormValues) {
    const finalData = {
      title: values.title,
      description: values.description,
      eventType: values.eventType as CommunityEventType,
      location: values.location || null,
      changeSummary: values.changeSummary || null,
      startDate: withTime(values.startDate, values.startTime),
      endDate: values.hasEndTime && values.endDate ? withTime(values.endDate, values.endTime) : undefined,
      imageUrl: values.imageUrl || undefined,
    };

    try {
      await onSave(finalData, event?.id);
    } catch (message) {
      toast({
        variant: 'destructive',
        title: 'Event not saved',
        description:
          typeof message === 'string'
            ? message
            : 'The server refused the event. Please check the details and try again.',
      });
      return;
    }

    toast({
      title: event ? 'Calendar Entry Updated' : 'Calendar Entry Created',
      description: `"${values.title}" has been saved.`,
    });
    onOpenChange(false);
  }

  return (
    <FormDialog
      trigger={children}
      open={open}
      onOpenChange={onOpenChange}
      form={form}
      onSubmit={onSubmit}
      record={event}
      resetValues={() =>
        event
          ? {
              title: event.title,
              description: event.description,
              eventType: (event.eventType as any) || 'event',
              location: event.location || '',
              changeSummary: event.changeSummary || '',
              startDate: event.startDate,
              startTime: event.startDate ? format(event.startDate, 'HH:mm') : undefined,
              hasEndTime: !!event.endDate,
              endDate: event.endDate,
              endTime: event.endDate ? format(event.endDate, 'HH:mm') : undefined,
              imageUrl: event.imageUrl || '',
            }
          : {
              title: '',
              description: '',
              eventType: 'event',
              location: '',
              changeSummary: '',
              startDate: new Date(),
              startTime: format(new Date(), 'HH:mm'),
              hasEndTime: false,
              endDate: undefined,
              endTime: undefined,
              imageUrl: '',
            }
      }
      title={`${event ? 'Edit' : 'Add'} Calendar Entry`}
      description="Create or update community events, holidays, meetings, maintenance windows, or office closures."
      submitLabel="Save Entry"
      submittingLabel="Saving…"
      contentClassName="sm:max-w-lg"
    >
      <FormField
        control={form.control}
        name="eventType"
        render={({ field }) => (
          <FormItem>
            <FormLabel>Category / Entry Type</FormLabel>
            <Select onValueChange={field.onChange} defaultValue={field.value || 'event'}>
              <FormControl>
                <SelectTrigger>
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem value="event">Community Event / Social</SelectItem>
                <SelectItem value="public_holiday">Public Holiday</SelectItem>
                <SelectItem value="office_closure">Office Closure</SelectItem>
                <SelectItem value="meeting">Meeting / AGM</SelectItem>
                <SelectItem value="maintenance_window">Maintenance Window</SelectItem>
                <SelectItem value="scheduled_notice">Scheduled Notice</SelectItem>
              </SelectContent>
            </Select>
            <FormMessage />
          </FormItem>
        )}
      />

      <TextField control={form.control} name="title" label="Title" placeholder="e.g., Annual General Meeting" />
      <TextField control={form.control} name="description" label="Description" placeholder="Provide event details..." multiline />
      <TextField control={form.control} name="location" label="Location / Venue (Optional)" placeholder="e.g., Clubhouse, Gate House, or Zoom" />

      {event && (
        <TextField
          control={form.control}
          name="changeSummary"
          label="What Changed? (Optional update notes)"
          placeholder="e.g., Rescheduled start time from 2:00 PM to 3:30 PM due to weather"
        />
      )}

      <div className="grid grid-cols-2 gap-4">
        <DateField control={form.control} name="startDate" label="Start Date" />
        <TextField control={form.control} name="startTime" label="Start Time (Optional)" type="time" />
      </div>

      <FormField
        control={form.control}
        name="hasEndTime"
        render={({ field }) => (
          <FormItem className="flex flex-row items-center justify-between rounded-lg border p-3 shadow-sm">
            <div className="space-y-0.5">
              <FormLabel>Add end date & time</FormLabel>
            </div>
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </FormControl>
          </FormItem>
        )}
      />

      {hasEndTime && (
        <div className="grid grid-cols-2 gap-4">
          <DateField
            control={form.control}
            name="endDate"
            label="End Date"
            disabled={(date) => date < form.getValues('startDate')}
          />
          <TextField control={form.control} name="endTime" label="End Time (Optional)" type="time" />
        </div>
      )}

      <TextField control={form.control} name="imageUrl" label="Image URL (Optional)" placeholder="https://example.com/image.png" />
    </FormDialog>
  );
}
