

import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
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
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import type { CommunityEvent } from '@/types';
import { Popover, PopoverContent, PopoverTrigger } from '../ui/popover';
import { CalendarIcon } from 'lucide-react';
import { Calendar } from '../ui/calendar';
import { cn } from '@/lib/utils';
import { format, setHours, setMinutes } from 'date-fns';
import { Switch } from '../ui/switch';

const formSchema = z.object({
  title: z.string().min(5, 'Title must be at least 5 characters.'),
  description: z.string().min(10, 'Description must be at least 10 characters.'),
  startDate: z.date({ required_error: "A start date is required." }),
  startTime: z.string().optional(),
  hasEndTime: z.boolean().default(false),
  endDate: z.date().optional(),
  endTime: z.string().optional(),
  imageUrl: z.string().url().optional().or(z.literal('')),
}).refine(data => {
    if (!data.hasEndTime) return true;
    if (!data.endDate) return false; // End date is required if hasEndTime is true
    
    const startDateTime = data.startTime 
        ? setMinutes(setHours(data.startDate, parseInt(data.startTime.split(':')[0])), parseInt(data.startTime.split(':')[1]))
        : data.startDate;
        
    const endDateTime = data.endTime
        ? setMinutes(setHours(data.endDate, parseInt(data.endTime.split(':')[0])), parseInt(data.endTime.split(':')[1]))
        : data.endDate;

    return endDateTime >= startDateTime;
}, {
    message: "End date and time must be after the start date and time.",
    path: ["endDate"],
});


type FormValues = z.infer<typeof formSchema>;

type EventFormProps = {
  children?: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /**
   * Persists the event. Resolves when the server accepted it and rejects when
   * it refused, so the dialog only claims success for a save that happened.
   */
  onSave: (data: Omit<CommunityEvent, 'id'>, id?: string) => Promise<void>;
  event?: CommunityEvent;
};

export function EventForm({ children, open, onOpenChange, onSave, event }: EventFormProps) {
  const { toast } = useToast();
  
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
        hasEndTime: false,
    }
  });

  const hasEndTime = form.watch('hasEndTime');

  useEffect(() => {
    if (open) {
      if (event) {
        form.reset({
            ...event,
            startTime: event.startDate ? format(event.startDate, 'HH:mm') : undefined,
            hasEndTime: !!event.endDate,
            endTime: event.endDate ? format(event.endDate, 'HH:mm') : undefined,
        });
      } else {
        form.reset({
            title: '',
            description: '',
            startDate: new Date(),
            startTime: format(new Date(), 'HH:mm'),
            hasEndTime: false,
            endDate: undefined,
            endTime: undefined,
            imageUrl: '',
        });
      }
    }
  }, [open, event, form]);

  async function onSubmit(values: FormValues) {
    const finalStartDate = values.startTime 
        ? setMinutes(setHours(values.startDate, parseInt(values.startTime.split(':')[0])), parseInt(values.startTime.split(':')[1]))
        : values.startDate;

    let finalEndDate: Date | undefined;
    if (values.hasEndTime && values.endDate) {
        finalEndDate = values.endTime
            ? setMinutes(setHours(values.endDate, parseInt(values.endTime.split(':')[0])), parseInt(values.endTime.split(':')[1]))
            : values.endDate;
    }


    const finalData = {
        title: values.title,
        description: values.description,
        startDate: finalStartDate,
        endDate: finalEndDate,
        imageUrl: values.imageUrl || undefined,
    }

    try {
      await onSave(finalData, event?.id);
    } catch (message) {
      toast({
        variant: 'destructive',
        title: 'Event not saved',
        description: typeof message === 'string' ? message : 'The server refused the event. Please check the details and try again.',
      });
      return;
    }

    toast({
      title: event ? 'Event Updated' : 'Event Created',
      description: `The event "${values.title}" has been saved.`,
    });
    onOpenChange(false);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-lg">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <DialogHeader>
              <DialogTitle>{event ? 'Edit' : 'Add'} Community Event</DialogTitle>
              <DialogDescription>
                Fill in the details for the event. Times are optional for all-day events.
              </DialogDescription>
            </DialogHeader>

            <FormField
              control={form.control}
              name="title"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Event Title</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., Community Pool Party" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Description</FormLabel>
                  <FormControl>
                    <Textarea placeholder="Describe the event..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-2 gap-4">
                 <FormField
                    control={form.control}
                    name="startDate"
                    render={({ field }) => (
                        <FormItem className="flex flex-col">
                        <FormLabel>Start Date</FormLabel>
                        <Popover>
                            <PopoverTrigger asChild>
                            <FormControl>
                                <Button
                                variant={"outline"}
                                className={cn(
                                    "pl-3 text-left font-normal",
                                    !field.value && "text-muted-foreground"
                                )}
                                >
                                {field.value ? (
                                    format(field.value, "PPP")
                                ) : (
                                    <span>Pick a date</span>
                                )}
                                <CalendarIcon className="ml-auto h-4 w-4 opacity-50" />
                                </Button>
                            </FormControl>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                            <Calendar
                                mode="single"
                                selected={field.value}
                                onSelect={field.onChange}
                                initialFocus
                            />
                            </PopoverContent>
                        </Popover>
                        <FormMessage />
                        </FormItem>
                    )}
                />
                 <FormField
                    control={form.control}
                    name="startTime"
                    render={({ field }) => (
                        <FormItem>
                        <FormLabel>Start Time (Optional)</FormLabel>
                        <FormControl>
                            <Input type="time" {...field} />
                        </FormControl>
                        <FormMessage />
                        </FormItem>
                    )}
                />
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
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  </FormControl>
                </FormItem>
              )}
            />

            {hasEndTime && (
                 <div className="grid grid-cols-2 gap-4">
                    <FormField
                        control={form.control}
                        name="endDate"
                        render={({ field }) => (
                            <FormItem className="flex flex-col">
                            <FormLabel>End Date</FormLabel>
                            <Popover>
                                <PopoverTrigger asChild>
                                <FormControl>
                                    <Button
                                    variant={"outline"}
                                    className={cn(
                                        "pl-3 text-left font-normal",
                                        !field.value && "text-muted-foreground"
                                    )}
                                    >
                                    {field.value ? (
                                        format(field.value, "PPP")
                                    ) : (
                                        <span>Pick a date</span>
                                    )}
                                    <CalendarIcon className="ml-auto h-4 w-4 opacity-50" />
                                    </Button>
                                </FormControl>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                <Calendar
                                    mode="single"
                                    selected={field.value}
                                    onSelect={field.onChange}
                                    disabled={(date) => date < form.getValues('startDate')}
                                    initialFocus
                                />
                                </PopoverContent>
                            </Popover>
                            <FormMessage />
                            </FormItem>
                        )}
                    />
                    <FormField
                        control={form.control}
                        name="endTime"
                        render={({ field }) => (
                            <FormItem>
                            <FormLabel>End Time (Optional)</FormLabel>
                            <FormControl>
                                <Input type="time" {...field} />
                            </FormControl>
                            <FormMessage />
                            </FormItem>
                        )}
                    />
                </div>
            )}


             <FormField
              control={form.control}
              name="imageUrl"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Image URL (Optional)</FormLabel>
                  <FormControl>
                    <Input placeholder="https://example.com/image.png" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
              <Button type="submit" disabled={form.formState.isSubmitting}>
                {form.formState.isSubmitting ? 'Saving…' : 'Save Event'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
