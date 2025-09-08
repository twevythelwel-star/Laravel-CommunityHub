
'use client';

import { useState, useEffect } from "react";
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { Calendar as CalendarIcon, MoreHorizontal, PlusCircle } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { useAuth } from "@/context/auth-context";
import { useToast } from "@/hooks/use-toast";
import { add, format, sub, formatISO } from "date-fns";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import type { DateRange } from "react-day-picker";

type VisitorStatus = "Expected" | "Checked In" | "Checked Out";
type EntryType = "onetime" | "recurring";

type Visitor = {
  id: string;
  name: string;
  type: "One-time" | "Recurring";
  status: VisitorStatus;
  expectedAt: Date;
  dateRange: string;
  homeowner: string;
};

const getInitialVisitors = (): Visitor[] => {
    const now = new Date();
    return [
        {
            id: "1",
            name: "Liam Johnson",
            type: "One-time",
            status: "Expected",
            expectedAt: now,
            dateRange: format(now, "yyyy-MM-dd"),
            homeowner: "Olivia Davis (Lot 42)",
        },
        {
            id: "2",
            name: "Noah Williams",
            type: "Recurring",
            status: "Checked In",
            expectedAt: sub(now, { days: 1 }),
            dateRange: `${format(sub(now, {days: 1}), "yyyy-MM-dd")} - ${format(add(now, {days: 60}), "yyyy-MM-dd")}`,
            homeowner: "John Smith (Lot 12)",
        },
        {
            id: "3",
            name: "Expired Visitor",
            type: "One-time",
            status: "Expected",
            expectedAt: sub(now, { hours: 13 }),
            dateRange: format(sub(now, { hours: 13 }), "yyyy-MM-dd"),
            homeowner: "John Smith (Lot 12)",
        },
    ];
};


