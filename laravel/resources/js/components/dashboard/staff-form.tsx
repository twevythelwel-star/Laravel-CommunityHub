

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
import type { Staff } from '@/types';
import { Popover, PopoverContent, PopoverTrigger } from '../ui/popover';
import { CalendarIcon } from 'lucide-react';
import { Calendar } from '../ui/calendar';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { useAuth } from '@/context/auth-context';

const idTypes = ["National ID", "Driver's License", "Passport"] as const;

const formSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters.'),
  job: z.string().min(2, 'Job/role must be at least 2 characters.'),
  property: z.string().min(3, 'Property is required.'),
  photoUrl: z.string().url().optional(),
  status: z.enum(['Active', 'Inactive', 'Expired ID']),
  idType: z.enum(idTypes),
  idNumber: z.string().min(5, 'ID number is required.'),
  idExpiry: z.date({ required_error: 'ID expiry date is required.' }),
});

export type StaffFormValues = z.infer<typeof formSchema>;

type FormValues = StaffFormValues;

/**
 * Existing record to edit. Loosened from the mock-data `Staff` type: server rows
 * carry a numeric id and an ISO date string where the mock used a string id and
 * a `Date`, so the expiry is accepted in either form and normalised below.
 */
export type StaffFormSubject = {
  id: number | string;
  name: string;
  job: string;
  property: string;
  status: 'Active' | 'Inactive' | 'Expired ID';
  idType?: string;
  idNumber?: string;
  idExpiry?: string | Date;
  photoUrl?: string | null;
};

type StaffFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /**
   * Receives camelCase form values. The caller is responsible for mapping them
   * to the snake_case payload the controller validates.
   */
  onSave: (data: StaffFormValues, id?: number | string) => void;
  staff?: StaffFormSubject;
};

export function StaffForm({ children, open, onOpenChange, onSave, staff }: StaffFormProps) {
  const { user } = useAuth();
  const { toast } = useToast();
  
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      status: 'Active',
      photoUrl: '',
    },
  });

  useEffect(() => {
    if (open) {
      if (staff) {
        form.reset({
            name: staff.name,
            job: staff.job,
            property: staff.property,
            status: staff.status,
            idType: staff.idType as FormValues['idType'],
            idNumber: staff.idNumber ?? '',
            // The server sends an ISO string; the date picker needs a Date.
            idExpiry: staff.idExpiry ? new Date(staff.idExpiry) : undefined,
            photoUrl: staff.photoUrl || '',
        });
      } else {
        form.reset({
          name: '',
          job: '',
          property: '',
          photoUrl: 'https://picsum.photos/seed/newstaff/200',
          status: 'Active',
          idType: undefined,
          idNumber: '',
          idExpiry: undefined,
        });
      }
    }
  }, [open, staff, form]);

  function onSubmit(values: FormValues) {
    onSave(values, staff?.id);
    onOpenChange(false);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <DialogHeader>
              <DialogTitle>{staff ? 'Edit Staff Details' : 'Register New Staff'}</DialogTitle>
              <DialogDescription>
                Fill in the details to register a staff member for gate access.
              </DialogDescription>
            </DialogHeader>

            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Full Name</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., John Smith" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <FormField
              control={form.control}
              name="job"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Job / Role</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., Gardener, Nanny" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
            <FormField
              control={form.control}
              name="property"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Property</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., Lot 42, Main St" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
                control={form.control}
                name="idType"
                render={({ field }) => (
                    <FormItem>
                        <FormLabel>ID Type</FormLabel>
                        <Select onValueChange={field.onChange} defaultValue={field.value}>
                            <FormControl>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select an ID type" />
                                </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                                {idTypes.map(type => (
                                    <SelectItem key={type} value={type}>{type}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FormMessage />
                    </FormItem>
                )}
            />
            
            <FormField
              control={form.control}
              name="idNumber"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>ID Number</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

             <FormField
                control={form.control}
                name="idExpiry"
                render={({ field }) => (
                    <FormItem className="flex flex-col">
                    <FormLabel>ID Expiry Date</FormLabel>
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
            
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
              <Button type="submit">Save Staff</Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
