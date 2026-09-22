

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
import type { Guideline } from '@/types';

const formSchema = z.object({
  category: z.string().min(3, 'Category must be at least 3 characters.'),
  title: z.string().min(3, 'Title must be at least 3 characters.'),
  description: z.string().min(10, 'Description must be at least 10 characters.'),
});

type FormValues = z.infer<typeof formSchema>;

type GuidelineFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /**
   * Persists the change. Resolves when the server accepted it and rejects when
   * it refused, so the dialog only claims success for a save that happened.
   */
  onSave: (data: Omit<Guideline, 'id'>, id?: string) => Promise<void>;
  guideline?: Guideline;
};

export function GuidelineForm({ children, open, onOpenChange, onSave, guideline }: GuidelineFormProps) {
  const { toast } = useToast();
  
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
  });

  useEffect(() => {
    if (open) {
      if (guideline) {
        form.reset(guideline);
      } else {
        form.reset({ category: '', title: '', description: '' });
      }
    }
  }, [open, guideline, form]);

  async function onSubmit(values: FormValues) {
    try {
      await onSave(values, guideline?.id);
    } catch (message) {
      toast({
        variant: 'destructive',
        title: 'Guideline not saved',
        description: typeof message === 'string' ? message : 'The server refused the change. Please check the details and try again.',
      });
      return;
    }

    toast({
      title: guideline ? 'Guideline Updated' : 'Guideline Added',
      description: `The guideline "${values.title}" has been saved.`,
    });
    onOpenChange(false);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <DialogHeader>
              <DialogTitle>{guideline ? 'Edit' : 'Add'} Guideline</DialogTitle>
              <DialogDescription>
                Fill in the details for the community guideline.
              </DialogDescription>
            </DialogHeader>

            <FormField
              control={form.control}
              name="category"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Category</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., General Conduct" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <FormField
              control={form.control}
              name="title"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Title</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., Noise Levels" {...field} />
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
                    <Textarea placeholder="Explain the rule in detail..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
              <Button type="submit" disabled={form.formState.isSubmitting}>
                {form.formState.isSubmitting ? 'Saving…' : 'Save Guideline'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
