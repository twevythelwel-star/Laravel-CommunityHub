
'use client';

import { useEffect, useState } from 'react';
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
import { useToast } from '@/hooks/use-toast';
import type { BlocklistEntry, Visitor } from '@/types';
import { Popover, PopoverContent, PopoverTrigger } from '../ui/popover';
import { CalendarIcon, Check, ChevronsUpDown } from 'lucide-react';
import { Calendar } from '../ui/calendar';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { Textarea } from '../ui/textarea';
import { Switch } from '../ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '../ui/command';


const predefinedReasons = [
    'Security Threat',
    'Prior Misconduct',
    'Restraining Order',
    'Policy Violation',
    'Other',
];

const formSchema = z.object({
  name: z.string({ required_error: "Please select a visitor."}),
  reason: z.string().min(1, 'A reason is required.'),
  customReason: z.string().optional(),
  photoUrl: z.string().url().nullable().optional(),
  isPermanent: z.boolean().default(true),
  expiryDate: z.date().optional().nullable(),
}).refine(data => data.isPermanent || !!data.expiryDate, {
    message: "An expiry date is required for temporary blocks.",
    path: ["expiryDate"],
}).refine(data => data.reason !== 'Other' || (data.reason === 'Other' && data.customReason && data.customReason.length >= 10), {
    message: 'A custom reason must be at least 10 characters long.',
    path: ['customReason'],
});

type FormValues = z.infer<typeof formSchema>;

type BlocklistFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSave: (entry: Omit<BlocklistEntry, 'id' | 'dateAdded' | 'addedBy'>, id?: string) => void;
  entry?: BlocklistEntry;
  visitors: Visitor[];
};

export function BlocklistForm({ children, open, onOpenChange, onSave, entry, visitors }: BlocklistFormProps) {
  const { toast } = useToast();
  const [isVisitorPopoverOpen, setVisitorPopoverOpen] = useState(false);
  
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      name: '',
      reason: '',
      customReason: '',
      photoUrl: null,
      isPermanent: true,
      expiryDate: null,
    },
  });

  useEffect(() => {
    if (entry) {
        const isPredefined = predefinedReasons.includes(entry.reason);
        form.reset({
            name: entry.name,
            reason: isPredefined ? entry.reason : 'Other',
            customReason: isPredefined ? '' : entry.reason,
            photoUrl: entry.photoUrl,
            isPermanent: !entry.expiryDate,
            expiryDate: entry.expiryDate
        });
    } else {
        form.reset({
            name: '',
            reason: '',
            customReason: '',
            photoUrl: null,
            isPermanent: true,
            expiryDate: null,
        });
    }
  }, [entry, form]);


  function onSubmit(values: FormValues) {
    const finalReason = values.reason === 'Other' ? values.customReason! : values.reason;
    const visitor = visitors.find(v => v.name === values.name);

    const entryToSave = {
        name: values.name,
        reason: finalReason,
        photoUrl: visitor?.idImageUrl || null,
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
                Select a visitor and provide a reason to add them to the blocklist.
              </DialogDescription>
            </DialogHeader>
            
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem className="flex flex-col">
                  <FormLabel>Visitor Name</FormLabel>
                   <Popover open={isVisitorPopoverOpen} onOpenChange={setVisitorPopoverOpen}>
                        <PopoverTrigger asChild>
                            <FormControl>
                                <Button
                                variant="outline"
                                role="combobox"
                                className={cn(
                                    "w-full justify-between",
                                    !field.value && "text-muted-foreground"
                                )}
                                >
                                {field.value
                                    ? visitors.find(
                                        (visitor) => visitor.name === field.value
                                    )?.name
                                    : "Select visitor"}
                                <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </FormControl>
                        </PopoverTrigger>
                        <PopoverContent className="w-[--radix-popover-trigger-width] p-0">
                            <Command>
                                <CommandInput placeholder="Search visitors..." />
                                <CommandList>
                                <CommandEmpty>No visitors found.</CommandEmpty>
                                <CommandGroup>
                                    {visitors.map((visitor) => (
                                    <CommandItem
                                        value={visitor.name}
                                        key={visitor.id}
                                        onSelect={() => {
                                        form.setValue("name", visitor.name);
                                        setVisitorPopoverOpen(false);
                                        }}
                                    >
                                        <Check
                                        className={cn(
                                            "mr-2 h-4 w-4",
                                            visitor.name === field.value
                                            ? "opacity-100"
                                            : "opacity-0"
                                        )}
                                        />
                                        {visitor.name}
                                    </CommandItem>
                                    ))}
                                </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
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
                        <Select onValueChange={field.onChange} defaultValue={field.value}>
                            <FormControl>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a reason" />
                                </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                                {predefinedReasons.map(reason => (
                                    <SelectItem key={reason} value={reason}>{reason}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FormMessage />
                    </FormItem>
                )}
            />

            {form.watch('reason') === 'Other' && (
                 <FormField
                    control={form.control}
                    name="customReason"
                    render={({ field }) => (
                        <FormItem>
                        <FormLabel>Custom Reason</FormLabel>
                        <FormControl>
                            <Textarea placeholder="Explain the specific reason..." {...field} />
                        </FormControl>
                        <FormMessage />
                        </FormItem>
                    )}
                />
            )}

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
