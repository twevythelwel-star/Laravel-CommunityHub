
'use client';

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
import { ArrowUpRight, CalendarCheck, Users, Bell, Siren, DollarSign } from "lucide-react";
import { Button } from "@/components/ui/button";
import Link from "next/link";
import Image from "next/image";
import { useAuth } from "@/context/auth-context";
import { useState, useEffect } from "react";
import { format } from "date-fns";

function ClientFormattedDate({ dateString }: { dateString: string }) {
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  if (!isClient) {
    return <>...</>;
  }

  return <>{format(new Date(dateString), 'yyyy-MM-dd')}</>;
}

const mockTransactions = [
    { id: '1', homeowner: 'John Smith (Lot 12)', date: '2023-07-20', amount: 30.00, status: 'Paid' },
    { id: '2', homeowner: 'Emma Watson (Lot 25)', date: '2023-07-19', amount: 30.00, status: 'Paid' },
    { id: '3', homeowner: 'Michael B. (Lot 03)', date: '2023-07-01', amount: 30.00, status: 'Overdue' },
    { id: '4', homeowner: 'Olivia Davis (Lot 42)', date: '2023-07-18', amount: 30.00, status: 'Paid' },
];

const totalCollected = mockTransactions.filter(t => t.status === 'Paid').reduce((acc, t) => acc + t.amount, 0);
const outstandingDues = mockTransactions.filter(t => t.status !== 'Paid').reduce((acc, t) => acc + t.amount, 0);


