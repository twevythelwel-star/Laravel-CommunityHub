
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
import type { FoodApp } from '@/types';

const formSchema = z.object({
  name: z.string().min(2, 'Service name must be at least 2 characters.'),
  websiteUrl: z.string().url('Please enter a valid URL.'),
  logoUrl: z.string().url('Please enter a valid logo URL.'),
  aiHint: z.string().min(1, 'AI hint is required.').max(20, 'AI hint is too long.'),
});

type FormValues = z.infer<typeof formSchema>;

type FoodAppFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSave: (app: Omit<FoodApp, 'id'>) => void;
  app?: FoodApp;
};

export function FoodAppForm({ children, open, onOpenChange, onSave, app }: FoodAppFormProps) {
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      name: '',
      websiteUrl: '',
      logoUrl: 'https://picsum.photos/seed/newapp/100/100',
      aiHint: 'food delivery logo'
    },
  });

  useEffect(() => {
    if (app) {
      form.reset(app);
    } else {
       form.reset({
        name: '',
        websiteUrl: '',
        logoUrl: 'https://picsum.photos/seed/newapp/100/100',
        aiHint: 'food delivery logo'
       });
    }
  }, [app, form, open]);


  function onSubmit(values: FormValues) {
    onSave(values);
    onOpenChange(false);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <DialogHeader>
              <DialogTitle>{app ? 'Edit' : 'Add'} Food Delivery Service</DialogTitle>
              <DialogDescription>
                Fill in the details for the delivery service.
              </DialogDescription>
            </DialogHeader>
            
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Service Name</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., DoorDash" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <FormField
              control={form.control}
              name="websiteUrl"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Website URL</FormLabel>
                  <FormControl>
                    <Input placeholder="https://www.doordash.com" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="logoUrl"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Logo URL</FormLabel>
                  <FormControl>
                    <Input placeholder="https://picsum.photos/seed/logo/100/100" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="aiHint"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>AI Hint for Logo</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., food delivery logo" {...field} />
                  </FormControl>
                   <p className="text-xs text-muted-foreground">A few keywords to help AI find a replacement image.</p>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
              <Button type="submit">Save Service</Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

    