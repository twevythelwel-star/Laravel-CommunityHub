
import { useState, useEffect } from 'react';
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
import { Label } from "@/components/ui/label";
import { useAuth } from '@/context/auth-context';
import { useToast } from '@/hooks/use-toast';
import { 
  Building2, 
  ShieldCheck, 
  User, 
  Briefcase, 
  Mail, 
  Phone, 
  MapPin, 
  BadgeCheck, 
  Calendar, 
  CheckCircle2, 
  KeyRound,
  Trash2,
  Save,
  Clock
} from 'lucide-react';
import { MyGatePassDialog } from '@/components/dashboard/my-gate-pass-dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

export default function ProfilePage() {
    const { user, updateUser } = useAuth();
    const { toast } = useToast();
    const [isPassOpen, setPassOpen] = useState(false);

    // Initialize state from user context
    const [displayName, setDisplayName] = useState(user?.displayName || '');
    const [phone, setPhone] = useState(user?.phone || '');

    useEffect(() => {
        if (user) {
            setDisplayName(user.displayName || '');
            setPhone(user.phone || '');
        }
    }, [user]);
    
    if (!user) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                <Clock className="mr-2 h-5 w-5 animate-spin" />
                Loading profile...
            </div>
        );
    }

    const handleSaveChanges = () => {
        updateUser({ displayName, phone });
        toast({
            title: "Profile Saved",
            description: "Your personal details have been updated successfully.",
        });
    };

    const canEditProfile = user.role === 'Homeowner' || user.role === 'Temporary Homeowner' || user.role === 'Admin' || user.role === 'System Admin';

    const getRoleBadgeVariant = (role: string) => {
        switch (role) {
            case 'System Admin':
                return 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/20';
            case 'Admin':
                return 'bg-sky-500/15 text-sky-600 dark:text-sky-400 border-sky-500/20';
            case 'Security':
                return 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/20';
            case 'Homeowner':
                return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
            case 'Temporary Homeowner':
                return 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/20';
            case 'Staff':
                return 'bg-teal-500/15 text-teal-600 dark:text-teal-400 border-teal-500/20';
            default:
                return 'bg-muted text-muted-foreground';
        }
    };

    const getInitials = (name: string) => {
        if (!name) return 'U';
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) {
            return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
        }
        return name.slice(0, 2).toUpperCase();
    };

    // Security Role View
    if (user.role === 'Security') {
        return (
            <>
            <MyGatePassDialog open={isPassOpen} onOpenChange={setPassOpen} />
            <div className="grid gap-8 max-w-5xl mx-auto">
                <div>
                    <div className="flex items-center gap-2 mb-1">
                        <Badge className={`px-2.5 py-0.5 text-xs font-semibold border ${getRoleBadgeVariant(user.role)}`}>
                            <ShieldCheck className="w-3.5 h-3.5 mr-1" />
                            Security Operations
                        </Badge>
                    </div>
                    <h1 className="font-headline text-3xl font-bold tracking-tight">Security Officer Profile</h1>
                    <p className="text-muted-foreground">Authorized Gate Patrol & Access Management Identity.</p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {/* Security Identity Card */}
                    <Card className="md:col-span-1 border-border/80 shadow-sm">
                        <CardHeader className="text-center pb-3">
                            <div className="mx-auto mb-3">
                                <Avatar className="h-24 w-24 border-2 border-amber-500/30 shadow-md">
                                    <AvatarFallback className="bg-gradient-to-br from-amber-500/20 to-amber-700/30 text-amber-600 dark:text-amber-400 text-2xl font-bold">
                                        <Building2 className="h-10 w-10 text-amber-500" />
                                    </AvatarFallback>
                                </Avatar>
                            </div>
                            <CardTitle className="text-xl font-bold">{user.name}</CardTitle>
                            <CardDescription className="text-xs font-medium text-amber-600 dark:text-amber-400">
                                Official Community Security Provider
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-2">
                            <div className="p-3 bg-muted/40 rounded-lg space-y-2 border border-border/50 text-xs">
                                <div className="flex justify-between items-center text-muted-foreground">
                                    <span>Deployment Unit</span>
                                    <span className="font-semibold text-foreground">{user.lot || 'Gatehouse 1'}</span>
                                </div>
                                <div className="flex justify-between items-center text-muted-foreground">
                                    <span>Sector Post</span>
                                    <span className="font-semibold text-foreground">{user.street || 'Main Perimeter Gate'}</span>
                                </div>
                                <div className="flex justify-between items-center text-muted-foreground">
                                    <span>Clearance Level</span>
                                    <span className="font-semibold text-amber-600 dark:text-amber-400">Tier 1 Gate Access</span>
                                </div>
                            </div>
                            <Button 
                                onClick={() => setPassOpen(true)} 
                                className="w-full bg-amber-600 hover:bg-amber-700 text-white font-medium"
                            >
                                <KeyRound className="mr-2 h-4 w-4" /> View My Access Pass
                            </Button>
                        </CardContent>
                    </Card>

                    {/* Operational Details Card */}
                    <Card className="md:col-span-2 border-border/80 shadow-sm">
                        <CardHeader>
                            <CardTitle>Provider & Contact Credentials</CardTitle>
                            <CardDescription>
                                Verified operational dispatch records. Managed centrally by System Administrators.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                        <Mail className="w-3.5 h-3.5" /> Dispatch Email
                                    </Label>
                                    <Input value={user.email} disabled className="bg-muted/30 font-medium" />
                                </div>
                                <div className="grid gap-2">
                                    <Label className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                        <Phone className="w-3.5 h-3.5" /> Command Hotline
                                    </Label>
                                    <Input value={user.phone ?? ''} disabled className="bg-muted/30 font-medium" />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                        <MapPin className="w-3.5 h-3.5" /> Guardhouse Station
                                    </Label>
                                    <Input value={`${user.lot || 'Gatehouse 1'}, ${user.street || 'Main Entrance Gate'}`} disabled className="bg-muted/30" />
                                </div>
                            </div>

                            <div className="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-2">
                                <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <BadgeCheck className="w-4 h-4 text-emerald-500" /> Active Roster Verification
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    All gate entries, scanner logs, and visitor sign-ins processed under this credential are cryptographically attributed to your dispatch session. To update dispatch records or personnel roster, contact the System Administrator.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
            </>
        );
    }

    // Staff Role View
    if (user.role === 'Staff') {
        return (
            <div className="grid gap-8 max-w-5xl mx-auto">
                <div>
                    <div className="flex items-center gap-2 mb-1">
                        <Badge className={`px-2.5 py-0.5 text-xs font-semibold border ${getRoleBadgeVariant(user.role)}`}>
                            <Briefcase className="w-3.5 h-3.5 mr-1" />
                            Community Staff
                        </Badge>
                    </div>
                    <h1 className="font-headline text-3xl font-bold tracking-tight">Staff Member Profile</h1>
                    <p className="text-muted-foreground">Your verified service personnel credentials and registered properties.</p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {/* Staff Identity Card */}
                    <Card className="md:col-span-1 border-border/80 shadow-sm">
                        <CardHeader className="text-center pb-3">
                            <div className="mx-auto mb-3">
                                <Avatar className="h-24 w-24 border-2 border-teal-500/30 shadow-md">
                                    <AvatarFallback className="bg-gradient-to-br from-teal-500/20 to-teal-700/30 text-teal-600 dark:text-teal-400 text-2xl font-bold">
                                        {getInitials(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                            </div>
                            <CardTitle className="text-xl font-bold">{user.name}</CardTitle>
                            <CardDescription className="text-xs font-medium text-teal-600 dark:text-teal-400">
                                {user.title || 'Facilities Coordinator'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-2">
                            <div className="p-3 bg-muted/40 rounded-lg space-y-2 border border-border/50 text-xs">
                                <div className="flex justify-between items-center text-muted-foreground">
                                    <span>Assigned Residence</span>
                                    <span className="font-semibold text-foreground">{user.lot || 'Lot 42'}</span>
                                </div>
                                <div className="flex justify-between items-center text-muted-foreground">
                                    <span>Street Address</span>
                                    <span className="font-semibold text-foreground">{user.street || 'Royal Palm Drive'}</span>
                                </div>
                                <div className="flex justify-between items-center text-muted-foreground">
                                    <span>Badge Status</span>
                                    <span className="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                        <CheckCircle2 className="w-3 h-3" /> Active
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Staff Contact Card */}
                    <Card className="md:col-span-2 border-border/80 shadow-sm">
                        <CardHeader>
                            <CardTitle>Personnel Records</CardTitle>
                            <CardDescription>
                                Information registered by your sponsoring Homeowner or Community Manager.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                        <Mail className="w-3.5 h-3.5" /> Contact Email
                                    </Label>
                                    <Input value={user.email} disabled className="bg-muted/30 font-medium" />
                                </div>
                                <div className="grid gap-2">
                                    <Label className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                        <Phone className="w-3.5 h-3.5" /> Mobile Phone
                                    </Label>
                                    <Input value={user.phone ?? ''} disabled className="bg-muted/30 font-medium" />
                                </div>
                            </div>

                            <div className="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-2">
                                <p className="text-xs text-muted-foreground">
                                    For security compliance, staff profile adjustments and gate-clearance updates must be authorized directly by your sponsoring resident employer or a community administrator.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        );
    }

    // Standard Views (Homeowner, Temporary Homeowner, Admin, System Admin)
    return (
        <div className="grid gap-8 max-w-5xl mx-auto">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div className="flex items-center gap-2 mb-1">
                        <Badge className={`px-2.5 py-0.5 text-xs font-semibold border ${getRoleBadgeVariant(user.role)}`}>
                            <User className="w-3.5 h-3.5 mr-1" />
                            {user.role}
                        </Badge>
                        {user.title && (
                            <span className="text-xs text-muted-foreground font-medium">• {user.title}</span>
                        )}
                    </div>
                    <h1 className="font-headline text-3xl font-bold tracking-tight">Your Profile</h1>
                    <p className="text-muted-foreground">Review and manage your personal details and community identity.</p>
                </div>
            </div>

            <div className="grid gap-6 md:grid-cols-3">
                {/* Profile Avatar & Identity Snapshot */}
                <Card className="md:col-span-1 border-border/80 shadow-sm">
                    <CardHeader className="text-center pb-4">
                        <div className="mx-auto mb-3">
                            <Avatar className="h-24 w-24 border-2 border-primary/20 shadow-md">
                                <AvatarFallback className="bg-gradient-to-br from-primary/20 to-primary/10 text-primary text-2xl font-bold">
                                    {getInitials(user.name)}
                                </AvatarFallback>
                            </Avatar>
                        </div>
                        <CardTitle className="text-xl font-bold">{user.name}</CardTitle>
                        <CardDescription className="text-xs font-medium">
                            {user.displayName ? `@${user.displayName}` : user.email}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-1">
                        <div className="p-3 bg-muted/40 rounded-lg space-y-2.5 border border-border/50 text-xs">
                            <div className="flex justify-between items-center text-muted-foreground">
                                <span>Account Role</span>
                                <span className="font-semibold text-foreground">{user.role}</span>
                            </div>
                            {user.lot && (
                                <div className="flex justify-between items-center text-muted-foreground">
                                    <span>Residence / Lot</span>
                                    <span className="font-semibold text-foreground">{user.lot}</span>
                                </div>
                            )}
                            {user.street && (
                                <div className="flex justify-between items-center text-muted-foreground">
                                    <span>Street Address</span>
                                    <span className="font-semibold text-foreground">{user.street}</span>
                                </div>
                            )}
                            <div className="flex justify-between items-center text-muted-foreground">
                                <span>Member Status</span>
                                <span className="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 className="w-3 h-3" /> Verified Active
                                </span>
                            </div>
                        </div>

                        <div className="text-xs text-muted-foreground text-center px-1 pt-1">
                            Profile identifier: <span className="font-mono text-[10px] select-all">{user.uid}</span>
                        </div>
                    </CardContent>
                </Card>

                {/* Editable Profile Information */}
                <Card className="md:col-span-2 border-border/80 shadow-sm">
                    <CardHeader>
                        <CardTitle>Profile Details</CardTitle>
                        <CardDescription>
                            Your display name, email, and phone number are visible to authorized community administrators.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="legal-name" className="text-xs font-semibold text-muted-foreground">
                                    Legal Full Name
                                </Label>
                                <Input 
                                    id="legal-name" 
                                    defaultValue={user.name} 
                                    disabled 
                                    className="bg-muted/30 font-medium"
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    Verified legal name cannot be edited directly.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="display-name" className="text-xs font-semibold text-muted-foreground">
                                    Display Name
                                </Label>
                                <Input 
                                    id="display-name" 
                                    value={displayName}
                                    onChange={(e) => setDisplayName(e.target.value)}
                                    disabled={!canEditProfile}
                                    placeholder="Enter your community display handle"
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    Name shown across community boards and notices.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="profile-email" className="text-xs font-semibold text-muted-foreground">
                                    Email Address
                                </Label>
                                {/*
                                  Read-only. This was an editable input whose value
                                  Save silently discarded: ProfileController accepts
                                  display_name and phone only. The email is the login
                                  identity, so changing it needs a verification flow
                                  that does not exist yet — better to say so than to
                                  accept an edit and drop it.
                                */}
                                <Input
                                    id="profile-email"
                                    type="email"
                                    value={user.email}
                                    disabled
                                    className="bg-muted/30"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Your email is your sign-in identity. Contact an administrator to change it.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="profile-phone" className="text-xs font-semibold text-muted-foreground">
                                    Phone Number
                                </Label>
                                <Input 
                                    id="profile-phone" 
                                    type="tel" 
                                    value={phone}
                                    onChange={(e) => setPhone(e.target.value)}
                                    disabled={!canEditProfile}
                                    placeholder="(876) 555-0100"
                                />
                            </div>
                        </div>

                        {canEditProfile && (
                            <div className="flex justify-end pt-2 border-t">
                                <Button onClick={handleSaveChanges} className="gap-2">
                                    <Save className="w-4 h-4" /> Save Profile Changes
                                </Button>
                            </div>
                        )}

                        {/* Danger Zone */}
                        <div className="pt-6 mt-4 border-t border-destructive/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold text-destructive">Deactivate Profile</p>
                                <p className="text-xs text-muted-foreground">
                                    Schedule your community account and credentials for deactivation.
                                </p>
                            </div>
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="outline" className="border-destructive/40 text-destructive hover:bg-destructive/10 text-xs">
                                        <Trash2 className="w-3.5 h-3.5 mr-1.5" /> Deactivate Account
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Are you sure you want to deactivate?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This action will revoke active digital passes, access credentials, and notification preferences associated with your profile.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction 
                                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90" 
                                            onClick={() => {
                                                toast({
                                                    variant: "destructive",
                                                    title: "Deactivation Request Submitted",
                                                    description: "Your account deactivation request has been scheduled for administrator review.",
                                                });
                                            }}
                                        >
                                            Confirm Deactivation
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
