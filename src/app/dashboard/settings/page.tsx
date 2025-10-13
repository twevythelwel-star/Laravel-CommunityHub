

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
import { BrandingSettings } from "@/components/dashboard/branding-settings";
import { useMap } from "@/context/map-context";
import { useState, useEffect } from "react";
import { useToast } from "@/hooks/use-toast";
import { Input } from "@/components/ui/input";
import { Trash2 } from "lucide-react";


export default function SettingsPage() {
  const { user } = useAuth();
  const { coordinates, setCoordinates } = useMap();
  const { toast } = useToast();
  const [localCoords, setLocalCoords] = useState<[number, number][]>([]);

  useEffect(() => {
    setLocalCoords(coordinates);
  }, [coordinates]);

  const handleCoordChange = (index: number, position: 'lat' | 'lon', value: string) => {
    const newCoords = [...localCoords];
    const numValue = parseFloat(value);
    if (!isNaN(numValue)) {
        newCoords[index] = position === 'lat'
            ? [numValue, newCoords[index][1]]
            : [newCoords[index][0], numValue];
        setLocalCoords(newCoords);
    }
  };

  const addCoordinate = () => {
    // Add a new coordinate pair, defaulting to the last one or a base value
    const lastCoord = localCoords[localCoords.length - 1] || [18.47, -77.92];
    setLocalCoords([...localCoords, [lastCoord[0] + 0.0001, lastCoord[1] + 0.0001]]);
  };

  const removeCoordinate = (index: number) => {
    if (localCoords.length <= 3) {
        toast({
            variant: "destructive",
            title: "Minimum Coordinates",
            description: "A polygon must have at least 3 points.",
        });
        return;
    }
    const newCoords = localCoords.filter((_, i) => i !== index);
    setLocalCoords(newCoords);
  };

  const handleSaveMapCoords = () => {
    // Basic validation
    if (localCoords.some(c => isNaN(c[0]) || isNaN(c[1]))) {
        toast({
            variant: "destructive",
            title: "Invalid Input",
            description: "All latitude and longitude values must be valid numbers.",
        });
        return;
    }
    if (localCoords.length < 3) {
         toast({
            variant: "destructive",
            title: "Invalid Shape",
            description: "A polygon requires at least 3 coordinate points.",
        });
        return;
    }

    setCoordinates(localCoords);
    toast({
        title: "Success",
        description: "Map coordinates have been updated successfully.",
    });
  }

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
              Define the community boundaries by providing an ordered list of polygon coordinates.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
             <div className="space-y-4">
                {localCoords.map((coord, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <Label className="w-10 text-center text-muted-foreground">{index + 1}.</Label>
                        <div className="grid flex-1 grid-cols-2 gap-2">
                            <Input 
                                type="number" 
                                placeholder="Latitude" 
                                value={coord[0]}
                                onChange={(e) => handleCoordChange(index, 'lat', e.target.value)}
                                step="any"
                            />
                            <Input 
                                type="number" 
                                placeholder="Longitude" 
                                value={coord[1]}
                                onChange={(e) => handleCoordChange(index, 'lon', e.target.value)}
                                step="any"
                            />
                        </div>
                        <Button variant="ghost" size="icon" onClick={() => removeCoordinate(index)} aria-label="Remove coordinate">
                            <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                ))}
             </div>
             <div className="flex gap-2 pt-2">
                <Button onClick={handleSaveMapCoords}>Save Map Coordinates</Button>
                <Button variant="outline" onClick={addCoordinate}>Add Coordinate</Button>
             </div>
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
