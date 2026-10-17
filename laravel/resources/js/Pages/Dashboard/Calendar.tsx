import { useState, useMemo } from "react";
import { Head, router } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
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
import { submit } from "@/lib/submit";
import type { CommunityEvent, CommunityEventType } from "@/types";
import { useAuth } from "@/context/auth-context";
import { isSameDay, format, isValid, parseISO } from "date-fns";
import { Image } from "@/components/ui/image";
import {
  CalendarDays,
  Clock,
  PlusCircle,
  ShieldCheck,
  UserCheck,
  ShieldAlert,
  ArrowRight,
  Sparkles,
  CheckCircle2,
  MapPin,
  Edit3,
  Wrench,
  Building,
  Flag,
  Megaphone,
  Users,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";

type EntrySlot = {
  id: string;
  name: string;
  timeRange: string;
  status: string;
  description: string;
};

type VisitorSummary = {
  id: number | string;
  name: string;
  type: string;
  status: string;
  expectedAt: string;
  dateRange: string | null;
};

type UserStay = {
  stayType: string;
  leaseStart: string;
  leaseEnd: string;
  expired?: boolean;
} | null;

type EventProp = {
  id: number | string;
  title: string;
  description: string;
  eventType?: CommunityEventType;
  eventTypeLabel?: string;
  location?: string | null;
  isAllDay?: boolean;
  startDate: string;
  endDate?: string | null;
  imageUrl?: string | null;
  changeSummary?: string | null;
};

type Props = {
  events: EventProp[];
  eventTypes?: Record<string, string>;
  entrySlots?: EntrySlot[];
  myVisitors?: VisitorSummary[];
  userStay?: UserStay;
  canManage?: boolean;
  canRegisterVisitors?: boolean;
};

function getBadgeStyle(type?: string) {
  switch (type) {
    case 'public_holiday':
      return 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/20';
    case 'office_closure':
      return 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20';
    case 'meeting':
      return 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/20';
    case 'maintenance_window':
      return 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20';
    case 'scheduled_notice':
      return 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/20';
    case 'event':
    default:
      return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20';
  }
}

function getTypeIcon(type?: string) {
  switch (type) {
    case 'public_holiday':
      return <Flag className="h-3 w-3" />;
    case 'office_closure':
      return <Building className="h-3 w-3" />;
    case 'meeting':
      return <Users className="h-3 w-3" />;
    case 'maintenance_window':
      return <Wrench className="h-3 w-3" />;
    case 'scheduled_notice':
      return <Megaphone className="h-3 w-3" />;
    case 'event':
    default:
      return <Sparkles className="h-3 w-3" />;
  }
}

export default function CalendarPage({
  events: initialEvents = [],
  entrySlots = [],
  myVisitors = [],
  userStay,
  canManage: serverCanManage,
  canRegisterVisitors = true,
}: Props) {
  const { user } = useAuth();
  const [selectedDate, setSelectedDate] = useState<Date | undefined>(new Date());
  const [activeTypeFilter, setActiveTypeFilter] = useState<string>('all');
  const [isFormOpen, setFormOpen] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState<CommunityEvent | undefined>(undefined);

  const canManage = serverCanManage ?? (user?.role === "System Admin" || user?.role === "Admin");

  // Parse events into Date objects
  const parsedEvents = useMemo(() => {
    return initialEvents.map((e) => ({
      ...e,
      id: String(e.id),
      eventType: e.eventType || 'event',
      eventTypeLabel: e.eventTypeLabel || 'Community Event',
      location: e.location || null,
      isAllDay: !!e.isAllDay,
      startDate: parseISO(e.startDate),
      endDate: e.endDate ? parseISO(e.endDate) : undefined,
      imageUrl: e.imageUrl || undefined,
      changeSummary: e.changeSummary || null,
    }));
  }, [initialEvents]);

  const filteredEvents = useMemo(() => {
    if (activeTypeFilter === 'all') return parsedEvents;
    return parsedEvents.filter((e) => e.eventType === activeTypeFilter);
  }, [parsedEvents, activeTypeFilter]);

  const dateEvents = useMemo(() => {
    if (!selectedDate) return [];
    return filteredEvents.filter((event) => isSameDay(event.startDate, selectedDate));
  }, [filteredEvents, selectedDate]);

  const dateVisitors = useMemo(() => {
    if (!selectedDate) return [];
    return myVisitors.filter((v) => isSameDay(parseISO(v.expectedAt), selectedDate));
  }, [myVisitors, selectedDate]);

  const isWithinStay = useMemo(() => {
    if (!userStay || !selectedDate) return true;
    const start = parseISO(userStay.leaseStart);
    start.setHours(0, 0, 0, 0);
    const end = parseISO(userStay.leaseEnd);
    end.setHours(23, 59, 59, 999);
    const sel = new Date(selectedDate);
    sel.setHours(12, 0, 0, 0);
    return sel >= start && sel <= end;
  }, [userStay, selectedDate]);

  const formatEventTime = (event: { startDate: Date; endDate?: Date; isAllDay?: boolean }) => {
    const { startDate, endDate, isAllDay } = event;
    if (isAllDay) return "All-day event";

    let timeString = "";
    if (isValid(startDate)) {
      timeString = format(startDate, "h:mm a");
    }
    if (isValid(endDate) && endDate) {
      timeString += ` – ${format(endDate, "h:mm a")}`;
    }
    return timeString || "All day";
  };

  const handleRegisterVisitorForDate = () => {
    const formatted = selectedDate ? format(selectedDate, "yyyy-MM-dd") : format(new Date(), "yyyy-MM-dd");
    router.get(`/dashboard/visitors?date=${formatted}`);
  };

  const handleEditEvent = (event: CommunityEvent) => {
    setSelectedEvent(event);
    setFormOpen(true);
  };

  return (
    <DashboardLayout>
      <Head title="Calendar & Community Schedule" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold font-headline tracking-tight flex items-center gap-2">
              <CalendarDays className="h-7 w-7 text-primary" />
              Community &amp; Entry Calendar
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Holidays, office closures, meetings, maintenance windows, community events, and entry slots.
            </p>
          </div>

          <div className="flex items-center gap-3">
            {canRegisterVisitors && (
              <Button
                onClick={handleRegisterVisitorForDate}
                disabled={!isWithinStay}
                className="gap-2 shadow-sm"
              >
                <PlusCircle className="h-4 w-4" />
                Register Visitor for {selectedDate ? format(selectedDate, "MMM d") : "Date"}
              </Button>
            )}

            {canManage && (
              <Button
                variant="outline"
                size="sm"
                className="gap-1.5"
                onClick={() => {
                  setSelectedEvent(undefined);
                  setFormOpen(true);
                }}
              >
                <PlusCircle className="h-4 w-4" />
                Add Calendar Entry
              </Button>
            )}
          </div>
        </div>

        {/* Temporary Homeowner Stay Alert */}
        {userStay && (
          <div
            className={`p-4 rounded-xl border flex items-start gap-3 transition-colors ${
              isWithinStay
                ? "bg-blue-50/50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-900/50"
                : "bg-amber-50/50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/50"
            }`}
          >
            <ShieldAlert
              className={`h-5 w-5 shrink-0 mt-0.5 ${
                isWithinStay ? "text-blue-600 dark:text-blue-400" : "text-amber-600 dark:text-amber-400"
              }`}
            />
            <div className="text-xs sm:text-sm space-y-1">
              <p className="font-semibold flex items-center gap-2">
                Temporary Homeowner Stay ({userStay.stayType})
                {isWithinStay ? (
                  <Badge variant="outline" className="text-blue-600 border-blue-500/30 text-[10px]">
                    Date Within Stay
                  </Badge>
                ) : (
                  <Badge variant="outline" className="text-amber-600 border-amber-500/40 text-[10px]">
                    Outside Stay Timeframe
                  </Badge>
                )}
              </p>
              <p className="text-muted-foreground">
                Authorized stay: <strong>{format(parseISO(userStay.leaseStart), "MMM d, yyyy")}</strong> to{" "}
                <strong>{format(parseISO(userStay.leaseEnd), "MMM d, yyyy")}</strong>. Visitor registrations are available on days within this window.
              </p>
            </div>
          </div>
        )}

        {/* Filter Pills */}
        <div className="flex flex-wrap items-center gap-2 pt-1">
          <Button
            size="sm"
            variant={activeTypeFilter === 'all' ? 'default' : 'outline'}
            onClick={() => setActiveTypeFilter('all')}
            className="text-xs h-8 px-3"
          >
            All Entries
          </Button>
          <Button
            size="sm"
            variant={activeTypeFilter === 'event' ? 'default' : 'outline'}
            onClick={() => setActiveTypeFilter('event')}
            className="text-xs h-8 px-3 gap-1.5"
          >
            <Sparkles className="h-3 w-3" /> Events
          </Button>
          <Button
            size="sm"
            variant={activeTypeFilter === 'public_holiday' ? 'default' : 'outline'}
            onClick={() => setActiveTypeFilter('public_holiday')}
            className="text-xs h-8 px-3 gap-1.5"
          >
            <Flag className="h-3 w-3 text-purple-500" /> Holidays
          </Button>
          <Button
            size="sm"
            variant={activeTypeFilter === 'office_closure' ? 'default' : 'outline'}
            onClick={() => setActiveTypeFilter('office_closure')}
            className="text-xs h-8 px-3 gap-1.5"
          >
            <Building className="h-3 w-3 text-amber-500" /> Office Closures
          </Button>
          <Button
            size="sm"
            variant={activeTypeFilter === 'meeting' ? 'default' : 'outline'}
            onClick={() => setActiveTypeFilter('meeting')}
            className="text-xs h-8 px-3 gap-1.5"
          >
            <Users className="h-3 w-3 text-blue-500" /> Meetings
          </Button>
          <Button
            size="sm"
            variant={activeTypeFilter === 'maintenance_window' ? 'default' : 'outline'}
            onClick={() => setActiveTypeFilter('maintenance_window')}
            className="text-xs h-8 px-3 gap-1.5"
          >
            <Wrench className="h-3 w-3 text-rose-500" /> Maintenance
          </Button>
          <Button
            size="sm"
            variant={activeTypeFilter === 'scheduled_notice' ? 'default' : 'outline'}
            onClick={() => setActiveTypeFilter('scheduled_notice')}
            className="text-xs h-8 px-3 gap-1.5"
          >
            <Megaphone className="h-3 w-3 text-sky-500" /> Notices
          </Button>
        </div>

        {/* Main Grid: Calendar Picker + Details */}
        <div className="grid lg:grid-cols-12 gap-6">
          {/* Calendar Picker Card */}
          <Card className="lg:col-span-5 shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">Select Date</CardTitle>
              <CardDescription>
                Choose a date to view gate clearance availability, holidays, and events.
              </CardDescription>
            </CardHeader>
            <CardContent className="flex justify-center pb-6">
              <Calendar
                mode="single"
                selected={selectedDate}
                onSelect={setSelectedDate}
                className="rounded-lg border shadow-sm p-3 pointer-events-auto"
              />
            </CardContent>
          </Card>

          {/* Right Column: Entry Slots & Day Overview */}
          <div className="lg:col-span-7 space-y-6">
            {/* Community Events on this Day */}
            <Card className="shadow-sm">
              <CardHeader className="pb-3 flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base font-semibold flex items-center gap-2">
                    <CalendarDays className="h-4 w-4 text-primary" />
                    Scheduled on {selectedDate ? format(selectedDate, "MMMM d, yyyy") : ""}
                  </CardTitle>
                  <CardDescription>
                    Events, closures, and maintenance windows. Reminders sent 24h in advance.
                  </CardDescription>
                </div>
                {canManage && (
                  <Button
                    size="sm"
                    variant="outline"
                    className="text-xs gap-1"
                    onClick={() => {
                      setSelectedEvent(undefined);
                      setFormOpen(true);
                    }}
                  >
                    <PlusCircle className="h-3.5 w-3.5" />
                    Add
                  </Button>
                )}
              </CardHeader>
              <CardContent>
                {dateEvents.length === 0 ? (
                  <p className="text-xs text-muted-foreground py-4 text-center border rounded-lg border-dashed">
                    No community events or notices scheduled for this day.
                  </p>
                ) : (
                  <div className="space-y-3">
                    {dateEvents.map((event) => (
                      <div key={event.id} className="p-3.5 rounded-xl border bg-card space-y-2 shadow-xs hover:border-primary/40 transition-colors">
                        {event.imageUrl && (
                          <div className="relative h-28 w-full rounded overflow-hidden mb-2">
                            <Image
                              src={event.imageUrl}
                              alt={event.title}
                              layout="fill"
                              objectFit="cover"
                            />
                          </div>
                        )}
                        <div className="flex items-start justify-between gap-2">
                          <div className="space-y-1">
                            <div className="flex items-center gap-2 flex-wrap">
                              <h4 className="font-semibold text-sm text-foreground">{event.title}</h4>
                              <Badge variant="outline" className={`text-[10px] flex items-center gap-1 font-medium ${getBadgeStyle(event.eventType)}`}>
                                {getTypeIcon(event.eventType)}
                                {event.eventTypeLabel}
                              </Badge>
                            </div>
                            {event.location && (
                              <p className="text-xs text-muted-foreground flex items-center gap-1">
                                <MapPin className="h-3 w-3 text-muted-foreground" />
                                {event.location}
                              </p>
                            )}
                          </div>
                          <div className="flex items-center gap-2">
                            <span className="text-xs font-mono text-muted-foreground flex items-center gap-1 whitespace-nowrap">
                              <Clock className="h-3 w-3" />
                              {formatEventTime(event)}
                            </span>
                            {canManage && (
                              <Button
                                size="sm"
                                variant="ghost"
                                className="h-7 w-7 p-0 text-muted-foreground hover:text-foreground"
                                onClick={() => handleEditEvent(event as any)}
                              >
                                <Edit3 className="h-3.5 w-3.5" />
                              </Button>
                            )}
                          </div>
                        </div>

                        <p className="text-xs text-muted-foreground leading-relaxed">{event.description}</p>

                        {event.changeSummary && (
                          <div className="p-2 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 text-[11px] text-amber-800 dark:text-amber-300">
                            <strong>Schedule Update:</strong> {event.changeSummary}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Entry Slots Card */}
            <Card className="shadow-sm">
              <CardHeader className="pb-3 flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base font-semibold flex items-center gap-2">
                    <ShieldCheck className="h-4 w-4 text-emerald-600" />
                    Available Entry Clearance Slots
                  </CardTitle>
                  <CardDescription>
                    Gate clearance availability for {selectedDate ? format(selectedDate, "EEEE, MMMM d, yyyy") : "Select a day"}
                  </CardDescription>
                </div>
                {canRegisterVisitors && isWithinStay && (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={handleRegisterVisitorForDate}
                    className="text-xs gap-1.5"
                  >
                    <span>Register</span>
                    <ArrowRight className="h-3.5 w-3.5" />
                  </Button>
                )}
              </CardHeader>
              <CardContent className="space-y-3">
                {entrySlots.map((slot) => (
                  <div
                    key={slot.id}
                    className="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 rounded-lg border bg-card hover:bg-muted/30 transition-colors gap-2"
                  >
                    <div className="space-y-0.5">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-sm text-foreground">{slot.name}</span>
                        <Badge
                          variant="secondary"
                          className="text-[10px] bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20 flex items-center gap-1 font-medium"
                        >
                          <CheckCircle2 className="h-2.5 w-2.5" />
                          {slot.status}
                        </Badge>
                      </div>
                      <p className="text-xs text-muted-foreground">{slot.description}</p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      <span className="text-xs font-mono font-medium px-2 py-1 rounded bg-muted text-muted-foreground">
                        {slot.timeRange}
                      </span>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>

            {/* Scheduled Visitors for Selected Day */}
            <Card className="shadow-sm">
              <CardHeader className="pb-3">
                <CardTitle className="text-base font-semibold flex items-center gap-2">
                  <UserCheck className="h-4 w-4 text-primary" />
                  Your Scheduled Visitors for this Day
                </CardTitle>
                <CardDescription>
                  Visitor clearances pre-authorized by your profile for {selectedDate ? format(selectedDate, "MMM d") : ""}.
                </CardDescription>
              </CardHeader>
              <CardContent>
                {dateVisitors.length === 0 ? (
                  <p className="text-xs text-muted-foreground py-2">
                    No visitor arrivals currently scheduled for this date.
                  </p>
                ) : (
                  <div className="space-y-2">
                    {dateVisitors.map((visitor) => (
                      <div
                        key={visitor.id}
                        className="flex items-center justify-between p-2.5 rounded-lg border bg-muted/20 text-xs"
                      >
                        <div className="space-y-0.5">
                          <p className="font-semibold text-foreground">{visitor.name}</p>
                          <p className="text-muted-foreground">
                            {visitor.type} access · {format(parseISO(visitor.expectedAt), "h:mm a")}
                          </p>
                        </div>
                        <Badge variant="outline">{visitor.status}</Badge>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      {canManage && (
        <EventForm
          open={isFormOpen}
          onOpenChange={setFormOpen}
          onSave={(data, id) => {
            const payload = {
              title: data.title,
              description: data.description,
              event_type: data.eventType || 'event',
              location: data.location || null,
              change_summary: data.changeSummary || null,
              start_date: data.startDate.toISOString(),
              end_date: data.endDate ? data.endDate.toISOString() : null,
              image_url: data.imageUrl ?? null,
            };

            return id
              ? submit('patch', `/dashboard/calendar/${id}`, payload)
              : submit('post', '/dashboard/calendar', payload);
          }}
          event={selectedEvent}
        />
      )}
    </DashboardLayout>
  );
}
