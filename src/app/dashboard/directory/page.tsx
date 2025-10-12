
'use client';

import { useState } from 'react';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/context/auth-context";
import type { ManagedUser, UserRole } from "@/types";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { MoreHorizontal, PlusCircle, Edit, Trash2 } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { CreateUserForm } from '@/components/dashboard/create-user-form';
import { useToast } from '@/hooks/use-toast';


const mockAllUsers: ManagedUser[] = [
    // System Admins
    {
        id: 'usr_sys_1',
        name: 'Root Sysadmin',
        email: 'user-sysadmin@example.com',
        role: 'System Admin',
        status: 'Active',
        createdAt: new Date('2023-01-10T09:00:00Z'),
    },
     {
        id: 'usr_sys_2',
        name: 'Secondary Sysadmin',
        email: 'user-sysadmin2@example.com',
        role: 'System Admin',
        status: 'Active',
        createdAt: new Date('2023-01-11T09:00:00Z'),
    },
    // Admins
    {
        id: 'usr_adm_1',
        name: 'Lead Admin',
        email: 'user-admin@example.com',
        role: 'Admin',
        status: 'Active',
        createdAt: new Date('2023-01-12T10:00:00Z'),
    },
    { id: 'usr_adm_2', name: 'Operations Admin', email: 'user-admin2@example.com', role: 'Admin', status: 'Active', createdAt: new Date() },
    { id: 'usr_adm_3', name: 'Community Admin', email: 'user-admin3@example.com', role: 'Admin', status: 'Inactive', createdAt: new Date() },
    { id: 'usr_adm_4', name: 'Finance Admin', email: 'user-admin4@example.com', role: 'Admin', status: 'Active', createdAt: new Date() },

    // Homeowners
    { id: 'usr_ho_1', name: 'Olivia Davis', email: 'olivia.d@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date('2023-01-15T09:00:00Z'), lotNumber: '42', streetName: 'Main St' },
    { id: 'usr_ho_2', name: 'John Smith', email: 'john.s@example.com', role: 'Homeowner', status: 'Inactive', createdAt: new Date('2023-02-20T11:00:00Z'), lotNumber: '12', streetName: 'Oak Ave' },
    { id: 'usr_ho_3', name: 'Jane Doe', email: 'jane.d@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date('2022-11-05T14:20:00Z'), lotNumber: '3', streetName: 'Elm Circle' },
    { id: 'usr_ho_4', name: 'Carlos Gomez', email: 'carlos.g@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date(), lotNumber: '21', streetName: 'Pine Ln' },
    { id: 'usr_ho_5', name: 'Aisha Khan', email: 'aisha.k@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date(), lotNumber: '88', streetName: 'Birch Rd' },
    { id: 'usr_ho_6', name: 'Ben Carter', email: 'ben.c@example.com', role: 'Homeowner', status: 'Inactive', createdAt: new Date(), lotNumber: '5', streetName: 'Willow Way' },


    // Renters (Temporary Homeowners)
    { id: 'usr_th_1', name: 'Sam Wilson', email: 'sam.w@example.com', role: 'Temporary Homeowner', status: 'Active', createdAt: new Date('2024-06-01T14:00:00Z'), lotNumber: '15B', streetName: 'Pine Ln' },
    { id: 'usr_th_2', name: 'Mia Wong', email: 'mia.w@example.com', role: 'Temporary Homeowner', status: 'Active', createdAt: new Date(), lotNumber: '3C', streetName: 'Maple Court' },
    { id: 'usr_th_3', name: 'Leo Martinez', email: 'leo.m@example.com', role: 'Temporary Homeowner', status: 'Inactive', createdAt: new Date(), lotNumber: '22A', streetName: 'Spruce Ave' },

    // Security
    { id: 'usr_sec_1', name: 'Guard McSecurity', email: 'guard.m@example.com', role: 'Security', status: 'Active', createdAt: new Date('2023-03-10T18:00:00Z') },
    { id: 'usr_sec_2', name: 'Officer Barbrady', email: 'officer.b@example.com', role: 'Security', status: 'Active', createdAt: new Date() },
    { id: 'usr_sec_3', name: 'Patrol Person', email: 'patrol.p@example.com', role: 'Security', status: 'Inactive', createdAt: new Date() },
];

const roleOrder: UserRole[] = ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'];
const adminVisibleRoles: UserRole[] = ['Homeowner', 'Temporary Homeowner', 'Security', 'Staff'];
const ROOT_SYS_ADMIN_EMAIL = 'user-sysadmin@example.com';


export default function DirectoryPage() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [users, setUsers] = useState<ManagedUser[]>(mockAllUsers);
  const [isFormOpen, setFormOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<ManagedUser | undefined>(undefined);

  const canManageDirectory = user?.role === 'System Admin' || user?.role === 'Admin';

  const handleOpenForm = (user?: ManagedUser) => {
    setSelectedUser(user);
    setFormOpen(true);
  };
  
  const handleCloseForm = () => {
    setSelectedUser(undefined);
    setFormOpen(false);
  };

  const handleSaveUser = (data: Omit<ManagedUser, 'id' | 'createdAt'>, id?: string) => {
    if (id) {
        // Update
        setUsers(users.map(u => u.id === id ? { ...u, ...data } : u));
        toast({ title: 'User Updated', description: `${data.name}'s profile has been updated.` });
    } else {
        // Create
        const newUser: ManagedUser = {
            id: `usr_${Date.now()}`,
            ...data,
            createdAt: new Date(),
        };
        setUsers([newUser, ...users].sort((a,b) => roleOrder.indexOf(a.role) - roleOrder.indexOf(b.role)));
        toast({ title: 'User Created', description: `${data.name} has been added as a new ${data.role}.` });
    }
  };

  const handleDeleteUser = (userId: string) => {
    const userToDelete = users.find(u => u.id === userId);
    if (userToDelete) {
        setUsers(users.filter(u => u.id !== userId));
        toast({ title: 'User Deleted', description: `${userToDelete.name} has been removed from the system.` });
    }
  };

  const toggleStatus = (userId: string) => {
    setUsers(users.map(u => 
        u.id === userId 
        ? { ...u, status: u.status === 'Active' ? 'Inactive' : 'Active' }
        : u
    ));
  };

  const visibleRoles = user?.role === 'System Admin' 
    ? roleOrder.filter(r => r !== 'Staff')
    : user?.role === 'Admin'
      ? adminVisibleRoles.filter(r => r !== 'Staff')
      : [];

  const groupedUsers = users.reduce((acc, currentUser) => {
    if (visibleRoles.includes(currentUser.role)) {
        (acc[currentUser.role] = acc[currentUser.role] || []).push(currentUser);
    }
    return acc;
  }, {} as Record<UserRole, ManagedUser[]>);


  if (!canManageDirectory) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Access Denied</CardTitle>
          <CardDescription>You do not have permission to view this page.</CardDescription>
        </CardHeader>
        <CardContent>
          <p>Only administrators can view the community directory.</p>
        </CardContent>
      </Card>
    );
  }
  
  return (
    <div className="grid gap-8">
      <div className="flex items-center justify-between">
        <div>
            <h1 className="font-headline text-3xl font-bold">Community Directory</h1>
            <p className="text-muted-foreground">View and manage all registered users in the community.</p>
        </div>
        <CreateUserForm
            open={isFormOpen}
            onOpenChange={handleCloseForm}
            onSaveUser={handleSaveUser}
            userToEdit={selectedUser}
        >
            <Button size="sm" className="gap-1" onClick={() => handleOpenForm()}>
                <PlusCircle className="h-3.5 w-3.5" />
                <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                    Add User
                </span>
            </Button>
        </CreateUserForm>
      </div>

       <Card>
        <CardHeader>
          <CardTitle>User Directory</CardTitle>
          <CardDescription>
            Browse users by role. Admins can create, edit, delete, and manage user accounts.
          </CardDescription>
        </CardHeader>
        <CardContent>
            <Accordion type="multiple" defaultValue={visibleRoles} className="w-full space-y-4">
                {visibleRoles.map(role => (
                    groupedUsers[role] && groupedUsers[role].length > 0 && (
                         <AccordionItem value={role} key={role} className="border-none">
                            <AccordionTrigger className="bg-muted hover:bg-muted/80 px-4 py-2 rounded-md text-lg font-semibold">
                                {role}s ({groupedUsers[role].length})
                            </AccordionTrigger>
                            <AccordionContent className="pt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {groupedUsers[role].map(person => {
                                    const isRootSysAdmin = person.email === ROOT_SYS_ADMIN_EMAIL;
                                    const isSelf = person.email === user?.email;
                                    const canManageUser = !isRootSysAdmin && !isSelf && (user?.role === 'System Admin' || (user?.role === 'Admin' && person.role !== 'Admin' && person.role !== 'System Admin'));

                                    return (
                                    <Card key={person.id}>
                                        <CardHeader className="flex-row gap-4 items-center !pb-2">
                                             <Avatar className="h-12 w-12">
                                                <AvatarImage src={`https://picsum.photos/200?q=${person.id}`} data-ai-hint="person avatar" />
                                                <AvatarFallback>{person.name.charAt(0)}</AvatarFallback>
                                            </Avatar>
                                            <div>
                                                <p className="font-semibold">{person.name}</p>
                                                <p className="text-sm text-muted-foreground">{person.email}</p>
                                                <Badge className="mt-2" variant={person.status === 'Active' ? 'secondary' : 'outline'}>
                                                    {person.status}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pt-2">
                                            {canManageUser ? (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="outline" size="sm" className="w-full">
                                                            <MoreHorizontal className="h-4 w-4 mr-2" />
                                                            Manage User
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="w-40">
                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                        <DropdownMenuItem onClick={() => handleOpenForm(person)}>
                                                            <Edit className="mr-2 h-4 w-4" /> Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem onClick={() => toggleStatus(person.id)}>
                                                            {person.status === 'Active' ? 'Set as Inactive' : 'Set as Active'}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <AlertDialog>
                                                            <AlertDialogTrigger asChild>
                                                                <DropdownMenuItem onSelect={(e) => e.preventDefault()} className="text-destructive">
                                                                     <Trash2 className="mr-2 h-4 w-4" /> Delete
                                                                </DropdownMenuItem>
                                                            </AlertDialogTrigger>
                                                            <AlertDialogContent>
                                                                <AlertDialogHeader>
                                                                    <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                                                    <AlertDialogDescription>
                                                                        This will permanently delete the user "{person.name}". This action cannot be undone.
                                                                    </Description>
                                                                </AlertDialogHeader>
                                                                <AlertDialogFooter>
                                                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                                    <AlertDialogAction onClick={() => handleDeleteUser(person.id)}>Yes, delete</AlertDialogAction>
                                                                </AlertDialogFooter>
                                                            </AlertDialogContent>
                                                        </AlertDialog>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            ) : (
                                                <Button 
                                                    variant="outline"
                                                    size="sm"
                                                    className="w-full"
                                                    disabled
                                                    title={
                                                        isRootSysAdmin ? "The root System Admin cannot be managed." :
                                                        isSelf ? "You cannot manage your own account." :
                                                        "You do not have permission to manage this user."
                                                    }
                                                >
                                                    Manage User
                                                </Button>
                                            )}
                                        </CardContent>
                                    </Card>
                                )})}
                            </AccordionContent>
                        </AccordionItem>
                    )
                ))}
            </Accordion>
        </CardContent>
      </Card>
    </div>
  );
}
