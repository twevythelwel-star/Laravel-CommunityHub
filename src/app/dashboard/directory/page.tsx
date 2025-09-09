
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
    { id: 'usr_ho_1', name: 'Olivia Davis', email: 'olivia.d@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date('2023-01-15T09:00:00Z') },
    { id: 'usr_ho_2', name: 'John Smith', email: 'john.s@example.com', role: 'Homeowner', status: 'Inactive', createdAt: new Date('2023-02-20T11:00:00Z') },
    { id: 'usr_ho_3', name: 'Jane Doe', email: 'jane.d@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date('2022-11-05T14:20:00Z') },
    { id: 'usr_ho_4', name: 'Carlos Gomez', email: 'carlos.g@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date() },
    { id: 'usr_ho_5', name: 'Aisha Khan', email: 'aisha.k@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date() },
    { id: 'usr_ho_6', name: 'Ben Carter', email: 'ben.c@example.com', role: 'Homeowner', status: 'Inactive', createdAt: new Date() },


    // Renters (Temporary Homeowners)
    { id: 'usr_th_1', name: 'Sam Wilson', email: 'sam.w@example.com', role: 'Temporary Homeowner', status: 'Active', createdAt: new Date('2024-06-01T14:00:00Z') },
    { id: 'usr_th_2', name: 'Mia Wong', email: 'mia.w@example.com', role: 'Temporary Homeowner', status: 'Active', createdAt: new Date() },
    { id: 'usr_th_3', name: 'Leo Martinez', email: 'leo.m@example.com', role: 'Temporary Homeowner', status: 'Inactive', createdAt: new Date() },

    // Security
    { id: 'usr_sec_1', name: 'Guard McSecurity', email: 'guard.m@example.com', role: 'Security', status: 'Active', createdAt: new Date('2023-03-10T18:00:00Z') },
    { id: 'usr_sec_2', name: 'Officer Barbrady', email: 'officer.b@example.com', role: 'Security', status: 'Active', createdAt: new Date() },
    { id: 'usr_sec_3', name: 'Patrol Person', email: 'patrol.p@example.com', role: 'Security', status: 'Inactive', createdAt: new Date() },
];

const roleOrder: UserRole[] = ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'];
const adminVisibleRoles: UserRole[] = ['Homeowner', 'Temporary Homeowner', 'Security'];
const ROOT_SYS_ADMIN_EMAIL = 'user-sysadmin@example.com';


export default function DirectoryPage() {
  const { user } = useAuth();
  const [users, setUsers] = useState<ManagedUser[]>(mockAllUsers);

  const canManageDirectory = user?.role === 'System Admin' || user?.role === 'Admin';

  const toggleStatus = (userId: string) => {
    setUsers(users.map(u => 
        u.id === userId 
        ? { ...u, status: u.status === 'Active' ? 'Inactive' : 'Active' }
        : u
    ));
  };

  const visibleRoles = user?.role === 'System Admin' 
    ? roleOrder
    : user?.role === 'Admin'
      ? adminVisibleRoles
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
      <div>
        <h1 className="font-headline text-3xl font-bold">Community Directory</h1>
        <p className="text-muted-foreground">View and manage all registered users in the community.</p>
      </div>

       <Card>
        <CardHeader>
          <CardTitle>User Directory</CardTitle>
          <CardDescription>
            Browse users by role. Admins can toggle user account status.
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
                                    const canToggle = !isRootSysAdmin && !isSelf && (user?.role === 'System Admin' || (user?.role === 'Admin' && person.role !== 'Admin' && person.role !== 'System Admin'));

                                    return (
                                    <Card key={person.id}>
                                        <CardContent className="pt-6 flex flex-col items-center text-center gap-4">
                                            <Avatar className="h-20 w-20">
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
                                            
                                            <Button 
                                                variant="outline"
                                                size="sm"
                                                onClick={() => toggleStatus(person.id)}
                                                disabled={!canToggle}
                                                title={
                                                    isRootSysAdmin ? "The root System Admin cannot be disabled." :
                                                    isSelf ? "You cannot change your own status." :
                                                    !canToggle ? "You do not have permission to change this user's status." : ""
                                                }
                                            >
                                                {person.status === 'Active' ? 'Set as Inactive' : 'Set as Active'}
                                            </Button>
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
