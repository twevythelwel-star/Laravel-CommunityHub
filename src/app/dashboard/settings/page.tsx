

'use client';

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
import { useAuth } from "@/context/auth-context";
import { ThemeCustomizer } from "@/components/dashboard/theme-customizer";
import { Textarea } from "@/components/ui/textarea";
import { BrandingSettings } from "@/components/dashboard/branding-settings";

const defaultCoords = JSON.stringify([
  { "longitude": -77.9278, "latitude": 18.4781 },
  { "longitude": -77.9239, "latitude": 18.4783 },
  { "longitude": -77.9236, "latitude": 18.4752 },
  { "longitude": -77.9276, "latitude": 18.4750 },
], null, 2);


export default function SettingsPage() {
  const { user } = useAuth();
  const isAdmin = user?.role === 'System Admin' || user?.role === 'Admin';


  return (
    <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">Settings</h1>
            <p className="text-muted-foreground">Manage your account and application preferences.</p>
        </div>
      
      {user?.role === 'System Admin' && (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>Branding</CardTitle>
                    <CardDescription>
                    Customize the name and logo of your community application.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <BrandingSettings />
                </CardContent>
            </Card>
            <Card>
            <CardHeader>
                <CardTitle>Theme Customization</CardTitle>
                <CardDescription>
                As a System Admin, you can customize the application's appearance.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ThemeCustomizer />
            </CardContent>
            </Card>
        </>
      )}

      {isAdmin && (
         <Card>
          <CardHeader>
            <CardTitle>Map Configuration</CardTitle>
            <CardDescription>
              Define the community boundaries by providing polygon coordinates.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="map-coords">Polygon Coordinates (JSON format)</Label>
                <Textarea id="map-coords" className="font-code h-48" defaultValue={defaultCoords} />
            </div>
            <Button>Save Map Coordinates</Button>
          </CardContent>
        </Card>
      )}


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
