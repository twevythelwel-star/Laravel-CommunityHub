
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
import type { ManagedUser, UserRole } from '@/types';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { useEffect } from 'react';

const creatableRoles = ["Homeowner", "Temporary Homeowner", "Security", "Admin"] as const;

const formSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters.'),
  email: z.string().email('Please enter a valid email address.'),
  role: z.enum(creatableRoles),
  status: z.enum(['Active', 'Inactive']),
  lotNumber: z.string().optional(),
  streetName: z.string().optional(),
}).refine(data => {
    if ((data.role === 'Homeowner' || data.role === 'Temporary Homeowner') && (!data.lotNumber || !data.streetName)) {
        return false;
    }
    return true;
}, {
    message: 'Lot number and street name are required for Homeowners and Renters.',
    path: ['lotNumber'], // Show error on the first of the two fields
});

type FormValues = z.infer<typeof formSchema>;

type CreateUserFormProps = {
  children: React.ReactNode;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSaveUser: (data: Omit<ManagedUser, 'id' | 'createdAt'>, id?: string) => void;
  userToEdit?: ManagedUser;
};

export function CreateUserForm({ children, open, onOpenChange, onSaveUser, userToEdit }: CreateUserFormProps) {
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      name: '',
      email: '',
      role: 'Homeowner',
      status: 'Active',
      lotNumber: '',
      streetName: '',
    },
  });

  useEffect(() => {
    if (open && userToEdit) {
        form.reset({
            name: userToEdit.name,
            email: userToEdit.email,
            role: userToEdit.role as any, // Cast because creatableRoles is a subset
            status: userToEdit.status,
            lotNumber: userToEdit.lotNumber || '',
            streetName: userToEdit.streetName || '',
        });
    } else if (open && !userToEdit) {
        form.reset({
            name: '',
            email: '',
            role: 'Homeowner',
            status: 'Active',
            lotNumber: '',
            streetName: '',
        });
    }
  }, [open, userToEdit, form])

  const selectedRole = form.watch('role');
  const showAddressFields = selectedRole === 'Homeowner' || selectedRole === 'Temporary Homeowner';


  function onSubmit(values: FormValues) {
    onSaveUser({ ...values, role: values.role as UserRole }, userToEdit?.id);
    onOpenChange(false);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-[425px]">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <DialogHeader>
              <DialogTitle>{userToEdit ? 'Edit User' : 'Create New User'}</DialogTitle>
              <DialogDescription>
                {userToEdit ? `Update the profile details for ${userToEdit.name}.` : 'Fill out the form to create a new user account.'}
              </DialogDescription>
            </DialogHeader>
            
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Full Name</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., Jane Doe" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email Address</FormLabel>
                  <FormControl>
                    <Input type="email" placeholder="e.g., jane.d@example.com" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            
             <div className="grid grid-cols-2 gap-4">
                <FormField
                control={form.control}
                name="role"
                render={({ field }) => (
                    <FormItem>
                    <FormLabel>Role</FormLabel>
                    <Select onValueChange={field.onChange} defaultValue={field.value} value={field.value}>
                        <FormControl>
                        <SelectTrigger>
                            <SelectValue placeholder="Select a role" />
                        </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                            {creatableRoles.map(role => (
                                <SelectItem key={role} value={role}>{role}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <FormMessage />
                    </FormItem>
                )}
                />
                 <FormField
                control={form.control}
                name="status"
                render={({ field }) => (
                    <FormItem>
                    <FormLabel>Status</FormLabel>
                    <Select onValueChange={field.onChange} defaultValue={field.value} value={field.value}>
                        <FormControl>
                        <SelectTrigger>
                            <SelectValue placeholder="Select a status" />
                        </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                           <SelectItem value="Active">Active</SelectItem>
                           <SelectItem value="Inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                    <FormMessage />
                    </FormItem>
                )}
                />
            </div>


            {showAddressFields && (
                <div className="grid grid-cols-2 gap-4">
                    <FormField
                        control={form.control}
                        name="lotNumber"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Lot Number</FormLabel>
                                <FormControl>
                                    <Input placeholder="e.g., 42" {...field} />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                    <FormField
                        control={form.control}
                        name="streetName"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Street Name</FormLabel>
                                <FormControl>
                                    <Input placeholder="e.g., Main St" {...field} />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                </div>
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
