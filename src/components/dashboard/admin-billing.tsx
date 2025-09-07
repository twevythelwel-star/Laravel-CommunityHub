
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
import { Bar, BarChart, CartesianGrid, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer, Cell } from "recharts";
import { ChartContainer, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart";


const mockTransactions = [
    { id: '1', homeowner: 'John Smith (Lot 12)', date: '2023-07-20', amount: 30.00, status: 'Paid' },
    { id: '2', homeowner: 'Emma Watson (Lot 25)', date: '2023-07-19', amount: 30.00, status: 'Paid' },
    { id: '3', homeowner: 'Michael B. (Lot 03)', date: '2023-07-01', amount: 30.00, status: 'Overdue' },
    { id: '4', homeowner: 'Olivia Davis (Lot 42)', date: '2023-07-18', amount: 30.00, status: 'Paid' },
];

const monthlyCollectionsData = [
  { month: "Jan", total: 1860 },
  { month: "Feb", total: 1900 },
  { month: "Mar", total: 2000 },
  { month: "Apr", total: 1780 },
  { month: "May", total: 1890 },
  { month: "Jun", total: 2390 },
  { month: "Jul", total: 2490 },
  { month: "Aug", total: 2300 },
  { month: "Sep", total: 2100 },
  { month: "Oct", total: 2400 },
  { month: "Nov", total: 2500 },
  { month: "Dec", total: 2600 },
];

const currentMonthIndex = new Date().getMonth();

const chartConfig = {
  total: {
    label: "Total Collected",
  },
}


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
          <CardTitle>Monthly Collections</CardTitle>
          <CardDescription>
            A summary of dues collected over the last 12 months.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ChartContainer config={chartConfig} className="min-h-[200px] w-full">
            <ResponsiveContainer width="100%" height={300}>
                <BarChart data={monthlyCollectionsData} margin={{ top: 20, right: 20, bottom: 20, left: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="month" stroke="hsl(var(--muted-foreground))" fontSize={12} tickLine={false} axisLine={false} />
                    <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `$${value}`} />
                     <ChartTooltip
                      cursor={false}
                      content={<ChartTooltipContent indicator="dot" />}
                    />
                    <Bar dataKey="total" radius={[4, 4, 0, 0]}>
                        {monthlyCollectionsData.map((entry, index) => (
                            <Cell key={`cell-${index}`} fill={index === currentMonthIndex ? "hsl(var(--primary))" : "hsl(var(--primary), 0.3)"} />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
          </ChartContainer>
        </CardContent>
      </Card>


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
