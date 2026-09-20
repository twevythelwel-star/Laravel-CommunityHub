

import { useState } from 'react';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { MoreHorizontal, PlusCircle } from "lucide-react";
import type { Guideline } from "@/types";
import { useAuth } from '@/context/auth-context';
import { GuidelineForm } from '@/components/dashboard/guideline-form';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
  } from "@/components/ui/dropdown-menu";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';


const mockGuidelines: Guideline[] = [
    { id: 'g_1', category: 'General Conduct', title: 'Noise Levels', description: 'Quiet hours are from 10:00 PM to 8:00 AM daily. Please be respectful of your neighbors.' },
    { id: 'g_2', category: 'General Conduct', title: 'Trash Disposal', description: 'All household trash must be placed in sealed bags inside the designated bins. Bins should be placed curbside on Tuesday evenings for Wednesday morning pickup.' },
    { id: 'g_3', category: 'Property Maintenance', title: 'Lawn Care', description: 'Lawns must be mowed and maintained weekly. Weeds and overgrown vegetation must be removed promptly.' },
    { id: 'g_4', category: 'Property Maintenance', title: 'Exterior Modifications', description: 'Any changes to the exterior of a home, including painting, requires prior approval from the Architectural Review Committee.' },
    { id: 'g_5', category: 'Amenities Usage', title: 'Pool Hours', description: 'The community pool is open from 9:00 AM to 9:00 PM, from May 1st to September 30th. No lifeguard on duty.' },
    { id: 'g_6', category: 'Amenities Usage', title: 'Clubhouse Booking', description: 'The clubhouse can be reserved for private events by contacting the HOA office at least two weeks in advance. A security deposit is required.' },
    { id: 'g_7', category: 'Security Policies', title: 'Visitor Registration', description: 'All visitors must be registered in the app at least 24 hours prior to their arrival. Unregistered visitors may be denied entry.' },
    { id: 'g_8', category: 'Security Policies', title: 'Gate Access', description: 'Do not tailgate or allow other vehicles to follow you through the gate. Each vehicle must use its own access credential.' },
    { id: 'g_9', category: 'Security Policies', title: 'Emergency Procedures', description: 'In case of a security emergency, contact the front gate at (555) 123-4567 or dial 911 for immediate assistance.' },
];

export default function GuidelinesPage() {
    const { user } = useAuth();
    const [guidelines, setGuidelines] = useState<Guideline[]>(mockGuidelines);
    const [isFormOpen, setFormOpen] = useState(false);
    const [selectedGuideline, setSelectedGuideline] = useState<Guideline | undefined>(undefined);

    const canManage = user?.role === 'System Admin' || user?.role === 'Admin';

    const handleOpenForm = (guideline?: Guideline) => {
        setSelectedGuideline(guideline);
        setFormOpen(true);
    };

    const handleCloseForm = () => {
        setSelectedGuideline(undefined);
        setFormOpen(false);
    };

    const handleSave = (data: Omit<Guideline, 'id'>, id?: string) => {
        if (id) {
            setGuidelines(guidelines.map(g => g.id === id ? { ...g, ...data } : g));
        } else {
            const newGuideline: Guideline = {
                id: `g_${Date.now()}`,
                ...data,
            };
            setGuidelines([...guidelines, newGuideline]);
        }
    };

    const handleDelete = (id: string) => {
        setGuidelines(guidelines.filter(g => g.id !== id));
    };

    const groupedGuidelines = guidelines.reduce((acc, guideline) => {
        (acc[guideline.category] = acc[guideline.category] || []).push(guideline);
        return acc;
    }, {} as Record<string, Guideline[]>);

    const categories = Object.keys(groupedGuidelines).sort();

  return (
    <div className="grid gap-8">
        <div className="flex items-center justify-between">
            <div>
                <h1 className="font-headline text-3xl font-bold">Community Guidelines</h1>
                <p className="text-muted-foreground">A comprehensive guide to the rules and regulations of our community.</p>
            </div>
             {canManage && (
                <GuidelineForm
                    open={isFormOpen}
                    onOpenChange={handleCloseForm}
                    onSave={handleSave}
                    guideline={selectedGuideline}
                >
                    <Button size="sm" className="gap-1">
                        <PlusCircle className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                            Add Guideline
                        </span>
                    </Button>
                </GuidelineForm>
             )}
        </div>
        <Card>
            <CardHeader>
                <CardTitle>Rules & Regulations</CardTitle>
                <CardDescription>Browse the community guidelines by category.</CardDescription>
            </CardHeader>
            <CardContent>
                <Accordion type="multiple" defaultValue={categories} className="w-full space-y-4">
                    {categories.map(category => (
                        <AccordionItem value={category} key={category} className="border-none">
                            <AccordionTrigger className="bg-muted hover:bg-muted/80 px-4 py-3 rounded-md text-lg font-semibold">
                                {category} ({groupedGuidelines[category].length})
                            </AccordionTrigger>
                            <AccordionContent className="pt-4 space-y-4">
                                {groupedGuidelines[category].map(guideline => (
                                    <div key={guideline.id} className="border rounded-md p-4 flex justify-between items-start">
                                        <div>
                                            <h4 className="font-semibold">{guideline.title}</h4>
                                            <p className="text-muted-foreground mt-1">{guideline.description}</p>
                                        </div>
                                        {canManage && (
                                             <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                <Button aria-haspopup="true" size="icon" variant="ghost">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                    <span className="sr-only">Toggle menu</span>
                                                </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                    <DropdownMenuItem onClick={() => handleOpenForm(guideline)}>Edit</DropdownMenuItem>
                                                    <AlertDialog>
                                                        <AlertDialogTrigger asChild>
                                                            <DropdownMenuItem onSelect={(e) => e.preventDefault()} className="text-red-600">Delete</DropdownMenuItem>
                                                        </AlertDialogTrigger>
                                                        <AlertDialogContent>
                                                            <AlertDialogHeader>
                                                            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This action cannot be undone. This will permanently delete the guideline titled &ldquo;{guideline.title}&rdquo;.
                                                            </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction onClick={() => handleDelete(guideline.id)}>
                                                                Yes, delete
                                                            </AlertDialogAction>
                                                            </AlertDialogFooter>
                                                        </AlertDialogContent>
                                                    </AlertDialog>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        )}
                                    </div>
                                ))}
                            </AccordionContent>
                        </AccordionItem>
                    ))}
                </Accordion>
            </CardContent>
        </Card>
    </div>
  );
}
