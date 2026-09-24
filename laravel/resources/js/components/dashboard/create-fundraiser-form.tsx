import React, { useState } from 'react';
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
  FormDescription,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { useToast } from '@/hooks/use-toast';
import { router } from '@inertiajs/react';
import { Popover, PopoverContent, PopoverTrigger } from '../ui/popover';
import { CalendarIcon, Target, Image as ImageIcon } from 'lucide-react';
import { Calendar } from '../ui/calendar';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { Textarea } from '../ui/textarea';

const formSchema = z
  .object({
    title: z.string().min(5, 'Title must be at least 5 characters.').max(160),
    description: z.string().min(10, 'Description must be at least 10 characters.').max(5000),
    beneficiary: z.string().max(150).optional(),
    goal: z.coerce.number().min(1, 'Goal must be at least $1.'),
    startDate: z.date({ required_error: 'A start date is required.' }),
    endDate: z.date({ required_error: 'An end date is required.' }),
    coverImageUrl: z.string().url('Must be a valid URL').optional().or(z.literal('')),
    images: z.string().optional(),
    matchingSponsor: z.string().max(150).optional(),
  })
  .refine((data) => data.endDate > data.startDate, {
    message: 'End date must be after start date.',
    path: ['endDate'],
  });

type CreateFundraiserFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function CreateFundraiserForm({ children, open, onOpenChange }: CreateFundraiserFormProps) {
  const { toast } = useToast();
  const [submitting, setSubmitting] = useState(false);

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      title: '',
      description: '',
      beneficiary: 'Cypress Bay Community Improvement Fund',
      goal: 150000,
      coverImageUrl: '',
      images: '',
      matchingSponsor: '',
    },
  });

  function onSubmit(values: z.infer<typeof formSchema>) {
    setSubmitting(true);

    router.post(
      '/dashboard/fundraising',
      {
        title: values.title,
        description: values.description,
        beneficiary: values.beneficiary || 'Cypress Bay Community Improvement Fund',
        goal: values.goal,
        start_date: format(values.startDate, 'yyyy-MM-dd'),
        end_date: format(values.endDate, 'yyyy-MM-dd'),
        status: 'Upcoming',
        cover_image_url: values.coverImageUrl || null,
        images: values.images || null,
        matching_sponsor: values.matchingSponsor || null,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          form.reset();
          onOpenChange(false);
          toast({
            title: 'Campaign created',
            description: `"${values.title}" has been scheduled. Use "Enable Now" to open it for donations.`,
          });
        },
        onError: (errors) =>
          toast({
            variant: 'destructive',
            title: 'Could not create the campaign',
            description: Object.values(errors)[0] ?? 'Please check the form and try again.',
          }),
        onFinish: () => setSubmitting(false),
      }
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
      <DialogContent className="sm:max-w-xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <div className="p-2 rounded-full bg-primary/10 text-primary">
              <Target className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle>Create Community Campaign</DialogTitle>
              <DialogDescription>
                Launch a targeted fundraising campaign for estate improvements or charity.
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="title"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Campaign Title</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g. New Playground & Recreation Equipment" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="beneficiary"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Beneficiary / Fund</FormLabel>
                    <FormControl>
                      <Input placeholder="e.g. Park Development Committee" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="goal"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Donation Target Goal (JMD)</FormLabel>
                    <FormControl>
                      <Input type="number" placeholder="150000" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Campaign Purpose & Description</FormLabel>
                  <FormControl>
                    <Textarea
                      rows={3}
                      placeholder="Detail why this campaign is important to our community, expected costs, and contractor timelines..."
                      {...field}
                    />
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
                            variant={'outline'}
                            className={cn(
                              'pl-3 text-left font-normal',
                              !field.value && 'text-muted-foreground'
                            )}
                          >
                            {field.value ? format(field.value, 'PPP') : <span>Pick start date</span>}
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
                name="endDate"
                render={({ field }) => (
                  <FormItem className="flex flex-col">
                    <FormLabel>End Date</FormLabel>
                    <Popover>
                      <PopoverTrigger asChild>
                        <FormControl>
                          <Button
                            variant={'outline'}
                            className={cn(
                              'pl-3 text-left font-normal',
                              !field.value && 'text-muted-foreground'
                            )}
                          >
                            {field.value ? format(field.value, 'PPP') : <span>Pick end date</span>}
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
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="matchingSponsor"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>1:1 Matching Sponsor (Optional)</FormLabel>
                    <FormControl>
                      <Input placeholder="e.g. Apex Construction Foundation" {...field} />
                    </FormControl>
                    <FormDescription className="text-[11px]">
                      Sponsor matching resident gifts dollar-for-dollar.
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="coverImageUrl"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Cover Photo URL (Optional)</FormLabel>
                    <FormControl>
                      <Input placeholder="https://images.unsplash.com/..." {...field} />
                    </FormControl>
                    <FormDescription className="text-[11px]">
                      Hero picture featured on campaign cards.
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="images"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Gallery Image URLs (Optional)</FormLabel>
                  <FormControl>
                    <Textarea
                      rows={2}
                      placeholder="Enter one image URL per line for the campaign photo gallery..."
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter className="pt-2">
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Creating Campaign...' : 'Launch Campaign'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
