

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
import { MoreHorizontal, PlusCircle, Search } from "lucide-react";
import type { Staff, ManagedUser } from "@/types";
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { useAuth } from '@/context/auth-context';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { StaffGatePassDialog } from '@/components/dashboard/staff-gate-pass-dialog';
import { StaffForm } from '@/components/dashboard/staff-form';
import { useToast } from '@/hooks/use-toast';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { MyGatePassDialog } from '@/components/dashboard/my-gate-pass-dialog';

const mockUsers: ManagedUser[] = [
    { id: 'usr_ho_1', name: 'Olivia Davis', email: 'olivia.d@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date('2023-01-15T09:00:00Z'), lotNumber: '42', streetName: 'Main St' },
    { id: 'usr_ho_2', name: 'John Smith', email: 'john.s@example.com', role: 'Homeowner', status: 'Inactive', createdAt: new Date('2023-02-20T11:00:00Z'), lotNumber: '12', streetName: 'Oak Ave' },
    { id: 'usr_ho_3', name: 'Jane Doe', email: 'jane.d@example.com', role: 'Homeowner', status: 'Active', createdAt: new Date('2022-11-05T14:20:00Z'), lotNumber: '3', streetName: 'Elm Circle' },
    { id: 'usr_th_1', name: 'Sam Wilson', email: 'sam.w@example.com', role: 'Temporary Homeowner', status: 'Active', createdAt: new Date('2024-06-01T14:00:00Z'), lotNumber: '15B', streetName: 'Pine Ln' },
];


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

function AdminView() {
    const [allUsers] = useState<ManagedUser[]>(mockUsers);
    const [searchTerm, setSearchTerm] = useState('');
    const [isPassOpen, setPassOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<ManagedUser | null>(null);

    const filteredUsers = allUsers.filter(user => 
        user.name.toLowerCase().includes(searchTerm.toLowerCase()) &&
        (user.role === 'Homeowner' || user.role === 'Temporary Homeowner')
    );
    
    const handleViewPass = (user: ManagedUser) => {
        setSelectedUser(user);
        setPassOpen(true);
    };

    return (
        <>
            {selectedUser && (
                 <Dialog open={isPassOpen} onOpenChange={setPassOpen}>
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                        <DialogTitle>Digital Gate Pass</DialogTitle>
                        <DialogDescription>
                            This is the digital ID for {selectedUser.name}.
                        </DialogDescription>
                        </DialogHeader>
                        
                        <div className="bg-gradient-to-br from-primary/80 to-accent/80 p-6 rounded-lg text-primary-foreground shadow-2xl relative overflow-hidden">
                            <div className="flex items-center gap-4">
                                <Avatar className="h-16 w-16 border-2 border-white/50">
                                    <AvatarImage src={`https://picsum.photos/100?q=${selectedUser.id}`} alt={selectedUser.name} data-ai-hint="person face" />
                                    <AvatarFallback>{selectedUser.name.charAt(0)}</AvatarFallback>
                                </Avatar>
                                <div>
                                    <p className="text-muted-foreground text-sm">{selectedUser.role}</p>
                                    <h3 className="font-bold text-xl">{selectedUser.name}</h3>
                                    <p className="text-sm">Lot {selectedUser.lotNumber}, {selectedUser.streetName}</p>
                                </div>
                            </div>

                            <div className="mt-6 p-4 bg-white rounded-md flex justify-center">
                                <QRCode value={`https://communityapp.com/id/${selectedUser.id}`} size={160} />
                            </div>
                            
                            <Badge className={selectedUser.status === 'Active' ? 'bg-green-500' : 'bg-destructive'}>
                                {selectedUser.status}
                            </Badge>
                        </div>
                    </DialogContent>
                </Dialog>
            )}
            <Card>
                <CardHeader>
                    <CardTitle>Digital ID Directory</CardTitle>
                    <CardDescription>
                        View and manage digital passes for all residents and renters.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="relative">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input 
                            placeholder="Search by name..." 
                            className="pl-8" 
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {filteredUsers.map(user => (
                            <Card key={user.id}>
                                <CardContent className="pt-6 flex flex-col items-center text-center gap-4">
                                    <Avatar className="h-20 w-20">
                                        <AvatarImage src={`https://picsum.photos/200?q=${user.id}`} data-ai-hint="person avatar" />
                                        <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <p className="font-semibold">{user.name}</p>
                                        <p className="text-sm text-muted-foreground">Lot {user.lotNumber}, {user.streetName}</p>
                                         <Badge className="mt-2" variant={user.status === 'Active' ? 'secondary' : 'outline'}>
                                            {user.status}
                                        </Badge>
                                    </div>
                                    <Button variant="outline" size="sm" onClick={() => handleViewPass(user)}>
                                        View Pass
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </>
    );
}


function HomeownerStaffView() {
    const { user } = useAuth();
    const { toast } = useToast();
    const [staffList, setStaffList] = useState<Staff[]>(mockStaff);
    const [selectedStaff, setSelectedStaff] = useState<Staff | null>(null);
    const [staffToEdit, setStaffToEdit] = useState<Staff | undefined>(undefined);
    const [isFormOpen, setFormOpen] = useState(false);
    
    // In a real app, filtering would be based on the logged-in user's properties or all properties for admins.
    const visibleStaff = staffList.filter(s => s.addedBy === user?.uid);

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
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Registered Staff</CardTitle>
                        <CardDescription>
                            A list of all personnel you have registered for long-term gate access.
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
        </>
    );
}

export default function GatePassPage() {
    const { user } = useAuth();
    
    const isAdmin = user?.role === 'System Admin' || user?.role === 'Admin';
    const isHomeownerOrRenter = user?.role === 'Homeowner' || user?.role === 'Temporary Homeowner';

    return (
        <div className="grid gap-8">
            <div>
                <h1 className="font-headline text-3xl font-bold">Gate Pass Management</h1>
                <p className="text-muted-foreground">
                    {isAdmin ? 'Manage digital IDs for all residents and renters.' : 'Manage long-term access for your personal staff.'}
                </p>
            </div>
            
            {isAdmin && <AdminView />}
            {isHomeownerOrRenter && <HomeownerStaffView />}
            
            {(user?.role === 'Security' || user?.role === 'Staff') && (
                <Card>
                    <CardHeader>
                        <CardTitle>Access Denied</CardTitle>
                        <CardDescription>You do not have permission to manage gate passes.</CardDescription>
                    </CardHeader>
                </Card>
            )}
        </div>
    );
}
