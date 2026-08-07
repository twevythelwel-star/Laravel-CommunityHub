
'use client';

import { useState } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { CalendarIcon, CreditCard } from "lucide-react";
import { format } from "date-fns";
import { BillingSummary } from "@/components/dashboard/billing-summary";
import { Checkbox } from "@/components/ui/checkbox";
import { useAuth } from "@/context/auth-context";
import { AdminBilling } from "@/components/dashboard/admin-billing";
import { Calendar } from "@/components/ui/calendar";
import { useBilling } from "@/context/billing-context";


function HomeownerBilling() {
  const [selectedDate, setSelectedDate] = useState<Date | undefined>(new Date());
  const { monthlyFee } = useBilling();

  return (
    <div className="grid gap-8">
       <div>
        <h1 className="font-headline text-3xl font-bold">Billing</h1>
        <p className="text-muted-foreground">Manage your payments and subscriptions.</p>
      </div>

      <BillingSummary />

      <Card>
        <CardHeader>
          <CardTitle>Payment Method</CardTitle>
          <CardDescription>
            Add or update your credit card details for HOA payments.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
            <div className="grid gap-4">
                 <div className="grid gap-2">
                    <Label htmlFor="card-name">Name on Card</Label>
                    <Input id="card-name" placeholder="John Doe" className="max-w-md" />
                </div>
                <div className="grid gap-2">
                  <Label>Secure Payment Details</Label>
                  <div className="flex h-10 w-full rounded-md border border-input bg-background/50 px-3 py-2 text-sm text-muted-foreground items-center gap-2">
                      <span>🔒</span>
                      <span>Card information is securely collected by Stripe (PCI-DSS Level 1 Compliant)</span>
                  </div>
                </div>
            </div>
            <div className="flex items-center gap-4">
            <div className="flex items-center gap-3">
                <div className="flex items-center justify-center p-2 rounded-md border bg-muted/20">
                    <CreditCard className="h-6 w-6 text-muted-foreground" />
                </div>
                <div className="flex items-center justify-center p-2 rounded-md border bg-muted/20">
                    <CreditCard className="h-6 w-6 text-muted-foreground" />
                </div>
                <div className="flex items-center justify-center p-2 rounded-md border bg-muted/20">
                    <CreditCard className="h-6 w-6 text-muted-foreground" />
                </div>
                <div className="flex items-center justify-center p-2 rounded-md border bg-muted/20">
                    <CreditCard className="h-6 w-6 text-muted-foreground" />
                </div>
            </div>
            </div>
            <div className="flex items-center space-x-2">
              <Checkbox id="remember-card" />
              <Label htmlFor="remember-card" className="text-sm font-normal">Remember this card for future payments</Label>
            </div>
            <div className="flex gap-4 pt-2">
              <Button>Pay JMD {monthlyFee.toLocaleString('en-JM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</Button>
              <Button variant="outline">Save Payment Method</Button>
            </div>
        </CardContent>
      </Card>
       <Card>
        <CardHeader>
          <CardTitle>Recurring Payments</CardTitle>
           <CardDescription>
            Set a day of the month to automatically pay your community dues.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
            <div className="grid gap-2 max-w-sm">
                 <Label>Payment Date</Label>
                 <Popover>
                    <PopoverTrigger asChild>
                        <Button variant="outline" className="w-[280px] justify-start text-left font-normal">
                            <CalendarIcon className="mr-2 h-4 w-4" />
                            {selectedDate ? `Pay on the ${format(selectedDate, 'do')} of every month` : "Select a date"}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0">
                        <Calendar
                            mode="single"
                            selected={selectedDate}
                            onSelect={setSelectedDate}
                            initialFocus
                        />
                    </PopoverContent>
                </Popover>
            </div>
          <Button>Set Up Recurring Payment</Button>
        </CardContent>
      </Card>
    </div>
  );
}


export default function BillingPage() {
  const { user } = useAuth();

  if (!user) {
    return <div>Loading...</div>
  }

  if (user.role === 'Admin' || user.role === 'System Admin') {
    return <AdminBilling />;
  }

  return <HomeownerBilling />;
}
