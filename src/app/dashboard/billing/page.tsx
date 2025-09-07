
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { CalendarIcon, Info } from "lucide-react";
import { format } from "date-fns";
import { BillingSummary } from "@/components/dashboard/billing-summary";
import { Checkbox } from "@/components/ui/checkbox";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";


export default function BillingPage() {
  const [selectedDate, setSelectedDate] = useState<Date | undefined>(new Date());
  
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
            <div className="grid gap-4 md:grid-cols-2">
                 <div className="grid gap-2">
                    <Label htmlFor="card-name">Name on Card</Label>
                    <Input id="card-name" placeholder="John Doe" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="card-number">Card Number</Label>
                    <Input id="card-number" placeholder="**** **** **** 1234" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="expiry">Expiry Date</Label>
                    <Input id="expiry" placeholder="MM/YY" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="cvc">CVC</Label>
                    <Input id="cvc" placeholder="123" />
                </div>
            </div>
            <div className="flex items-center gap-4">
                <img src="https://picsum.photos/40/25?v=visa" alt="Visa" className="rounded-sm" data-ai-hint="credit card" />
                <img src="https://picsum.photos/40/25?v=mastercard" alt="Mastercard" className="rounded-sm" data-ai-hint="credit card" />
                <img src="https://picsum.photos/40/25?v=amex" alt="American Express" className="rounded-sm" data-ai-hint="credit card" />
                <img src="https://picsum.photos/40/25?v=discover" alt="Discover" className="rounded-sm" data-ai-hint="credit card" />
            </div>
            <div className="flex items-center space-x-2">
              <Checkbox id="remember-card" />
              <Label htmlFor="remember-card" className="text-sm font-normal">Remember this card for future payments</Label>
            </div>
            <Button>Save Payment Method</Button>
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