export default function Dashboard() {
  const { user } = useAuth();

  const canViewActiveResidents = user && ['System Admin', 'Admin', 'Security'].includes(user.role);
  const canViewUpcomingVisitors = user && ['System Admin', 'Homeowner', 'Temporary Homeowner', 'Security'].includes(user.role);
  const canViewAnnouncements = user && ['System Admin', 'Admin', 'Homeowner'].includes(user.role);
  const canViewWarnings = user && ['System Admin', 'Admin', 'Homeowner', 'Security'].includes(user.role);
  const canViewRecentVisitors = user && ['System Admin', 'Admin', 'Homeowner', 'Security'].includes(user.role);
  const canViewBilling = user && ['System Admin', 'Admin'].includes(user.role);
  
  if (!user) {
    return null;
  }

  return (
    <div className="flex flex-1 flex-col">
       <div className="grid gap-4 md:grid-cols-2 md:gap-8 lg:grid-cols-4">
        {canViewActiveResidents && (
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">
                Active Residents
              </CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">452</div>
              <p className="text-xs text-muted-foreground">
                +12 from last month
              </p>
            </CardContent>
          </Card>
        )}
         {canViewBilling && (
          <>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">
                  Total Collected (July)
                </CardTitle>
                <DollarSign className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">${totalCollected.toFixed(2)}</div>
                <p className="text-xs text-muted-foreground">
                  from {mockTransactions.filter(t => t.status === 'Paid').length} households
                </p>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">
                  Outstanding Dues
                </CardTitle>
                 <DollarSign className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">${outstandingDues.toFixed(2)}</div>
                <p className="text-xs text-muted-foreground">
                  from {mockTransactions.filter(t => t.status !== 'Paid').length} household
                </p>
              </CardContent>
            </Card>
          </>
        )}
        {canViewUpcomingVisitors && (
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">
                Upcoming Visitors
              </CardTitle>
              <CalendarCheck className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">15</div>
              <p className="text-xs text-muted-foreground">
                +3 scheduled today
              </p>
            </CardContent>
          </Card>
        )}
        {canViewAnnouncements && (
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">New Announcements</CardTitle>
              <Bell className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">3</div>
              <p className="text-xs text-muted-foreground">
                In the last 7 days
              </p>
            </CardContent>
          </Card>
        )}
        {canViewWarnings && (
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Active Warnings</CardTitle>
              <Siren className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">1</div>
              <p className="text-xs text-muted-foreground">
                Requires attention
              </p>
            </CardContent>
          </Card>
        )}
      </div>
      <div className="grid gap-4 md:gap-8 lg:grid-cols-2 xl:grid-cols-3 mt-8">
        {canViewRecentVisitors && (
          <Card className="xl:col-span-2">
            <CardHeader className="flex flex-row items-center">
              <div className="grid gap-2">
                <CardTitle>Recent Visitors</CardTitle>
                <CardDescription>
                  A log of the most recent visitors to the community.
                </CardDescription>
              </div>
              <Button asChild size="sm" className="ml-auto gap-1">
                <Link href="/dashboard/visitors">
                  View All
                  <ArrowUpRight className="h-4 w-4" />
                </Link>
              </Button>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Visitor</TableHead>
                    <TableHead className="hidden xl:table-column">
                      Type
                    </TableHead>
                    <TableHead className="hidden xl:table-column">
                      Status
                    </TableHead>
                    <TableHead className="hidden md:table-cell">
                      Homeowner
                    </TableHead>
                    <TableHead className="text-right">Date</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow>
                    <TableCell>
                      <div className="font-medium">Liam Johnson</div>
                      <div className="hidden text-sm text-muted-foreground md:inline">
                        liam@example.com
                      </div>
                    </TableCell>
                    <TableCell className="hidden xl:table-column">
                      One-time
                    </TableCell>
                    <TableCell className="hidden xl:table-column">
                      <Badge className="text-xs" variant="outline">
                        Approved
                      </Badge>
                    </TableCell>
                    <TableCell className="hidden md:table-cell">
                      Olivia Davis (Lot 42)
                    </TableCell>
                    <TableCell className="text-right"><ClientFormattedDate dateString="2023-06-23" /></TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell>
                      <div className="font-medium">Noah Williams</div>
                      <div className="hidden text-sm text-muted-foreground md:inline">
                        noah@example.com
                      </div>
                    </TableCell>
                    <TableCell className="hidden xl:table-column">
                      Recurring
                    </TableCell>
                    <TableCell className="hidden xl:table-column">
                      <Badge className="text-xs" variant="outline">
                        Approved
                      </Badge>
                    </TableCell>
                    <TableCell className="hidden md:table-cell">
                      John Smith (Lot 12)
                    </TableCell>
                    <TableCell className="text-right"><ClientFormattedDate dateString="2023-06-24" /></TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

         {canViewBilling && (
          <Card>
            <CardHeader className="flex flex-row items-center">
              <div className="grid gap-2">
                <CardTitle>Recent Transactions</CardTitle>
                 <CardDescription>
                  A log of recent payments and outstanding dues.
                </CardDescription>
              </div>
               <Button asChild size="sm" className="ml-auto gap-1">
                <Link href="/dashboard/billing">
                  View All
                  <ArrowUpRight className="h-4 w-4" />
                </Link>
              </Button>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Homeowner</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Amount</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {mockTransactions.slice(0,3).map(transaction => (
                    <TableRow key={transaction.id}>
                        <TableCell>
                            <div className="font-medium">{transaction.homeowner.split('(')[0].trim()}</div>
                            <div className="hidden text-sm text-muted-foreground md:inline">
                                {transaction.homeowner.match(/\(([^)]+)\)/)?.[1]}
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge variant={transaction.status === 'Paid' ? 'secondary' : 'destructive'}>
                                {transaction.status}
                            </Badge>
                        </TableCell>
                        <TableCell className="text-right">${transaction.amount.toFixed(2)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle>Community Promotions</CardTitle>
            <CardDescription>
              Special offers and ads for our residents.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4">
            <div className="flex items-center gap-4">
              <Image alt="Promotion" className="aspect-square rounded-lg object-cover" height="64" src="https://picsum.photos/64/64?p=1" width="64" data-ai-hint="food delivery" />
              <div className="grid gap-1">
                <p className="text-sm font-medium leading-none">
                  Free Delivery Friday!
                </p>
                <p className="text-sm text-muted-foreground">
                  From 'Local Eats' tonight only.
                </p>
              </div>
            </div>
             <div className="flex items-center gap-4">
              <Image alt="Promotion" className="aspect-square rounded-lg object-cover" height="64" src="https://picsum.photos/64/64?p=2" width="64" data-ai-hint="fitness gym" />
              <div className="grid gap-1">
                <p className="text-sm font-medium leading-none">
                  50% off Gym Membership
                </p>
                <p className="text-sm text-muted-foreground">
                  Join 'Community Fit' this month.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
