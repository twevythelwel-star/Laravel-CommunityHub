
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
import { DollarSign, Users } from "lucide-react";
import { Button } from "@/components/ui/button";

const mockTransactions = [
    { id: '1', homeowner: 'John Smith (Lot 12)', date: '2023-07-20', amount: 30.00, status: 'Paid' },
    { id: '2', homeowner: 'Emma Watson (Lot 25)', date: '2023-07-19', amount: 30.00, status: 'Paid' },
    { id: '3', homeowner: 'Michael B. (Lot 03)', date: '2023-07-01', amount: 30.00, status: 'Overdue' },
    { id: '4', homeowner: 'Olivia Davis (Lot 42)', date: '2023-07-18', amount: 30.00, status: 'Paid' },
];

const totalCollected = mockTransactions.filter(t => t.status === 'Paid').reduce((acc, t) => acc + t.amount, 0);
const outstandingDues = mockTransactions.filter(t => t.status !== 'Paid').reduce((acc, t) => acc + t.amount, 0);


export function AdminBilling() {
  return (
    <div className="flex flex-1 flex-col gap-8">
      <div>
        <h1 className="font-headline text-3xl font-bold">Billing Overview</h1>
        <p className="text-muted-foreground">Monitor community payments and dues.</p>
      </div>

       <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">${outstandingDues.toFixed(2)}</div>
              <p className="text-xs text-muted-foreground">
                from {mockTransactions.filter(t => t.status !== 'Paid').length} household
              </p>
            </CardContent>
          </Card>
      </div>

       <Card>
        <CardHeader>
          <CardTitle>Recent Transactions</CardTitle>
          <CardDescription>
            A log of recent payments and outstanding dues.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Homeowner</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Amount</TableHead>
                <TableHead>Status</TableHead>
                 <TableHead><span className="sr-only">Actions</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {mockTransactions.map((transaction) => (
                <TableRow key={transaction.id}>
                  <TableCell className="font-medium">{transaction.homeowner}</TableCell>
                  <TableCell>{transaction.date}</TableCell>
                  <TableCell>${transaction.amount.toFixed(2)}</TableCell>
                  <TableCell>
                     <Badge variant={transaction.status === 'Paid' ? 'secondary' : 'destructive'}>
                        {transaction.status}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {transaction.status !== 'Paid' && (
                        <Button variant="outline" size="sm">Send Reminder</Button>
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
