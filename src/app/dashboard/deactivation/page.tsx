
'use client';

import { useState, useEffect } from 'react';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  CardFooter,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { useToast } from '@/hooks/use-toast';
import { add, differenceInSeconds, format } from 'date-fns';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Timer } from 'lucide-react';
import { useAuth } from '@/context/auth-context';


function Countdown({ targetDate }: { targetDate: Date }) {
  const [timeLeft, setTimeLeft] = useState(0);

  useEffect(() => {
    const calculateTimeLeft = () => differenceInSeconds(targetDate, new Date());
    setTimeLeft(calculateTimeLeft());

    const interval = setInterval(() => {
      const seconds = calculateTimeLeft();
      if (seconds > 0) {
        setTimeLeft(seconds);
      } else {
        setTimeLeft(0);
        clearInterval(interval);
      }
    }, 1000);

    return () => clearInterval(interval);
  }, [targetDate]);

  const minutes = Math.floor(timeLeft / 60);
  const seconds = timeLeft % 60;

  return (
    <div className="font-mono text-lg text-destructive font-bold">
      {String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}
    </div>
  );
}


export default function DeactivationPage() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [deactivationDate, setDeactivationDate] = useState<Date | null>(null);
  const [dateString, setDateString] = useState('');
  const [timeString, setTimeString] = useState('');
  const [showCountdown, setShowCountdown] = useState(false);
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  useEffect(() => {
    if (!isClient || !deactivationDate) return;

    const interval = setInterval(() => {
        const now = new Date();
        const fifteenMinutes = 15 * 60;
        const diff = differenceInSeconds(deactivationDate, now);
        
        setShowCountdown(diff <= fifteenMinutes && diff > 0);

        if (diff <= 0) {
            // In a real app, this would trigger the actual deactivation
            toast({ title: "Account Deactivated", description: "Your account has been deactivated."});
            setDeactivationDate(null);
            setShowCountdown(false);
            clearInterval(interval);
        }
    }, 1000);

    return () => clearInterval(interval);
    
  }, [isClient, deactivationDate, toast]);


  const handleSchedule = () => {
    const combinedDateTimeString = `${dateString}T${timeString}:00`;
    const scheduledDate = new Date(combinedDateTimeString);
    const now = new Date();

    if (isNaN(scheduledDate.getTime()) || scheduledDate <= now) {
      toast({
        variant: 'destructive',
        title: 'Invalid Date/Time',
        description: 'Please select a valid date and time in the future.',
      });
      return;
    }

    setDeactivationDate(scheduledDate);
    toast({
      title: 'Deactivation Scheduled',
      description: `Your account is scheduled for deactivation on ${format(scheduledDate, 'PPP p')}.`,
    });
  };

  const handleCancel = () => {
    setDeactivationDate(null);
    setShowCountdown(false);
    setDateString('');
    setTimeString('');
    toast({
      title: 'Deactivation Canceled',
      description: "Your account's deactivation request has been canceled.",
    });
  }
  
  if (!isClient) {
    return null;
  }

  if (user?.role !== 'Homeowner') {
     return (
        <Card>
            <CardHeader>
                <CardTitle>Access Denied</CardTitle>
            </CardHeader>
            <CardContent>
                <p>This feature is only available for Homeowners.</p>
            </CardContent>
        </Card>
     )
  }

  return (
    <div className="grid gap-8">
      <div>
        <h1 className="font-headline text-3xl font-bold">Account Deactivation</h1>
        <p className="text-muted-foreground">Request to deactivate your account on a future date.</p>
      </div>

       {showCountdown && deactivationDate && (
        <Alert variant="destructive">
            <Timer className="h-4 w-4" />
          <AlertTitle className="flex justify-between items-center">
            <span>Deactivation Pending</span>
             <Countdown targetDate={deactivationDate} />
          </AlertTitle>
          <AlertDescription>
            Your account will be deactivated soon. You will be logged out automatically.
          </AlertDescription>
        </Alert>
      )}


      {deactivationDate ? (
        <Card>
            <CardHeader>
                <CardTitle>Deactivation Scheduled</CardTitle>
                <CardDescription>
                    Your account deactivation is currently scheduled. You can cancel it at any time before the scheduled date.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-lg font-semibold">Your account will be deactivated on:</p>
                <p className="text-xl font-bold text-primary">{format(deactivationDate, 'EEEE, MMMM d, yyyy \'at\' p')}</p>
                <Alert className="mt-4">
                    <AlertTitle>Reminders</AlertTitle>
                    <AlertDescription>
                        You will receive an email and text message reminder 1 hour and 15 minutes before your account is deactivated.
                    </AlertDescription>
                </Alert>
            </CardContent>
            <CardFooter>
                 <Button onClick={handleCancel} variant="outline">Cancel Deactivation</Button>
            </CardFooter>
        </Card>
      ) : (
        <Card>
            <CardHeader>
            <CardTitle>Schedule Deactivation</CardTitle>
            <CardDescription>
                Select a future date and time to deactivate your account. You will be automatically logged out and your access will be revoked at the specified time.
            </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid md:grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="deactivation-date">Date</Label>
                        <Input 
                            id="deactivation-date" 
                            type="date" 
                            min={format(add(new Date(), { days: 1 }), 'yyyy-MM-dd')}
                            value={dateString}
                            onChange={(e) => setDateString(e.target.value)}
                        />
                    </div>
                     <div className="grid gap-2">
                        <Label htmlFor="deactivation-time">Time</Label>
                        <Input 
                            id="deactivation-time" 
                            type="time" 
                            value={timeString}
                            onChange={(e) => setTimeString(e.target.value)}
                        />
                    </div>
                </div>
                <Button onClick={handleSchedule}>Schedule Deactivation</Button>
            </CardContent>
        </Card>
      )}
    </div>
  );
}
