import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";

export default function SettingsPage() {
  return (
    <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">Settings</h1>
            <p className="text-muted-foreground">Manage your account and notification preferences.</p>
        </div>
      <Card>
        <CardHeader>
          <CardTitle>Notification Settings</CardTitle>
          <CardDescription>
            Choose how you want to be notified.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
            <div className="flex items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                    <Label htmlFor="email-notifications" className="text-base">Email Notifications</Label>
                    <p className="text-sm text-muted-foreground">Receive updates and alerts via email.</p>
                </div>
                <Switch id="email-notifications" defaultChecked/>
            </div>
             <div className="flex items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                    <Label htmlFor="push-notifications" className="text-base">Push Notifications</Label>
                    <p className="text-sm text-muted-foreground">Get instant alerts on your mobile device.</p>
                </div>
                <Switch id="push-notifications" />
            </div>
             <div className="flex items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                    <Label htmlFor="sms-notifications" className="text-base">SMS Notifications</Label>
                    <p className="text-sm text-muted-foreground">Receive critical alerts via text message.</p>
                </div>
                <Switch id="sms-notifications" />
            </div>
            <Button>Save Preferences</Button>
        </CardContent>
      </Card>
    </div>
  );
}
