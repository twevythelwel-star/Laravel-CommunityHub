

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
import { MoreHorizontal, PlusCircle } from "lucide-react";
import type { Renter } from "@/types";
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { RegisterRenterForm } from '@/components/dashboard/register-renter-form';
import { useAuth } from '@/context/auth-context';


const mockRenters: Renter[] = [
    { id: 'rnt_1', name: 'Alex Ray', status: 'Active', leaseStart: new Date('2024-01-01'), leaseEnd: new Date('2024-12-31') },
    { id: 'rnt_2', name: 'Jordan Smith', status: 'Inactive', leaseStart: new Date('2023-05-01'), leaseEnd: new Date('2024-04-30') },
];

export default function RentersPage() {
    const { user } = useAuth();
    const [renters, setRenters] = useState<Renter[]>(mockRenters);
    const [isCreateOpen, setCreateOpen] = useState(false);

    const handleCreateRenter = (newRenter: Omit<Renter, 'id'>) => {
        const renter: Renter = {
            ...newRenter,
            id: `rnt_${Date.now()}`,
        };
        setRenters([renter, ...renters]);
    };

    const toggleRenterStatus = (renterId: string) => {
        setRenters(renters.map(r => 
            r.id === renterId 
            ? { ...r, status: r.status === 'Active' ? 'Inactive' : 'Active' }
            : r
        ));
    };

    if (user?.role !== 'Homeowner') {
         return (
            <Card>
                <CardHeader>
                    <CardTitle>Access Denied</CardTitle>
                </CardHeader>
                <CardContent>
                    <p>You do not have permission to view this page.</p>
                </CardContent>
            </Card>
        )
    }

  return (
    <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">My Renters</h1>
            <p className="text-muted-foreground">Manage renters associated with your property.</p>
        </div>
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Registered Renters</CardTitle>
                    <CardDescription>
                        A list of all renters for your property.
                    </CardDescription>
                </div>
                <RegisterRenterForm onOpenChange={setCreateOpen} open={isCreateOpen} onCreateRenter={handleCreateRenter}>
                    <Button size="sm" className="gap-1">
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Register Renter
                        </span>
                    </Button>
                </RegisterRenterForm>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Renter</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Lease Start</TableHead>
                            <TableHead>Lease End</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {renters.map((renter) => (
                            <TableRow key={renter.id}>
                                <TableCell className="font-medium">{renter.name}</TableCell>
                                <TableCell>
                                    <Badge variant={renter.status === 'Active' ? 'secondary' : 'outline'}>
                                        {renter.status}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <ClientFormattedDate date={renter.leaseStart} formatString="MMM d, yyyy" />
                                </TableCell>
                                <TableCell>
                                     <ClientFormattedDate date={renter.leaseEnd} formatString="MMM d, yyyy" />
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
                                            <DropdownMenuItem>Edit Lease</DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => toggleRenterStatus(renter.id)}>
                                                {renter.status === 'Active' ? 'Set as Inactive' : 'Set as Active'}
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
