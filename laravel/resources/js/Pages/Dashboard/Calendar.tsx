

import { useState, useMemo } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Calendar } from "@/components/ui/calendar";
import { Button } from "@/components/ui/button";
import { EventForm } from "@/components/dashboard/event-form";
import type { CommunityEvent } from "@/types";
import { useAuth } from "@/context/auth-context";
import { isSameDay, isPast, format, isValid } from "date-fns";
import { Image } from "@/components/ui/image";
import { MoreHorizontal, PlusCircle, Trash2, Edit } from "lucide-react";
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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";

const mockEvents: CommunityEvent[] = [
    { id: 'evt_1', title: 'Community Pool Party', description: 'Join us for a fun day at the pool! Food and drinks will be provided.', startDate: new Date(new Date().getFullYear(), 6, 20, 12, 0), endDate: new Date(new Date().getFullYear(), 6, 20, 16, 0), imageUrl: 'https://picsum.photos/seed/pool-party/800/400' },
    { id: 'evt_2', title: 'Annual HOA Meeting', description: 'Discussing the budget and plans for the upcoming year.', startDate: new Date(new Date().getFullYear(), 7, 5, 19, 0), endDate: new Date(new Date().getFullYear(), 7, 5, 21, 0) },
    { id: 'evt_3', title: 'Movie Night Under the Stars', description: 'We\'ll be showing a family-friendly movie on a big screen in the park.', startDate: new Date(new Date().getFullYear(), 6, 25, 20, 0), imageUrl: 'https://picsum.photos/seed/movie-night/800/400' },
    { id: 'evt_4', title: 'Yoga in the Park', description: 'Morning yoga session for all skill levels.', startDate: new Date(new Date().getFullYear(), 6, 20, 9, 0) },
];


export default function CalendarPage() {
    const { user } = useAuth();
    const [events, setEvents] = useState<CommunityEvent[]>(mockEvents);
    const [selectedDate, setSelectedDate] = useState<Date | undefined>(new Date());
    const [isFormOpen, setFormOpen] = useState(false);
    const [selectedEvent, setSelectedEvent] = useState<CommunityEvent | undefined>(undefined);

    const canManage = user?.role === 'System Admin' || user?.role === 'Admin';

    const handleOpenForm = (event?: CommunityEvent) => {
        setSelectedEvent(event);
        setFormOpen(true);
    };

    const handleCloseForm = () => {
        setSelectedEvent(undefined);
        setFormOpen(false);
    };

    const handleSaveEvent = (data: Omit<CommunityEvent, 'id'>, id?: string) => {
        if (id) {
            setEvents(events.map(e => e.id === id ? { ...e, ...data } : e));
        } else {
            const newEvent: CommunityEvent = { id: `evt_${Date.now()}`, ...data };
            setEvents([...events, newEvent]);
        }
    };

    const handleDeleteEvent = (id: string) => {
        setEvents(events.filter(e => e.id !== id));
    };

    const todaysEvents = useMemo(() => {
        const futureEvents = events.filter(e => !isPast(e.endDate || e.startDate));
        if (!selectedDate) return [];
        return futureEvents
            .filter(event => isSameDay(event.startDate, selectedDate))
            .sort((a, b) => a.startDate.getTime() - b.startDate.getTime());
    }, [events, selectedDate]);
    
    const formatEventTime = (event: CommunityEvent) => {
        const { startDate, endDate } = event;
        // Check if startTime and endTime are the same as the start of the day
        const isAllDay = 
            startDate.getHours() === 0 && startDate.getMinutes() === 0 &&
            (!endDate || (endDate.getHours() === 0 && endDate.getMinutes() === 0));

        if (isAllDay && !endDate) return "All-day event";
        if (isAllDay && endDate && isSameDay(startDate, endDate)) return "All-day event";

        let timeString = '';
        if (isValid(startDate)) {
            timeString = format(startDate, 'h:mm a');
        }

        if (isValid(endDate) && endDate) {
            timeString += ` - ${format(endDate, 'h:mm a')}`;
        }
        
        return timeString;
    }

  return (
    <div className="grid gap-8">
      <div className="flex justify-between items-center">
        <div>
            <h1 className="font-headline text-3xl font-bold">Community Calendar</h1>
            <p className="text-muted-foreground">View community events and available visitor timeslots.</p>
        </div>
         {canManage && (
            <EventForm open={isFormOpen} onOpenChange={setFormOpen} onSave={handleSaveEvent} event={selectedEvent}>
                <Button size="sm" className="gap-1" onClick={() => handleOpenForm()}>
                    <PlusCircle className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                        Add Event
                    </span>
                </Button>
            </EventForm>
        )}
      </div>

      <div className="grid md:grid-cols-2 gap-8">
        <Card>
            <CardHeader>
                <CardTitle>Events and Bookings</CardTitle>
                <CardDescription>
                    Select a date to see upcoming events.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex justify-center">
                <Calendar
                    mode="single"
                    selected={selectedDate}
                    onSelect={setSelectedDate}
                    className="rounded-md border"
                />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>
                    Events for {selectedDate ? format(selectedDate, 'MMMM d, yyyy') : '...'}
                </CardTitle>
                <CardDescription>A list of scheduled events for the selected day.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 max-h-[400px] overflow-y-auto">
                {todaysEvents.length > 0 ? (
                    todaysEvents.map(event => (
                        <Card key={event.id} className="overflow-hidden">
                            {event.imageUrl && (
                                <div className="relative h-32 w-full">
                                    <Image src={event.imageUrl} alt={event.title} layout="fill" objectFit="cover" data-ai-hint="event image" />
                                </div>
                            )}
                            <div className="p-4">
                                <div className="flex justify-between items-start">
                                    <div>
                                        <h4 className="font-semibold">{event.title}</h4>
                                        <p className="text-sm text-muted-foreground">{formatEventTime(event)}</p>
                                    </div>
                                    {canManage && (
                                         <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" className="h-8 w-8">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                <DropdownMenuItem onClick={() => handleOpenForm(event)}>
                                                    <Edit className="mr-2 h-4 w-4" />
                                                    Edit
                                                </DropdownMenuItem>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <DropdownMenuItem onSelect={(e) => e.preventDefault()} className="text-destructive">
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            This will permanently delete the event &ldquo;{event.title}&rdquo;.
                                                        </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                        <AlertDialogAction onClick={() => handleDeleteEvent(event.id)}>Yes, delete</AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground mt-2">{event.description}</p>
                            </div>
                        </Card>
                    ))
                ) : (
                    <p className="text-center text-muted-foreground py-10">No events scheduled for this day.</p>
                )}
            </CardContent>
        </Card>
      </div>
    </div>
  );
}
