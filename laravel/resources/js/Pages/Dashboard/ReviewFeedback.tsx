

import { useState } from "react";
import { Head } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
  } from "@/components/ui/dropdown-menu";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { MoreHorizontal } from "lucide-react";
import type { Feedback } from "@/types";
import { ClientFormattedDate } from "@/components/client-formatted-date";

const mockFeedback: Feedback[] = [
    {
        id: '1',
        submittedBy: 'User-Homeowner@example.com',
        userRole: 'Homeowner',
        timestamp: new Date('2024-07-30T10:00:00Z'),
        type: 'Issue',
        subject: 'App crashes on login',
        status: 'New',
    },
    {
        id: '2',
        submittedBy: 'User-Admin@example.com',
        userRole: 'Admin',
        timestamp: new Date('2024-07-29T14:30:00Z'),
        type: 'Suggestion',
        subject: 'Add dark mode',
        status: 'In Progress',
    },
    {
        id: '3',
        submittedBy: 'User-Security@example.com',
        userRole: 'Security',
        timestamp: new Date('2024-07-28T09:00:00Z'),
        type: 'Issue',
        subject: 'Visitor log not updating',
        status: 'Resolved',
    },
    {
        id: '4',
        submittedBy: 'User-Renter@example.com',
        userRole: 'Temporary Homeowner',
        timestamp: new Date('2024-07-27T18:45:00Z'),
        type: 'Suggestion',
        subject: 'Easier way to contact landlord',
        status: 'New',
    },
];

export default function ReviewFeedbackPage() {
    const [feedbackList, setFeedbackList] = useState<Feedback[]>(mockFeedback);

    const handleStatusChange = (id: string, status: 'New' | 'In Progress' | 'Resolved') => {
        setFeedbackList(feedbackList.map(item => item.id === id ? { ...item, status } : item));
    }

    const handleDelete = (id: string) => {
        setFeedbackList(feedbackList.filter(item => item.id !== id));
    }

    const getStatusVariant = (status: Feedback['status']) => {
        switch (status) {
            case 'New':
                return 'default';
            case 'In Progress':
                return 'secondary';
            case 'Resolved':
                return 'outline';
            default:
                return 'default';
        }
    }

  return (
    <DashboardLayout>
      <Head title="Review Feedback" />
      <div className="grid gap-8 max-w-7xl mx-auto pb-12">
        <div>
          <h1 className="font-headline text-3xl font-bold">Review Feedback</h1>
          <p className="text-muted-foreground">Manage and review user-submitted issues and suggestions.</p>
        </div>
         <Card className="mt-2">
          <CardHeader>
            <CardTitle>Submitted Feedback</CardTitle>
            <CardDescription>
              Review and take action on feedback from all users.
            </CardDescription>
          </CardHeader>
          <CardContent>
              <Table>
                  <TableHeader>
                      <TableRow>
                          <TableHead>Submitted By</TableHead>
                          <TableHead>Subject</TableHead>
                          <TableHead>Type</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>Date</TableHead>
                          <TableHead>
                              <span className="sr-only">Actions</span>
                          </TableHead>
                      </TableRow>
                  </TableHeader>
                  <TableBody>
                      {feedbackList.map((item) => (
                          <TableRow key={item.id}>
                              <TableCell>
                                  <div className="font-medium">{item.submittedBy.split('@')[0]}</div>
                                  <div className="text-sm text-muted-foreground">{item.userRole}</div>
                              </TableCell>
                              <TableCell className="font-medium">{item.subject}</TableCell>
                              <TableCell>{item.type}</TableCell>
                              <TableCell>
                                  <Badge variant={getStatusVariant(item.status)}>{item.status}</Badge>
                              </TableCell>
                              <TableCell>
                                 <ClientFormattedDate date={item.timestamp} formatString="MMM d, yyyy" />
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
                                      <DropdownMenuItem onClick={() => handleStatusChange(item.id, 'New')}>Mark as New</DropdownMenuItem>
                                      <DropdownMenuItem onClick={() => handleStatusChange(item.id, 'In Progress')}>Mark as In Progress</DropdownMenuItem>
                                      <DropdownMenuItem onClick={() => handleStatusChange(item.id, 'Resolved')}>Mark as Resolved</DropdownMenuItem>
                                      <DropdownMenuSeparator />
                                      <DropdownMenuItem className="text-red-600" onClick={() => handleDelete(item.id)}>Delete</DropdownMenuItem>
                                  </DropdownMenuContent>
                                  </DropdownMenu>
                              </TableCell>
                          </TableRow>
                      ))}
                  </TableBody>
              </Table>
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  );
}
