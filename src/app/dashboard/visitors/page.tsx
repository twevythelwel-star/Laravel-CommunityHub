
'use client';

import { useState } from "react";
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
import { MoreHorizontal, PlusCircle } from "lucide-react";
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

type VisitorStatus = "Expected" | "Checked In" | "Checked Out";

type Visitor = {
  id: string;
  name: string;
  type: "One-time" | "Recurring";
  status: VisitorStatus;
  dateRange: string;
  homeowner: string;
};

const initialVisitors: Visitor[] = [
  {
    id: "1",
    name: "Liam Johnson",
    type: "One-time",
    status: "Expected",
    dateRange: "2023-06-23",
    homeowner: "Olivia Davis (Lot 42)",
  },
  {
    id: "2",
    name: "Noah Williams",
    type: "Recurring",
    status: "Checked In",
    dateRange: "2023-06-20 - 2023-08-20",
    homeowner: "John Smith (Lot 12)",
  },
];

export default function VisitorsPage() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [visitors, setVisitors] = useState<Visitor[]>(initialVisitors);

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
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                <DialogTitle>Register New Visitor</DialogTitle>
                <DialogDescription>
                    Fill in the details for the new visitor. Click save when you're done.
                </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                <div className="grid grid-cols-4 items-center gap-4">
                    <Label htmlFor="name" className="text-right">
                    Name
                    </Label>
                    <Input id="name" placeholder="John Doe" className="col-span-3" />
                </div>
                <div className="grid grid-cols-4 items-center gap-4">
                    <Label htmlFor="contact" className="text-right">
                    Contact
                    </Label>
                    <Input id="contact" placeholder="Phone or Email" className="col-span-3" />
                </div>
                <div className="grid grid-cols-4 items-center gap-4">
                    <Label htmlFor="vehicle" className="text-right">
                    Vehicle
                    </Label>
                    <Input id="vehicle" placeholder="Details (optional)" className="col-span-3" />
                </div>
                <div className="grid grid-cols-4 items-center gap-4">
                    <Label className="text-right">Entry Type</Label>
                    <RadioGroup defaultValue="onetime" className="col-span-3 flex gap-4">
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
