
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
import type { BlocklistEntry } from "@/types";
import { ClientFormattedDate } from '@/components/client-formatted-date';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { useAuth } from '@/context/auth-context';
import { BlocklistForm } from '@/components/dashboard/blocklist-form';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { format } from 'date-fns';
import { getBlocklist } from '@/lib/data';
import { RequestRemovalForm } from '@/components/dashboard/request-removal-form';


export default function BlockListPage() {
    const { user } = useAuth();
    const [entries, setEntries] = useState<BlocklistEntry[]>(getBlocklist());
    const [isFormOpen, setFormOpen] = useState(false);
    const [isRemovalFormOpen, setRemovalFormOpen] = useState(false);
    const [selectedEntry, setSelectedEntry] = useState<BlocklistEntry | undefined>(undefined);

    const handleOpenForm = (entry?: BlocklistEntry) => {
        setSelectedEntry(entry);
        setFormOpen(true);
    }
    
    const handleCloseForm = () => {
        setSelectedEntry(undefined);
        setFormOpen(false);
    }

    const handleOpenRemovalForm = (entry: BlocklistEntry) => {
        setSelectedEntry(entry);
        setRemovalFormOpen(true);
    };

    const handleCloseRemovalForm = () => {
        setSelectedEntry(undefined);
        setRemovalFormOpen(false);
    }

    const handleSave = (entry: Omit<BlocklistEntry, 'id' | 'dateAdded' | 'addedBy'>, id?: string) => {
        if (id) {
            // Update existing entry
            setEntries(entries.map(e => e.id === id ? { ...e, ...entry } : e));
        } else {
            // Add new entry
            const newEntry: BlocklistEntry = {
                id: `bl_${Date.now()}`,
                ...entry,
                dateAdded: new Date(),
                addedBy: user?.role || 'Admin',
            }
            setEntries([newEntry, ...entries]);
        }
    };

    const handleDelete = (id: string) => {
        setEntries(entries.filter(e => e.id !== id));
    }

    const canAdd = user?.role === 'Admin' || user?.role === 'System Admin' || user?.role === 'Security';
    const canEdit = user?.role === 'Admin' || user?.role === 'System Admin';
    const canRequestRemoval = user?.role === 'Homeowner';


  return (
    <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">Block List</h1>
            <p className="text-muted-foreground">Manage individuals who are denied entry to the community.</p>
        </div>
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Blocked Individuals</CardTitle>
                    <CardDescription>
                        Individuals on this list will be flagged upon entry attempt.
                    </CardDescription>
                </div>
                 {canAdd && (
                    <BlocklistForm
                        open={isFormOpen}
                        onOpenChange={handleCloseForm}
                        onSave={handleSave}
                        entry={selectedEntry}
                    >
                        <Button size="sm" className="gap-1" onClick={() => handleOpenForm()}>
                            <PlusCircle className="h-3.5 w-3.5" />
                            <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                                Add Individual
                            </span>
                        </Button>
                    </BlocklistForm>
                 )}
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Individual</TableHead>
                            <TableHead>Reason</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Date Added</TableHead>
                             <TableHead>Added By</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {entries.map((entry) => (
                            <TableRow key={entry.id}>
                                <TableCell className="font-medium">
                                    <div className="flex items-center gap-3">
                                        <Avatar>
                                            {entry.photoUrl && <AvatarImage src={entry.photoUrl} alt={entry.name} data-ai-hint="person face" />}
                                            <AvatarFallback>{entry.name.charAt(0)}</AvatarFallback>
                                        </Avatar>
                                        <span>{entry.name}</span>
                                    </div>
                                </TableCell>
                                <TableCell className="max-w-xs truncate">{entry.reason}</TableCell>
                                <TableCell>
                                     <Badge variant={entry.expiryDate ? "secondary" : "destructive"}>
                                        {entry.expiryDate ? `Expires ${format(entry.expiryDate, 'MMM d, yyyy')}` : 'Permanent'}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <ClientFormattedDate date={entry.dateAdded} formatString="MMM d, yyyy" />
                                </TableCell>
                                 <TableCell>
                                    {entry.addedBy}
                                 </TableCell>
                                <TableCell>
                                    {(canEdit || canRequestRemoval) && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                            <Button aria-haspopup="true" size="icon" variant="ghost">
                                                <MoreHorizontal className="h-4 w-4" />
                                                <span className="sr-only">Toggle menu</span>
                                            </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                {canEdit && (
                                                    <>
                                                        <DropdownMenuItem onClick={() => handleOpenForm(entry)}>Edit</DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem className="text-red-600" onClick={() => handleDelete(entry.id)}>Remove from list</DropdownMenuItem>
                                                    </>
                                                )}
                                                {canRequestRemoval && (
                                                    <DropdownMenuItem onClick={() => handleOpenRemovalForm(entry)}>Request Removal</DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        {selectedEntry && (
             <RequestRemovalForm 
                open={isRemovalFormOpen} 
                onOpenChange={handleCloseRemovalForm} 
                entry={selectedEntry} 
            />
        )}
    </div>
  );
}
