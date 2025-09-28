
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
import { MoreHorizontal, PlusCircle } from "lucide-react";
import type { Staff } from "@/types";
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { useAuth } from '@/context/auth-context';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { StaffGatePassDialog } from '@/components/dashboard/staff-gate-pass-dialog';
import { StaffForm } from '@/components/dashboard/staff-form';
import { useToast } from '@/hooks/use-toast';

const mockStaff: Staff[] = [
    { 
        id: 'staff_1', 
        name: 'Maria Garcia', 
        job: 'Housekeeper', 
        idType: "National ID", 
        idNumber: "123456789", 
        idExpiry: new Date('2028-12-31'), 
        property: 'Lot 12, Main St', 
        addedBy: 'user-homeowner', 
        status: 'Active',
        photoUrl: 'https://picsum.photos/seed/maria/200'
    },
    { 
        id: 'staff_2', 
        name: 'David Kim', 
        job: 'Gardener', 
        idType: "Driver's License", 
        idNumber: "987654321", 
        idExpiry: new Date('2024-05-31'), 
        property: 'Lot 25, Oak Ave', 
        addedBy: 'user-homeowner-2', 
        status: 'Expired ID',
        photoUrl: 'https://picsum.photos/seed/david/200'
    },
    { 
        id: 'staff_3', 
        name: 'Chen Wei', 
        job: 'Nanny', 
        idType: "Passport", 
        idNumber: "G12345678", 
        idExpiry: new Date('2026-08-15'), 
        property: 'Lot 12, Main St', 
        addedBy: 'user-homeowner', 
        status: 'Inactive',
        photoUrl: 'https://picsum.photos/seed/chen/200'
    },
];

export default function GatePassPage() {
    const { user } = useAuth();
    const { toast } = useToast();
    const [staffList, setStaffList] = useState<Staff[]>(mockStaff);
    const [selectedStaff, setSelectedStaff] = useState<Staff | null>(null);
    const [staffToEdit, setStaffToEdit] = useState<Staff | undefined>(undefined);
    const [isFormOpen, setFormOpen] = useState(false);
    
    // In a real app, filtering would be based on the logged-in user's properties or all properties for admins.
    const visibleStaff = user?.role === 'System Admin' || user?.role === 'Admin' || user?.role === 'Security'
        ? staffList
        : staffList.filter(s => s.addedBy === user?.uid);

    const getStatusVariant = (status: Staff['status']) => {
        switch (status) {
            case 'Active': return 'secondary';
            case 'Inactive': return 'outline';
            case 'Expired ID': return 'destructive';
            default: return 'default';
        }
    }

    const handleOpenForm = (staff?: Staff) => {
        setStaffToEdit(staff);
        setFormOpen(true);
    };

    const handleSaveStaff = (data: Omit<Staff, 'id' | 'addedBy'>, id?: string) => {
        if (id) {
            setStaffList(staffList.map(s => s.id === id ? { ...s, ...data } : s));
            toast({ title: "Staff Updated", description: `${data.name}'s details have been updated.`});
        } else {
            const newStaff: Staff = {
                id: `staff_${Date.now()}`,
                ...data,
                addedBy: user!.uid, // Safe to assume user exists
            };
            setStaffList([newStaff, ...staffList]);
            toast({ title: "Staff Added", description: `${data.name} has been registered.`});
        }
    };
    
    const handleRevokeAccess = (staffId: string) => {
        setStaffList(staffList.map(s => s.id === staffId ? { ...s, status: 'Inactive' } : s));
        toast({
            variant: "destructive",
            title: "Access Revoked",
            description: "The staff member's access has been set to inactive."
        });
    }

  return (
    <>
    {selectedStaff && (
        <StaffGatePassDialog
            staff={selectedStaff}
            open={!!selectedStaff}
            onOpenChange={() => setSelectedStaff(null)}
        />
    )}
    <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">Gate Pass Management</h1>
            <p className="text-muted-foreground">Manage long-term access for staff and other personnel.</p>
        </div>
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Registered Staff</CardTitle>
                    <CardDescription>
                        A list of all personnel with long-term gate access.
                    </CardDescription>
                </div>
                <StaffForm
                    open={isFormOpen}
                    onOpenChange={setFormOpen}
                    onSave={handleSaveStaff}
                    staff={staffToEdit}
                >
                    <Button size="sm" className="gap-1" onClick={() => handleOpenForm()}>
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Add Staff
                        </span>
                    </Button>
                </StaffForm>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Job / Role</TableHead>
                            <TableHead>Property</TableHead>
                            <TableHead>ID Expiry</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {visibleStaff.map((staff) => (
                            <TableRow key={staff.id}>
                                <TableCell className="font-medium">{staff.name}</TableCell>
                                <TableCell>{staff.job}</TableCell>
                                <TableCell>{staff.property}</TableCell>
                                <TableCell>
                                    <ClientFormattedDate date={staff.idExpiry} formatString="MMM d, yyyy" />
                                </TableCell>
                                 <TableCell>
                                    <Badge variant={getStatusVariant(staff.status)}>
                                        {staff.status}
                                    </Badge>
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
                                            <DropdownMenuItem onClick={() => setSelectedStaff(staff)}>
                                                View Pass
                                            </DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => handleOpenForm(staff)}>Edit Details</DropdownMenuItem>
                                            <DropdownMenuItem>Update ID</DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                             <DropdownMenuItem className="text-destructive" onClick={() => handleRevokeAccess(staff.id)}>Revoke Access</DropdownMenuItem>
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
    </>
  );
}
