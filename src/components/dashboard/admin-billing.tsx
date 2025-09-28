
'use client';

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  CardFooter,
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useState, useEffect, useMemo } from "react";
import { useBilling, MOCK_EXCHANGE_RATES } from "@/context/billing-context";
import { useToast } from "@/hooks/use-toast";
import { format } from "date-fns";


const mockTransactions = [
    { id: '1', homeowner: 'John Smith (Lot 12)', date: '2025-07-20', amount: 5000.00, status: 'Paid' },
    { id: '2', homeowner: 'Emma Watson (Lot 25)', date: '2025-07-19', amount: 5000.00, status: 'Paid' },
    { id: '3', homeowner: 'Michael B. (Lot 03)', date: '2025-07-01', amount: 5000.00, status: 'Overdue' },
    { id: '4', homeowner: 'Olivia Davis (Lot 42)', date: '2025-07-18', amount: 5000.00, status: 'Paid' },
];

const monthlyCollectionsData = [
  { month: "Jan", total: 310000 },
  { month: "Feb", total: 320000 },
  { month: "Mar", total: 335000 },
  { month: "Apr", total: 290000 },
  { month: "May", total: 315000 },
  { month: "Jun", total: 380000 },
  { month: "Jul", total: 400000 },
  { month: "Aug", total: 385000 },
  { month: "Sep", total: 350000 },
  { month: "Oct", total: 410000 },
  { month: "Nov", total: 420000 },
  { month: "Dec", total: 435000 },
];


const chartConfig = {
  total: {
    label: "Total Collected",
  },
}


export function AdminBilling() {
  const { monthlyFee, setMonthlyFee } = useBilling();
  const { toast } = useToast();
  const [newFee, setNewFee] = useState(monthlyFee);
  const [currentMonthName, setCurrentMonthName] = useState('');
  const [currentMonthIndex, setCurrentMonthIndex] = useState(0);
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
    const now = new Date();
    setCurrentMonthName(format(now, 'MMMM'));
    setCurrentMonthIndex(now.getMonth());
  }, []);

  const totalCollected = mockTransactions.filter(t => t.status === 'Paid').reduce((acc, t) => acc + t.amount, 0);
  const outstandingDues = mockTransactions.filter(t => t.status !== 'Paid').reduce((acc, t) => acc + t.amount, 0);

  const convertedAmounts = useMemo(() => {
    if (!newFee) return null;
    const usd = (newFee * MOCK_EXCHANGE_RATES.JMD_TO_USD);
    const gbp = (newFee * MOCK_EXCHANGE_RATES.JMD_TO_GBP);
    const eur = (newFee * MOCK_EXCHANGE_RATES.JMD_TO_EUR);
    const cad = (newFee * MOCK_EXCHANGE_RATES.JMD_TO_CAD);

    return {
        usd: new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(usd),
        gbp: new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(gbp),
        eur: new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(eur),
        cad: new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(cad),
    }
  }, [newFee]);

  const handleFeeChange = () => {
    setMonthlyFee(newFee);
    toast({
        title: "Success",
        description: `Monthly fee has been updated to JMD ${newFee.toFixed(2)}.`
    })
  }
  
  if (!isClient) {
    return null;
  }

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
                Total Collected ({currentMonthName})
              </CardTitle>
              <DollarSign className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">JMD {totalCollected.toLocaleString('en-JM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
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
              <div className="text-2xl font-bold">JMD {outstandingDues.toLocaleString('en-JM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
              <p className="text-xs text-muted-foreground">
                from {mockTransactions.filter(t => t.status !== 'Paid').length} household
              </p>
            </CardContent>
          </Card>
      </div>

       <Card>
            <CardHeader>
                <CardTitle>Manage Monthly Fee</CardTitle>
                <CardDescription>
                    Set the monthly HOA fee for all residents in Jamaican Dollars (JMD).
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-2 max-w-sm">
                    <Label htmlFor="monthly-fee">Monthly Fee (JMD)</Label>
                    <Input id="monthly-fee" type="number" value={newFee} onChange={(e) => setNewFee(Number(e.target.value))} />
                </div>
                 {convertedAmounts && (
                    <div className="text-sm text-muted-foreground space-y-1">
                        <p>Equivalent to:</p>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-1">
                            <span>🇺🇸 {convertedAmounts.usd}</span>
                            <span>🇨🇦 {convertedAmounts.cad}</span>
                            <span>🇬🇧 {convertedAmounts.gbp}</span>
                            <span>🇪🇺 {convertedAmounts.eur}</span>
                        </div>
                    </div>
                )}
            </CardContent>
            <CardFooter>
                 <Button onClick={handleFeeChange}>Update Fee</Button>
            </CardFooter>
        </Card>

       <Card>
        <CardHeader>
          <CardTitle>YTD Collections (2025)</CardTitle>
          <CardDescription>
            A summary of dues collected over the current year, shown in JMD.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ChartContainer config={chartConfig} className="min-h-[200px] w-full">
            <ResponsiveContainer width="100%" height={300}>
                <BarChart data={monthlyCollectionsData} margin={{ top: 20, right: 20, bottom: 20, left: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="month" stroke="hsl(var(--muted-foreground))" fontSize={12} tickLine={false} axisLine={false} />
                    <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `J$${Number(value) / 1000}k`} />
                     <ChartTooltip
                      cursor={false}
                      content={<ChartTooltipContent indicator="dot" formatter={(value) => `JMD ${Number(value).toLocaleString()}`} />}
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
                <TableHead>Amount (JMD)</TableHead>
                <TableHead>Status</TableHead>
                 <TableHead><span className="sr-only">Actions</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {mockTransactions.map((transaction) => (
                <TableRow key={transaction.id}>
                  <TableCell className="font-medium">{transaction.homeowner}</TableCell>
                  <TableCell>{transaction.date}</TableCell>
                  <TableCell>{transaction.amount.toFixed(2)}</TableCell>
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
