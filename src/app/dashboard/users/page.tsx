
'use client';

import { useState } from 'react';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { MoreHorizontal, PlusCircle } from "lucide-react";
import type { ManagedUser } from '@/types';
import { CreateUserForm } from '@/components/dashboard/create-user-form';
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { useAuth } from '@/context/auth-context';

const mockUsers: ManagedUser[] = [
    {
        id: 'usr_1',
        name: 'Olivia Davis',
        email: 'olivia.d@example.com',
        role: 'Homeowner',
        status: 'Active',
        createdAt: new Date('2023-01-15T09:00:00Z'),
    },
    {
        id: 'usr_2',
        name: 'John Smith',
        email: 'john.s@example.com',
        role: 'Homeowner',
        status: 'Active',
        createdAt: new Date('2023-02-20T11:00:00Z'),
    },
    {
        id: 'usr_3',
        name: 'Sam Wilson',
        email: 'sam.w@example.com',
        role: 'Temporary Homeowner',
        status: 'Active',
        createdAt: new Date('2024-06-01T14:00:00Z'),
    },
    {
        id: 'usr_4',
        name: 'Guard McSecurity',
        email: 'guard.m@example.com',
        role: 'Security',
        status: 'Inactive',
        createdAt: new Date('2023-03-10T18:00:00Z'),
    },
     {
        id: 'usr_5',
        name: 'Jane Doe',
        email: 'jane.d@example.com',
        role: 'Homeowner',
        status: 'Active',
        createdAt: new Date('2022-11-05T14:20:00Z'),
    },
];


export default function UsersPage() {
    const { user } = useAuth();
    const [users, setUsers] = useState<ManagedUser[]>(mockUsers);
    const [isCreateOpen, setCreateOpen] = useState(false);

    const handleCreateUser = (newUser: Omit<ManagedUser, 'id' | 'createdAt'>) => {
        const user: ManagedUser = {
            ...newUser,
            id: `usr_${Date.now()}`,
            createdAt: new Date(),
        };
        setUsers([user, ...users]);
    };

    const toggleUserStatus = (userId: string) => {
        setUsers(users.map(user => 
            user.id === userId 
            ? { ...user, status: user.status === 'Active' ? 'Inactive' : 'Active' }
            : user
        ));
    };

    const getRoleBadgeVariant = (role: ManagedUser['role']) => {
        switch (role) {
            case 'Homeowner': return 'default';
            case 'Temporary Homeowner': return 'secondary';
            case 'Security': return 'outline';
            default: return 'secondary';
        }
    }

    if (user?.role !== 'Admin' && user?.role !== 'System Admin') {
        return (
             <Card>
                <CardHeader>
                    <CardTitle>Access Denied</CardTitle>
                    <CardDescription>
                        You do not have permission to view this page.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p>Only Administrators can manage users.</p>
                </CardContent>
            </Card>
        )
    }


  return (
    <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">User Management</h1>
            <p className="text-muted-foreground">Create and manage Homeowner, Renter, and Security accounts.</p>
        </div>
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Community Users</CardTitle>
                    <CardDescription>
                        A list of all Homeowners, Renters, and Security personnel.
                    </CardDescription>
                </div>
                <CreateUserForm onOpenChange={setCreateOpen} open={isCreateOpen} onCreateUser={handleCreateUser}>
                    <Button size="sm" className="gap-1">
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Create User
                        </span>
                    </Button>
                </CreateUserForm>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>User</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Created At</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.map(user => (
                            <TableRow key={user.id}>
                                <TableCell>
                                    <div className="font-medium">{user.name}</div>
                                    <div className="text-sm text-muted-foreground">{user.email}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant={getRoleBadgeVariant(user.role)}>
                                        {user.role}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge variant={user.status === 'Active' ? 'secondary' : 'outline'}>
                                        {user.status}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <ClientFormattedDate date={user.createdAt} formatString="MMM d, yyyy" />
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                        <Button aria-haspopup="true" size="icon" variant="ghost">
                                            <MoreHorizontal className="h-4 w-4" />
                                            <span className="sr-only">Toggle menu</span>
                                        </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                            <DropdownMenuItem>View Profile</DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => toggleUserStatus(user.id)}>
                                                {user.status === 'Active' ? 'Deactivate' : 'Activate'}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
  );
}
