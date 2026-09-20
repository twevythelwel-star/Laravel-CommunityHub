'use client';

import { useState, useMemo } from 'react';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useAuth } from "@/context/auth-context";
import type { ManagedUser, UserRole } from "@/types";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { MoreHorizontal, PlusCircle, Edit, Trash2, Search, MapPin, Mail, ShieldAlert } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { CreateUserForm } from '@/components/dashboard/create-user-form';
import { useToast } from '@/hooks/use-toast';

// Enterprise sanitized user directory dataset
const mockAllUsers: ManagedUser[] = [
    // System Admins
    {
        id: 'usr_sys_1',
        name: 'Alexander Wright',
        email: 'alexander.wright@communityhub.org',
        role: 'System Admin',
        status: 'Active',
        createdAt: new Date('2023-01-10T09:00:00Z'),
        lotNumber: 'HQ-01',
        streetName: 'Executive Pavilion',
    },
    {
        id: 'usr_sys_2',
        name: 'Jonathan Bailey',
        email: 'jonathan.bailey@communityhub.org',
        role: 'System Admin',
        status: 'Active',
        createdAt: new Date('2023-01-11T09:00:00Z'),
        lotNumber: 'HQ-02',
        streetName: 'Executive Pavilion',
    },
    // Admins
    {
        id: 'usr_adm_1',
        name: 'Elena Rostova',
        email: 'elena.rostova@communityhub.org',
        role: 'Admin',
        status: 'Active',
        createdAt: new Date('2023-01-12T10:00:00Z'),
        lotNumber: 'Suite A',
        streetName: 'Central Clubhouse Way',
    },
    { 
        id: 'usr_adm_2', 
        name: 'David Sterling', 
        email: 'david.sterling@communityhub.org', 
        role: 'Admin', 
        status: 'Active', 
        createdAt: new Date('2023-02-01T10:00:00Z'),
        lotNumber: 'Suite B',
        streetName: 'Central Clubhouse Way',
    },
    { 
        id: 'usr_adm_3', 
        name: 'Maya Vance', 
        email: 'maya.vance@communityhub.org', 
        role: 'Admin', 
        status: 'Active', 
        createdAt: new Date('2023-03-15T10:00:00Z'),
        lotNumber: 'Suite C',
        streetName: 'Central Clubhouse Way',
    },

    // Homeowners
    { 
        id: 'usr_ho_1', 
        name: 'Marcus Vance', 
        email: 'marcus.vance@residence.net', 
        role: 'Homeowner', 
        status: 'Active', 
        createdAt: new Date('2023-01-15T09:00:00Z'), 
        lotNumber: '42', 
        streetName: 'Royal Palm Drive' 
    },
    { 
        id: 'usr_ho_2', 
        name: 'Olivia Davis', 
        email: 'olivia.davis@residence.net', 
        role: 'Homeowner', 
        status: 'Active', 
        createdAt: new Date('2023-02-20T11:00:00Z'), 
        lotNumber: '12', 
        streetName: 'Bougainvillea Way' 
    },
    { 
        id: 'usr_ho_3', 
        name: 'Carlos Gomez', 
        email: 'carlos.gomez@residence.net', 
        role: 'Homeowner', 
        status: 'Active', 
        createdAt: new Date('2022-11-05T14:20:00Z'), 
        lotNumber: '21', 
        streetName: 'Pine Lane' 
    },
    { 
        id: 'usr_ho_4', 
        name: 'Aisha Khan', 
        email: 'aisha.khan@residence.net', 
        role: 'Homeowner', 
        status: 'Active', 
        createdAt: new Date('2023-04-10T14:00:00Z'), 
        lotNumber: '88', 
        streetName: 'Birch Road' 
    },
    { 
        id: 'usr_ho_5', 
        name: 'Gregory Campbell', 
        email: 'gregory.campbell@residence.net', 
        role: 'Homeowner', 
        status: 'Active', 
        createdAt: new Date('2023-05-18T16:00:00Z'), 
        lotNumber: '5', 
        streetName: 'Willow Way' 
    },

    // Temporary Homeowners (Renters)
    { 
        id: 'usr_th_1', 
        name: 'Sophia Taylor', 
        email: 'sophia.taylor@residence.net', 
        role: 'Temporary Homeowner', 
        status: 'Active', 
        createdAt: new Date('2024-06-01T14:00:00Z'), 
        lotNumber: '15B', 
        streetName: 'Hibiscus Crescent' 
    },
    { 
        id: 'usr_th_2', 
        name: 'Samuel Wilson', 
        email: 'samuel.wilson@residence.net', 
        role: 'Temporary Homeowner', 
        status: 'Active', 
        createdAt: new Date('2024-06-15T12:00:00Z'), 
        lotNumber: '3C', 
        streetName: 'Maple Court' 
    },
    { 
        id: 'usr_th_3', 
        name: 'Leo Martinez', 
        email: 'leo.martinez@residence.net', 
        role: 'Temporary Homeowner', 
        status: 'Active', 
        createdAt: new Date('2024-07-01T10:00:00Z'), 
        lotNumber: '22A', 
        streetName: 'Spruce Avenue' 
    },

    // Security Personnel
    { 
        id: 'usr_sec_1', 
        name: 'Apex Security Command', 
        email: 'dispatch@apexguard.com', 
        role: 'Security', 
        status: 'Active', 
        createdAt: new Date('2023-03-10T18:00:00Z'),
        lotNumber: 'Gatehouse 1',
        streetName: 'Main Perimeter Entrance'
    },
    { 
        id: 'usr_sec_2', 
        name: 'Roland Sterling', 
        email: 'r.sterling@apexguard.com', 
        role: 'Security', 
        status: 'Active', 
        createdAt: new Date('2023-04-01T08:00:00Z'),
        lotNumber: 'Gatehouse 2',
        streetName: 'Service Gate'
    },
    { 
        id: 'usr_sec_3', 
        name: 'Kenneth Ward', 
        email: 'k.ward@apexguard.com', 
        role: 'Security', 
        status: 'Active', 
        createdAt: new Date('2023-05-12T08:00:00Z'),
        lotNumber: 'Mobile Patrol',
        streetName: 'Perimeter Ring Road'
    },
];

