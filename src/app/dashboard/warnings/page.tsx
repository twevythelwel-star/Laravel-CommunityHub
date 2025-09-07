import { Button } from "@/components/ui/button";
import { PlusCircle, AlertTriangle } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogClose,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { WarningList } from "@/components/dashboard/warning-list";
import type { Warning } from "@/types";

const mockWarnings: Warning[] = [
    {
        id: '1',
        title: 'Suspicious Vehicle Reported',
        description: 'A black sedan with no license plate has been seen circling Lot B. Please be cautious.',
        author: 'Jane Doe',
        timestamp: new Date('2024-07-29T10:00:00Z'),
        confirms: 2,
        denies: 0,
        userStatus: null,
    },
    {
        id: '2',
        title: 'Lost Golden Retriever',
        description: 'Our dog, "Buddy", went missing near the park. He is very friendly and has a blue collar.',
        author: 'John Smith',
        timestamp: new Date('2024-07-29T08:30:00Z'),
        confirms: 5,
        denies: 1,
        userStatus: 'confirmed',
    },
]

export default function WarningsPage() {
  return (
    <div className="flex flex-col gap-8">
      <div className="flex items-center">
        <div className="flex-1">
          <h1 className="font-headline text-3xl font-bold">Community Warnings</h1>
          <p className="text-muted-foreground">Send and validate urgent alerts within the community.</p>
        </div>
        <Dialog>
          <DialogTrigger asChild>
            <Button size="sm" variant="destructive" className="gap-1">
              <PlusCircle className="h-3.5 w-3.5" />
              <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                Send Alert
              </span>
            </Button>
          </DialogTrigger>
          <DialogContent className="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle>Send New Alert</DialogTitle>
              <DialogDescription>
                Describe the issue. Your alert will be sent to all residents and the admin.
              </DailogDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid grid-cols-4 items-center gap-4">
                <Label htmlFor="title" className="text-right">
                  Title
                </Label>
                <Input id="title" placeholder="e.g. Lost Pet" className="col-span-3" />
              </div>
              <div className="grid grid-cols-4 items-start gap-4">
                <Label htmlFor="description" className="text-right pt-2">
                  Description
                </Label>
                <Textarea id="description" placeholder="Provide details here..." className="col-span-3" />
              </div>
            </div>
            <DialogFooter>
                <Dialog>
                    <DialogTrigger asChild>
                        <Button type="submit" variant="destructive">Send Alert</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2"><AlertTriangle className="text-destructive"/>Are you sure?</DialogTitle>
                            <DialogDescription>
                                Sending an alert notifies the entire community. Please confirm you want to proceed to avoid false alarms.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <DialogClose asChild>
                                <Button variant="destructive">Yes, Send Alert</Button>
                            </DialogClose>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
      
      <WarningList initialWarnings={mockWarnings} />

    </div>
  );
}
