
'use client';

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
import type { BlocklistEntry } from '@/types';
import { Textarea } from '../ui/textarea';


const formSchema = z.object({
  reason: z.string().min(10, 'A reason of at least 10 characters is required.'),
});

type FormValues = z.infer<typeof formSchema>;

type RequestRemovalFormProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  entry: BlocklistEntry;
};

export function RequestRemovalForm({ open, onOpenChange, entry }: RequestRemovalFormProps) {
  const { toast } = useToast();
  
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      reason: '',
    },
  });

  function onSubmit(values: FormValues) {
    console.log({
        message: "Removal Request Submitted",
        blocklistEntryId: entry.id,
        reason: values.reason,
    })
    toast({
      title: 'Request Sent',
      description: `Your request to remove ${entry.name} from the blocklist has been sent to the administrators for review.`,
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
      <DialogContent className="sm:max-w-md">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Request Removal for {entry.name}</DialogTitle>
              <DialogDescription>
                Please provide a reason for requesting this individual&apos;s removal from the blocklist. This will be sent to an administrator for review.
              </DialogDescription>
            </DialogHeader>
            
             <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Reason for Request</FormLabel>
                  <FormControl>
                    <Textarea placeholder="Explain why this person should be removed..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
              <Button type="submit">Submit Request</Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
