

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
import type { Donation, Fundraiser } from '@/types';
import { Switch } from '../ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';

const currencies = ["JMD", "USD", "GBP", "EUR", "CAD"] as const;

const formSchema = z.object({
  amount: z.coerce.number().min(1, 'Donation must be at least 1 unit of the selected currency.'),
  currency: z.enum(currencies),
  donorName: z.string().optional(),
  isAnonymous: z.boolean().default(false),
}).refine(data => !data.isAnonymous ? data.donorName && data.donorName.length > 0 : true, {
    message: "Name is required for non-anonymous donations.",
    path: ["donorName"],
});

type DonateFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  fundraiser: Fundraiser;
  onDonate: (newDonation: Omit<Donation, 'id'>) => void;
};

export function DonateForm({ children, open, onOpenChange, fundraiser, onDonate }: DonateFormProps) {
  const { toast } = useToast();
  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      amount: 25,
      isAnonymous: false,
      currency: 'USD',
    },
  });

  const isAnonymous = form.watch('isAnonymous');

  function onSubmit(values: z.infer<typeof formSchema>) {
    onDonate({ 
        ...values,
        fundraiserId: fundraiser.id,
        timestamp: new Date(),
    });
    toast({
      title: 'Donation Successful!',
      description: `Thank you for your donation of ${values.amount} ${values.currency} to the "${fundraiser.title}" fundraiser.`,
    });
    form.reset();
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
              <DialogTitle>Donate to: {fundraiser.title}</DialogTitle>
              <DialogDescription>
                Your contribution will help us reach our community goal. Thank you for your support!
              </DialogDescription>
            </DialogHeader>
            
            <div className="flex gap-2">
                <FormField
                control={form.control}
                name="amount"
                render={({ field }) => (
                    <FormItem className="flex-grow">
                    <FormLabel>Donation Amount</FormLabel>
                    <FormControl>
                        <Input type="number" {...field} />
                    </FormControl>
                    <FormMessage />
                    </FormItem>
                )}
                />
                 <FormField
                    control={form.control}
                    name="currency"
                    render={({ field }) => (
                        <FormItem className="w-1/3">
                        <FormLabel>Currency</FormLabel>
                        <Select onValueChange={field.onChange} defaultValue={field.value}>
                            <FormControl>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                                {currencies.map(c => <SelectItem key={c} value={c}>{c}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <FormMessage />
                        </FormItem>
                    )}
                    />
            </div>

            {!isAnonymous && (
                <FormField
                control={form.control}
                name="donorName"
                render={({ field }) => (
                    <FormItem>
                    <FormLabel>Your Name (as it will appear)</FormLabel>
                    <FormControl>
                        <Input placeholder="e.g., John D." {...field} />
                    </FormControl>
                    <FormMessage />
                    </FormItem>
                )}
                />
            )}

            <FormField
              control={form.control}
              name="isAnonymous"
              render={({ field }) => (
                <FormItem className="flex flex-row items-center justify-between rounded-lg border p-3 shadow-sm">
                  <div className="space-y-0.5">
                    <FormLabel>Donate Anonymously</FormLabel>
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
            
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
              <Button type="submit">Confirm Donation</Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
