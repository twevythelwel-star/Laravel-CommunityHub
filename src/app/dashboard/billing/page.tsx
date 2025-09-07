import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";

export default function BillingPage() {
  return (
    <div className="grid gap-8">
       <div>
        <h1 className="font-headline text-3xl font-bold">Billing</h1>
        <p className="text-muted-foreground">Manage your payments and subscriptions.</p>
      </div>
      <Card>
        <CardHeader>
          <CardTitle>Payment Method</CardTitle>
          <CardDescription>
            This feature is under development.
          </CardDescription>
        </CardHeader>
        <CardContent>
           <p>A section to manage payment methods and view billing history will be available here.</p>
        </CardContent>
      </Card>
       <Card>
        <CardHeader>
          <CardTitle>Recurring Payments</CardTitle>
           <CardDescription>
            This feature is under development.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p>A section to set up recurring payments will be available here.</p>
        </CardContent>
      </Card>
    </div>
  );
}
