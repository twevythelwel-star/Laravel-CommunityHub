
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
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAuth } from '@/context/auth-context';
import { useToast } from '@/hooks/use-toast';
import { Building2 } from 'lucide-react';
import Image from 'next/image';

export default function ProfilePage() {
    const { user, updateUser } = useAuth();
    const { toast } = useToast();

    // Initialize state from user context
    const [displayName, setDisplayName] = useState(user?.displayName || '');
    const [email, setEmail] = useState(user?.email || '');
    const [phone, setPhone] = useState(user?.phone || '');
    
    if (!user) {
        return <div>Loading...</div>;
    }

    const handleSaveChanges = () => {
        updateUser({ displayName, email, phone });
        toast({
            title: "Profile Updated",
            description: "Your changes have been saved successfully.",
        });
    };

    const canEditProfile = user.role === 'Homeowner' || user.role === 'Temporary Homeowner' || user.role === 'Admin' || user.role === 'System Admin';

    // Security Role View
    if (user.role === 'Security') {
        return (
             <div className="grid gap-8">
                <div>
                    <h1 className="font-headline text-3xl font-bold">Security Profile</h1>
                    <p className="text-muted-foreground">This information is managed by the System Administrator.</p>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Security Company Details</CardTitle>
                        <CardDescription>
                            Contact information for the community's security provider.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="flex items-center gap-4">
                             <Avatar className="h-20 w-20 rounded-sm">
                                <AvatarImage src="https://picsum.photos/seed/security-logo/200" alt="Security Company Logo" data-ai-hint="security logo" />
                                <AvatarFallback><Building2 /></AvatarFallback>
                            </Avatar>
                            <div>
                                <p className="text-lg font-semibold">{user.name}</p>
                                <p className="text-sm text-muted-foreground">Official Security Provider</p>
                            </div>
                        </div>
                         <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Email</Label>
                                <Input value={user.email} disabled />
                            </div>
                             <div className="grid gap-2">
                                <Label>Contact Number</Label>
                                <Input value={user.phone} disabled />
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground pt-4">
                            To update this information, please contact a System Administrator.
                        </p>
                    </CardContent>
                </Card>
            </div>
        )
    }

    // View for all other roles
    return (
        <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">Your Profile</h1>
            <p className="text-muted-foreground">Manage your personal information.</p>
        </div>
        <Card>
            <CardHeader>
            <CardTitle>Profile Details</CardTitle>
            <CardDescription>
                Your display name, email and phone number are visible to administrators.
            </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
            <div className="flex items-center gap-4">
                <Avatar className="h-20 w-20">
                <AvatarImage src={`https://picsum.photos/200?q=${user.uid}`} data-ai-hint="person avatar" />
                <AvatarFallback>{user.name?.charAt(0) || 'U'}</AvatarFallback>
                </Avatar>
                <Button variant="outline">Change Picture</Button>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="name">Full Name</Label>
                    <Input id="name" defaultValue={user.name} disabled />
                     <p className="text-xs text-muted-foreground">Your full name cannot be changed. Please contact an admin if it is incorrect.</p>
                </div>
                 <div className="grid gap-2">
                    <Label htmlFor="displayName">Display Name</Label>
                    <Input 
                        id="displayName" 
                        value={displayName}
                        onChange={(e) => setDisplayName(e.target.value)}
                        disabled={!canEditProfile}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input 
                        id="email" 
                        type="email" 
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        disabled={!canEditProfile}
                    />
                </div>
                 <div className="grid gap-2">
                    <Label htmlFor="phone">Phone Number</Label>
                    <Input 
                        id="phone" 
                        type="tel" 
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        disabled={!canEditProfile}
                    />
                </div>
            </div>
            {canEditProfile && <Button onClick={handleSaveChanges}>Save Changes</Button>}
            </CardContent>
        </Card>
        </div>
    );
}