const roleOrder: UserRole[] = ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'];
const adminVisibleRoles: UserRole[] = ['Homeowner', 'Temporary Homeowner', 'Security', 'Staff'];
const ROOT_SYS_ADMIN_EMAILS = ['alexander.wright@communityhub.org', 'user-sysadmin@example.com'];

export default function DirectoryPage() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [users, setUsers] = useState<ManagedUser[]>(mockAllUsers);
  const [isFormOpen, setFormOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<ManagedUser | undefined>(undefined);
  const [searchQuery, setSearchQuery] = useState('');

  const canManageDirectory = user?.role === 'System Admin' || user?.role === 'Admin';

  const handleOpenForm = (targetUser?: ManagedUser) => {
    setSelectedUser(targetUser);
    setFormOpen(true);
  };
  
  const handleCloseForm = () => {
    setSelectedUser(undefined);
    setFormOpen(false);
  };

  const handleSaveUser = (data: Omit<ManagedUser, 'id' | 'createdAt'>, id?: string) => {
    if (id) {
        // Update
        setUsers(users.map(u => u.id === id ? { ...u, ...data, createdAt: u.createdAt } : u));
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

  const visibleRoles = useMemo(() => {
    return (user?.role === 'System Admin' 
      ? roleOrder.filter(r => r !== 'Staff')
      : user?.role === 'Admin'
        ? adminVisibleRoles.filter(r => r !== 'Staff')
        : []) as UserRole[];
  }, [user?.role]);

  const filteredUsers = useMemo(() => {
    if (!searchQuery.trim()) return users;
    const query = searchQuery.toLowerCase().trim();
    return users.filter(u => 
      u.name.toLowerCase().includes(query) ||
      u.email.toLowerCase().includes(query) ||
      (u.lotNumber && u.lotNumber.toLowerCase().includes(query)) ||
      (u.streetName && u.streetName.toLowerCase().includes(query))
    );
  }, [users, searchQuery]);

  const groupedUsers = useMemo(() => {
    return filteredUsers.reduce((acc, currentUser) => {
      if (visibleRoles.includes(currentUser.role)) {
          (acc[currentUser.role] = acc[currentUser.role] || []).push(currentUser);
      }
      return acc;
    }, {} as Record<UserRole, ManagedUser[]>);
  }, [filteredUsers, visibleRoles]);

  const getInitials = (name: string) => {
    if (!name) return 'U';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
      return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  };

  const getRoleAccent = (role: UserRole) => {
    switch (role) {
      case 'System Admin':
        return 'border-purple-500/30 text-purple-600 dark:text-purple-400 bg-purple-500/10';
      case 'Admin':
        return 'border-sky-500/30 text-sky-600 dark:text-sky-400 bg-sky-500/10';
      case 'Security':
        return 'border-amber-500/30 text-amber-600 dark:text-amber-400 bg-amber-500/10';
      case 'Homeowner':
        return 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400 bg-emerald-500/10';
      case 'Temporary Homeowner':
        return 'border-blue-500/30 text-blue-600 dark:text-blue-400 bg-blue-500/10';
      default:
        return 'border-muted text-muted-foreground bg-muted/40';
    }
  };

  if (!canManageDirectory) {
    return (
      <Card className="max-w-2xl mx-auto border-border/80">
        <CardHeader>
          <div className="flex items-center gap-2 text-destructive">
            <ShieldAlert className="h-5 w-5" />
            <CardTitle>Access Denied</CardTitle>
          </div>
          <CardDescription>You do not have permission to view this page.</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            Only authorized community administrators can view and manage the resident directory.
          </p>
        </CardContent>
      </Card>
    );
  }
  
  return (
    <div className="grid gap-8 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 className="font-headline text-3xl font-bold tracking-tight">Community Directory</h1>
            <p className="text-muted-foreground">View, search, and manage registered profiles across the community.</p>
        </div>
        <div className="flex items-center gap-3">
          <div className="relative w-64">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search by name, email, lot..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-8 h-9 text-xs"
            />
          </div>
          <CreateUserForm
              open={isFormOpen}
              onOpenChange={handleCloseForm}
              onSaveUser={handleSaveUser}
              userToEdit={selectedUser}
          >
              <Button size="sm" className="gap-1.5 h-9" onClick={() => handleOpenForm()}>
                  <PlusCircle className="h-4 w-4" />
                  <span>Add Member</span>
              </Button>
          </CreateUserForm>
        </div>
      </div>

      <Card className="border-border/80 shadow-sm">
        <CardHeader className="pb-4">
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-lg">Registered Member Profiles</CardTitle>
              <CardDescription>
                Profiles categorized by administrative and residential role.
              </CardDescription>
            </div>
            <Badge variant="outline" className="text-xs">
              {filteredUsers.length} {filteredUsers.length === 1 ? 'Profile' : 'Profiles'} Listed
            </Badge>
          </div>
        </CardHeader>
        <CardContent>
            <Accordion type="multiple" defaultValue={visibleRoles} className="w-full space-y-4">
                {visibleRoles.map(role => (
                    groupedUsers[role] && groupedUsers[role].length > 0 && (
                         <AccordionItem value={role} key={role} className="border border-border/50 rounded-lg overflow-hidden">
                            <AccordionTrigger className="bg-muted/40 hover:bg-muted/70 px-4 py-3 text-base font-semibold transition-colors">
                                <div className="flex items-center gap-2">
                                  <span>{role}s</span>
                                  <Badge variant="secondary" className="text-xs px-2 py-0 h-5">
                                    {groupedUsers[role].length}
                                  </Badge>
                                </div>
                            </AccordionTrigger>
                            <AccordionContent className="p-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {groupedUsers[role].map(person => {
                                    const isRootSysAdmin = ROOT_SYS_ADMIN_EMAILS.includes(person.email);
                                    const isSelf = person.email === user?.email;
                                    const canManageUser = !isRootSysAdmin && !isSelf && (user?.role === 'System Admin' || (user?.role === 'Admin' && person.role !== 'Admin' && person.role !== 'System Admin'));

                                    return (
                                    <Card key={person.id} className="border-border/70 shadow-none hover:border-primary/40 transition-colors">
                                        <CardHeader className="flex-row gap-3.5 items-center !pb-2">
                                             <Avatar className={`h-11 w-11 border-2 ${getRoleAccent(person.role)}`}>
                                                <AvatarFallback className="font-bold text-xs">
                                                  {getInitials(person.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                <p className="font-semibold text-sm truncate">{person.name}</p>
                                                <div className="flex items-center gap-1 text-xs text-muted-foreground truncate">
                                                  <Mail className="w-3 h-3 shrink-0" />
                                                  <span className="truncate">{person.email}</span>
                                                </div>
                                                <Badge className="mt-1.5 text-[10px] py-0 h-4" variant={person.status === 'Active' ? 'secondary' : 'outline'}>
                                                    {person.status}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pt-2 text-xs">
                                            {(person.lotNumber || person.streetName) && (
                                              <div className="flex items-center gap-1.5 text-muted-foreground mb-3 bg-muted/30 p-1.5 rounded text-[11px]">
                                                <MapPin className="w-3.5 h-3.5 shrink-0 text-primary/70" />
                                                <span className="truncate">
                                                  {[person.lotNumber, person.streetName].filter(Boolean).join(', ')}
                                                </span>
                                              </div>
                                            )}

                                            {canManageUser ? (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="outline" size="sm" className="w-full text-xs h-8">
                                                            <MoreHorizontal className="h-3.5 w-3.5 mr-1.5" />
                                                            Manage Account
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="w-44">
                                                        <DropdownMenuLabel className="text-xs">Profile Actions</DropdownMenuLabel>
                                                        <DropdownMenuItem onClick={() => handleOpenForm(person)} className="text-xs">
                                                            <Edit className="mr-2 h-3.5 w-3.5" /> Edit Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem onClick={() => toggleStatus(person.id)} className="text-xs">
                                                            {person.status === 'Active' ? 'Set as Inactive' : 'Set as Active'}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <AlertDialog>
                                                            <AlertDialogTrigger asChild>
                                                                <DropdownMenuItem onSelect={(e) => e.preventDefault()} className="text-destructive text-xs">
                                                                     <Trash2 className="mr-2 h-3.5 w-3.5" /> Delete Member
                                                                </DropdownMenuItem>
                                                            </AlertDialogTrigger>
                                                            <AlertDialogContent>
                                                                <AlertDialogHeader>
                                                                    <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                                                    <AlertDialogDescription>
                                                                        This will permanently delete &ldquo;{person.name}&rdquo; from the community directory.
                                                                    </AlertDialogDescription>
                                                                </AlertDialogHeader>
                                                                <AlertDialogFooter>
                                                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                                    <AlertDialogAction onClick={() => handleDeleteUser(person.id)}>
                                                                      Confirm Delete
                                                                    </AlertDialogAction>
                                                                </AlertDialogFooter>
                                                            </AlertDialogContent>
                                                        </AlertDialog>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            ) : (
                                                <Button 
                                                    variant="outline"
                                                    size="sm"
                                                    className="w-full text-xs h-8 text-muted-foreground opacity-70"
                                                    disabled
                                                    title={
                                                        isRootSysAdmin ? "The root System Admin cannot be modified." :
                                                        isSelf ? "You cannot manage your own profile here." :
                                                        "You do not have permission to manage this member."
                                                    }
                                                >
                                                    {isSelf ? "Your Account" : isRootSysAdmin ? "System Protected" : "Protected"}
                                                </Button>
                                            )}
                                        </CardContent>
                                    </Card>
                                );})}
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
