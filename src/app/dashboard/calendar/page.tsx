import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Calendar } from "@/components/ui/calendar";

export default function CalendarPage() {
  return (
    <div>
      <h1 className="font-headline text-3xl font-bold">Community Calendar</h1>
      <p className="text-muted-foreground">View community events and available visitor timeslots.</p>
      <Card className="mt-8">
        <CardHeader>
          <CardTitle>Events and Bookings</CardTitle>
          <CardDescription>
            Select a date to see events and availability.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex justify-center">
           <Calendar
            mode="single"
            className="rounded-md border"
          />
        </CardContent>
      </Card>
    </div>
  );
}