export default function VisitorsPage() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [visitors, setVisitors] = useState<Visitor[]>([]);
  const [entryType, setEntryType] = useState<EntryType>('onetime');
  const [expectedDate, setExpectedDate] = useState<Date | undefined>();
  const [dateRange, setDateRange] = useState<DateRange | undefined>();
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setVisitors(getInitialVisitors());
    setDateRange({ from: new Date(), to: add(new Date(), { days: 7 }) });
    setExpectedDate(new Date());
    setIsClient(true);
  }, []);

  useEffect(() => {
    if (!isClient) return;

    const interval = setInterval(() => {
      const now = new Date();
      const twelveHoursAgo = sub(now, { hours: 12 });
      
      setVisitors(currentVisitors => {
        const updatedVisitors = currentVisitors.filter(visitor => {
            if (visitor.status === 'Expected' && visitor.expectedAt < twelveHoursAgo) {
            toast({
                variant: "destructive",
                title: "Visitor Removed",
                description: `${visitor.name} was automatically removed for not checking in within 12 hours.`
            });
            return false;
            }
            return true;
        });
        return updatedVisitors;
      });

    }, 60 * 1000); // Check every minute

    return () => clearInterval(interval);
  }, [isClient, toast]);


  const handleStatusChange = (visitorId: string, newStatus: VisitorStatus) => {
    const visitor = visitors.find(v => v.id === visitorId);
    if (!visitor) return;

    setVisitors(visitors.map(v => v.id === visitorId ? { ...v, status: newStatus } : v));
    
    toast({
        title: `Visitor ${newStatus}`,
        description: `${visitor.name} has been ${newStatus.toLowerCase()}. ${visitor.homeowner} has been notified.`
    })
  };

  const getStatusVariant = (status: VisitorStatus) => {
    switch (status) {
        case 'Checked In':
            return 'default';
        case 'Checked Out':
            return 'secondary';
        case 'Expected':
        default:
            return 'outline';
    }
  }

  if (!isClient) {
    return (
        <div className="flex flex-col gap-8">
            <div className="flex items-center">
                <div className="flex-1">
                <h1 className="font-headline text-3xl font-bold">Visitor Management</h1>
                <p className="text-muted-foreground">
                    Loading visitor information...
                </p>
                </div>
            </div>
            <Card>
                <CardHeader>
                <CardTitle>Registered Visitors</CardTitle>
                <CardDescription>
                    Loading...
                </CardDescription>
                </CardHeader>
                <CardContent>
                <p>Please wait while we load the visitor data.</p>
                </CardContent>
            </Card>
        </div>
    );
  }

  return (
    <div className="flex flex-col gap-8">
      <div className="flex items-center">
        <div className="flex-1">
          <h1 className="font-headline text-3xl font-bold">Visitor Management</h1>
          <p className="text-muted-foreground">
            {user?.role === 'Security' 
              ? "Review and manage daily visitor access." 
              : "Register and manage visitors for your property."
            }
          </p>
        </div>
        {user?.role !== 'Security' && (
            <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" className="gap-1">
                <PlusCircle className="h-3.5 w-3.5" />
                <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                    Register Visitor
                </span>
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[480px]">
                <DialogHeader>
                <DialogTitle>Register New Visitor</DialogTitle>
                <DialogDescription>
                    Fill in the details for the new visitor. Click save when you're done.
                </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid grid-cols-4 items-center gap-4">
                      <Label htmlFor="name" className="text-right">Name</Label>
                      <Input id="name" placeholder="John Doe" className="col-span-3" />
                  </div>
                  <div className="grid grid-cols-4 items-center gap-4">
                      <Label htmlFor="contact" className="text-right">Contact</Label>
                      <Input id="contact" placeholder="Phone or Email" className="col-span-3" />
                  </div>
                  <div className="grid grid-cols-4 items-center gap-4">
                      <Label htmlFor="vehicle" className="text-right">Vehicle</Label>
                      <Input id="vehicle" placeholder="Details (optional)" className="col-span-3" />
                  </div>
                  <div className="grid grid-cols-4 items-center gap-4">
                    <Label htmlFor="id-type" className="text-right">ID Type</Label>
                    <Select>
                      <SelectTrigger className="col-span-3">
                        <SelectValue placeholder="Select ID Type" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="drivers-license">Driver's License</SelectItem>
                        <SelectItem value="passport">Passport</SelectItem>
                        <SelectItem value="national-id">National ID</SelectItem>
                        <SelectItem value="school-id">School ID</SelectItem>
                        <SelectItem value="work-id">Work ID</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                   <div className="grid grid-cols-4 items-center gap-4">
                      <Label htmlFor="id-number" className="text-right">ID #</Label>
                      <Input id="id-number" placeholder="Identification Number" className="col-span-3" />
                  </div>
                  <div className="grid grid-cols-4 items-center gap-4">
                      <Label htmlFor="id-picture" className="text-right">ID Picture</Label>
                      <Input id="id-picture" type="file" className="col-span-3" />
                  </div>

                  <div className="grid grid-cols-4 items-center gap-4">
                    <Label className="text-right">Entry Type</Label>
                    <RadioGroup value={entryType} onValueChange={(value: string) => setEntryType(value as EntryType)} className="col-span-3 flex gap-4">
                      <div className="flex items-center space-x-2">
                          <RadioGroupItem value="onetime" id="r1" />
                          <Label htmlFor="r1">One-time</Label>
                      </div>
                      <div className="flex items-center space-x-2">
                          <RadioGroupItem value="recurring" id="r2" />
                          <Label htmlFor="r2">Recurring</Label>
                      </div>
                    </RadioGroup>
                  </div>

                  {entryType === 'onetime' && (
                    <div className="grid grid-cols-4 items-center gap-4">
                      <Label className="text-right">Date & Time</Label>
                      <div className="col-span-3 flex gap-2">
                         <Popover>
                            <PopoverTrigger asChild>
                              <Button
                                variant={"outline"}
                                className={cn(
                                  "w-[150px] justify-start text-left font-normal",
                                  !expectedDate && "text-muted-foreground"
                                )}
                              >
                                <CalendarIcon className="mr-2 h-4 w-4" />
                                {expectedDate ? format(expectedDate, "PPP") : <span>Pick a date</span>}
                              </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0">
                              <Calendar
                                mode="single"
                                selected={expectedDate}
                                onSelect={setExpectedDate}
                                initialFocus
                              />
                            </PopoverContent>
                          </Popover>
                          <Select defaultValue="10">
                            <SelectTrigger className="w-[80px]"><SelectValue/></SelectTrigger>
                            <SelectContent>{Array.from({length: 12}, (_,i)=> i+1).map(h => <SelectItem key={h} value={`${h}`}>{h}</SelectItem>)}</SelectContent>
                          </Select>
                           <Select defaultValue="00">
                            <SelectTrigger className="w-[80px]"><SelectValue/></SelectTrigger>
                            <SelectContent>{['00', '15', '30', '45'].map(m => <SelectItem key={m} value={m}>{m}</SelectItem>)}</SelectContent>
                          </Select>
                          <Select defaultValue="PM">
                            <SelectTrigger className="w-[80px]"><SelectValue/></SelectTrigger>
                            <SelectContent>
                              <SelectItem value="AM">AM</SelectItem>
                              <SelectItem value="PM">PM</SelectItem>
                            </SelectContent>
                          </Select>
                      </div>
                    </div>
                  )}

                  {entryType === 'recurring' && (
                     <div className="grid grid-cols-4 items-center gap-4">
                        <Label className="text-right">Date Range</Label>
                        <div className="col-span-3">
                           <Popover>
                            <PopoverTrigger asChild>
                              <Button
                                id="date"
                                variant={"outline"}
                                className={cn(
                                  "w-full justify-start text-left font-normal",
                                  !dateRange && "text-muted-foreground"
                                )}
                              >
                                <CalendarIcon className="mr-2 h-4 w-4" />
                                {dateRange?.from ? (
                                  dateRange.to ? (
                                    <>
                                      {format(dateRange.from, "LLL dd, y")} -{" "}
                                      {format(dateRange.to, "LLL dd, y")}
                                    </>
                                  ) : (
                                    format(dateRange.from, "LLL dd, y")
                                  )
                                ) : (
                                  <span>Pick a date range</span>
                                )}
                              </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                              <Calendar
                                initialFocus
                                mode="range"
                                defaultMonth={dateRange?.from}
                                selected={dateRange}
                                onSelect={setDateRange}
                                numberOfMonths={2}
                              />
                            </PopoverContent>
                          </Popover>
                        </div>
                    </div>
                  )}


                </div>
                <DialogFooter>
                  <Button type="submit">Save visitor</Button>
                </DialogFooter>
            </DialogContent>
            </Dialog>
        )}
      </div>
      <Card>
        <CardHeader>
          <CardTitle>
            {user?.role === 'Security' ? "Today's Visitors" : "Registered Visitors"}
          </CardTitle>
          <CardDescription>
            {user?.role === 'Security' 
              ? "A list of all expected visitors for today."
              : "A list of all visitors you have registered."
            }
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Visitor Name</TableHead>
                {user?.role === 'Security' && <TableHead>Registered By</TableHead>}
                <TableHead>Type</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="hidden md:table-cell">Date Range</TableHead>
                <TableHead>
                  <span className="sr-only">Actions</span>
                  Actions
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {visitors.map((visitor) => (
                <TableRow key={visitor.id}>
                  <TableCell className="font-medium">{visitor.name}</TableCell>
                   {user?.role === 'Security' && <TableCell>{visitor.homeowner}</TableCell>}
                  <TableCell>{visitor.type}</TableCell>
                  <TableCell>
                    <Badge variant={getStatusVariant(visitor.status)}>{visitor.status}</Badge>
                  </TableCell>
                  <TableCell className="hidden md:table-cell">
                    {visitor.dateRange}
                  </TableCell>
                  <TableCell>
                    {user?.role === 'Security' ? (
                        <div className="flex gap-2">
                            <Button 
                                size="sm" 
                                variant="outline"
                                onClick={() => handleStatusChange(visitor.id, 'Checked In')}
                                disabled={visitor.status === 'Checked In' || visitor.status === 'Checked Out'}
                            >
                                Check In
                            </Button>
                             <Button 
                                size="sm" 
                                variant="destructive"
                                onClick={() => handleStatusChange(visitor.id, 'Checked Out')}
                                disabled={visitor.status === 'Expected' || visitor.status === 'Checked Out'}
                            >
                                Check Out
                            </Button>
                        </div>
                    ) : (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                            <Button aria-haspopup="true" size="icon" variant="ghost">
                                <MoreHorizontal className="h-4 w-4" />
                                <span className="sr-only">Toggle menu</span>
                            </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            <DropdownMenuItem>Edit</DropdownMenuItem>
                            <DropdownMenuItem>Delete</DropdownMenuItem>
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
    </div>
  );
}
