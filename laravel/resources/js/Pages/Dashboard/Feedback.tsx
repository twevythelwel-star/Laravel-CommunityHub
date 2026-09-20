

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Button } from "@/components/ui/button";

export default function FeedbackPage() {
  return (
    <div className="grid gap-8">
      <div>
        <h1 className="font-headline text-3xl font-bold">Submit Feedback</h1>
        <p className="text-muted-foreground">Report an issue or make a suggestion to improve the app.</p>
      </div>
      <Card>
        <CardHeader>
          <CardTitle>Feedback Form</CardTitle>
          <CardDescription>
            Your feedback is valuable to us. Please fill out the form below.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="grid gap-2">
            <Label htmlFor="subject">Subject</Label>
            <Input id="subject" placeholder="e.g., App crashes on login" />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="description">Description</Label>
            <Textarea id="description" placeholder="Please describe the issue or suggestion in detail." />
          </div>
           <div className="grid gap-2">
            <Label>Feedback Type</Label>
            <RadioGroup defaultValue="issue" className="flex gap-4">
                <div className="flex items-center space-x-2">
                    <RadioGroupItem value="issue" id="issue" />
                    <Label htmlFor="issue">Report an Issue</Label>
                </div>
                <div className="flex items-center space-x-2">
                    <RadioGroupItem value="suggestion" id="suggestion" />
                    <Label htmlFor="suggestion">Make a Suggestion</Label>
                </div>
            </RadioGroup>
          </div>
          <Button>Submit Feedback</Button>
        </CardContent>
      </Card>
    </div>
  );
}
