

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
import { useMap } from "@/context/map-context";
import { useState, useEffect } from "react";
import { useToast } from "@/hooks/use-toast";


export default function SettingsPage() {
  const { user } = useAuth();
  const { coordinates, setCoordinates } = useMap();
  const { toast } = useToast();
  const [coordsString, setCoordsString] = useState('');

  useEffect(() => {
    // Format the coordinates from the context into a pretty-printed JSON string for the textarea
    const formattedCoords = coordinates.map(c => ({ latitude: c[0], longitude: c[1] }));
    setCoordsString(JSON.stringify(formattedCoords, null, 2));
  }, [coordinates]);

  const isAdmin = user?.role === 'System Admin' || user?.role === 'Admin';
  
  const handleSaveMapCoords = () => {
    try {
        const parsed = JSON.parse(coordsString);
        if (!Array.isArray(parsed)) throw new Error("Input must be an array.");

        const newCoords = parsed.map(item => {
            if (typeof item.latitude !== 'number' || typeof item.longitude !== 'number') {
                throw new Error("Each coordinate object must have 'latitude' and 'longitude' as numbers.");
            }
            return [item.latitude, item.longitude] as [number, number];
        });

        setCoordinates(newCoords);
        toast({
            title: "Success",
            description: "Map coordinates have been updated successfully.",
        });
    } catch (e) {
        const error = e as Error;
        console.error("Failed to parse map coordinates:", error);
        toast({
            variant: "destructive",
            title: "Invalid JSON",
            description: `Could not save coordinates. ${error.message}`,
        });
    }
  }


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
                <Textarea id="map-coords" className="font-code h-48" value={coordsString} onChange={e => setCoordsString(e.target.value)} />
            </div>
            <Button onClick={handleSaveMapCoords}>Save Map Coordinates</Button>
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
