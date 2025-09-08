
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
import type { AdminUser } from '@/types';
import { CreateAdminForm } from '@/components/dashboard/create-admin-form';
import { AdminActivityLog } from '@/components/dashboard/admin-activity-log';
import { ClientFormattedDate } from '@/components/client-formatted-date';

const mockAdmins: AdminUser[] = [
    {
        id: 'adm_1',
        name: 'Alice Johnson',
        email: 'alice.j@example.com',
        status: 'Active',
        createdAt: new Date('2023-10-15T09:00:00Z'),
        activity: [
            { id: 'act_1', timestamp: new Date(), action: 'Created a notification: "Pool Maintenance"' },
            { id: 'act_2', timestamp: new Date(Date.now() - 86400000), action: 'Sent a warning: "Suspicious Vehicle"' },
        ]
    },
    {
        id: 'adm_2',
        name: 'Bob Williams',
        email: 'bob.w@example.com',
        status: 'Active',
        createdAt: new Date('2023-09-01T14:20:00Z'),
        activity: [
            { id: 'act_3', timestamp: new Date(Date.now() - 172800000), action: 'Updated billing information for Lot 42' },
        ]
    },
    {
        id: 'adm_3',
        name: 'Charlie Brown',
        email: 'charlie.b@example.com',
        status: 'Inactive',
        createdAt: new Date('2023-05-20T11:00:00Z'),
        activity: []
    }
];


export default function UsersPage() {
    const [admins, setAdmins] = useState<AdminUser[]>(mockAdmins);
    const [selectedAdmin, setSelectedAdmin] = useState<AdminUser | null>(null);
    const [isCreateOpen, setCreateOpen] = useState(false);
    const [isActivityLogOpen, setActivityLogOpen] = useState(false);

    const handleCreateAdmin = (newAdmin: Omit<AdminUser, 'id' | 'createdAt' | 'activity'>) => {
        const admin: AdminUser = {
            ...newAdmin,
            id: `adm_${Date.now()}`,
            createdAt: new Date(),
            activity: [],
        };
        setAdmins([admin, ...admins]);
    };

    const handleViewActivity = (admin: AdminUser) => {
        setSelectedAdmin(admin);
        setActivityLogOpen(true);
    };

    const toggleAdminStatus = (adminId: string) => {
        setAdmins(admins.map(admin => 
            admin.id === adminId 
            ? { ...admin, status: admin.status === 'Active' ? 'Inactive' : 'Active' }
            : admin
        ));
    };

  return (
    <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">User Management</h1>
            <p className="text-muted-foreground">Create and manage Admin accounts for the platform.</p>
        </div>
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Administrator Accounts</CardTitle>
                    <CardDescription>
                        A list of all Admin users with access to the system.
                    </CardDescription>
                </div>
                <CreateAdminForm onOpenChange={setCreateOpen} open={isCreateOpen} onCreateAdmin={handleCreateAdmin}>
                    <Button size="sm" className="gap-1">
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Create Admin
                        </span>
                    </Button>
                </CreateAdminForm>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Admin</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Created At</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {admins.map(admin => (
                            <TableRow key={admin.id}>
                                <TableCell>
                                    <div className="font-medium">{admin.name}</div>
                                    <div className="text-sm text-muted-foreground">{admin.email}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant={admin.status === 'Active' ? 'secondary' : 'outline'}>
                                        {admin.status}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <ClientFormattedDate date={admin.createdAt} formatString="MMM d, yyyy" />
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
                                            <DropdownMenuItem onClick={() => handleViewActivity(admin)}>View Activity</DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => toggleAdminStatus(admin.id)}>
                                                {admin.status === 'Active' ? 'Deactivate' : 'Activate'}
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
        {selectedAdmin && (
             <AdminActivityLog 
                open={isActivityLogOpen} 
                onOpenChange={setActivityLogOpen}
                admin={selectedAdmin}
            />
        )}
    </div>
  );
}
