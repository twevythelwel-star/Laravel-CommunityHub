
'use client';

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
import { useToast } from '@/hooks/use-toast';
import type { BlocklistEntry } from '@/types';
import { Popover, PopoverContent, PopoverTrigger } from '../ui/popover';
import { CalendarIcon } from 'lucide-react';
import { Calendar } from '../ui/calendar';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { Textarea } from '../ui/textarea';
import { Switch } from '../ui/switch';


const formSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters.'),
  reason: z.string().min(10, 'Reason must be at least 10 characters.'),
  photoUrl: z.string().url().nullable().optional(),
  isPermanent: z.boolean().default(true),
  expiryDate: z.date().optional().nullable(),
}).refine(data => data.isPermanent || !!data.expiryDate, {
    message: "An expiry date is required for temporary blocks.",
    path: ["expiryDate"],
});

type FormValues = z.infer<typeof formSchema>;

type BlocklistFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSave: (entry: Omit<BlocklistEntry, 'id' | 'dateAdded' | 'addedBy'>, id?: string) => void;
  entry?: BlocklistEntry;
};

export function BlocklistForm({ children, open, onOpenChange, onSave, entry }: BlocklistFormProps) {
  const { toast } = useToast();
  
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      name: '',
      reason: '',
      photoUrl: null,
      isPermanent: true,
      expiryDate: null,
    },
  });

  useEffect(() => {
    if (entry) {
        form.reset({
            name: entry.name,
            reason: entry.reason,
            photoUrl: entry.photoUrl,
            isPermanent: !entry.expiryDate,
            expiryDate: entry.expiryDate
        });
    } else {
        form.reset({
            name: '',
            reason: '',
            photoUrl: null,
            isPermanent: true,
            expiryDate: null,
        });
    }
  }, [entry, form]);


  function onSubmit(values: FormValues) {
    const entryToSave = {
        name: values.name,
        reason: values.reason,
        photoUrl: values.photoUrl,
        expiryDate: values.isPermanent ? null : values.expiryDate!,
    }
    onSave(entryToSave, entry?.id);
    toast({
      title: entry ? 'Entry Updated' : 'Entry Added',
      description: `${values.name} has been ${entry ? 'updated on' : 'added to'} the blocklist.`,
    });
    onOpenChange(false);
  }

  return (
    <Dialog open={open} onOpenChange={(isOpen) => {
        onOpenChange(isOpen);
        if (!isOpen) {
            form.reset();
        }
    }}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <DialogHeader>
              <DialogTitle>{entry ? 'Edit Blocklist Entry' : 'Add to Blocklist'}</DialogTitle>
              <DialogDescription>
                Fill out the form to add an individual to the blocklist. This will prevent them from being checked in.
              </DialogDescription>
            </DialogHeader>
            
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Full Name</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., John Doe" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
             <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Reason for Block</FormLabel>
                  <FormControl>
                    <Textarea placeholder="Explain why this person is being blocked..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="isPermanent"
              render={({ field }) => (
                <FormItem className="flex flex-row items-center justify-between rounded-lg border p-3 shadow-sm">
                  <div className="space-y-0.5">
                    <FormLabel>Permanent Block</FormLabel>
                     <FormMessage />
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

            {!form.watch('isPermanent') && (
                 <FormField
                    control={form.control}
                    name="expiryDate"
                    render={({ field }) => (
                        <FormItem className="flex flex-col">
                        <FormLabel>Expiry Date</FormLabel>
                        <Popover>
                            <PopoverTrigger asChild>
                            <FormControl>
                                <Button
                                variant={"outline"}
                                className={cn(
                                    "w-full pl-3 text-left font-normal",
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
                                selected={field.value || undefined}
                                onSelect={field.onChange}
                                disabled={(date) =>
                                  date < new Date()
                                }
                                initialFocus
                            />
                            </PopoverContent>
                        </Popover>
                        <FormMessage />
                        </FormItem>
                    )}
                />
            )}
            
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
              <Button type="submit">Save Changes</Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
