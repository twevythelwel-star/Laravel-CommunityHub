
"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Button } from "@/components/ui/button";
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
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
  } from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { AlertTriangle, PlusCircle } from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { useState } from "react";

const warningFormSchema = z.object({
  title: z.string().min(5, "Title must be at least 5 characters long."),
  description: z.string().min(10, "Description must be at least 10 characters long."),
});

export function WarningForm() {
    const { toast } = useToast();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);


    const form = useForm<z.infer<typeof warningFormSchema>>({
        resolver: zodResolver(warningFormSchema),
        defaultValues: {
          title: "",
          description: "",
        },
    });

    function onSubmit(values: z.infer<typeof warningFormSchema>) {
        console.log("Submitting warning", values);
        setIsConfirmOpen(false);
        setIsDialogOpen(false);
        toast({
          title: "Alert Sent",
          description: "Your community warning has been successfully submitted.",
        });
        form.reset();
    }

    return (
         <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button size="sm" variant="destructive" className="gap-1">
              <PlusCircle className="h-3.5 w-3.5" />
              <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                Send Alert
              </span>
            </Button>
          </DialogTrigger>
          <DialogContent className="sm:max-w-[425px]">
            <Form {...form}>
                 <form onSubmit={form.handleSubmit(() => setIsConfirmOpen(true))} className="space-y-4">
                    <DialogHeader>
                    <DialogTitle>Send New Alert</DialogTitle>
                    <DialogDescription>
                        Describe the issue. Your alert will be sent to all residents and the admin.
                    </DialogDescription>
                    </DialogHeader>
                    
                    <FormField
                    control={form.control}
                    name="title"
                    render={({ field }) => (
                        <FormItem>
                        <FormLabel>Title</FormLabel>
                        <FormControl>
                            <Input placeholder="e.g. Lost Pet" {...field} />
                        </FormControl>
                        <FormMessage />
                        </FormItem>
                    )}
                    />
                    <FormField
                    control={form.control}
                    name="description"
                    render={({ field }) => (
                        <FormItem>
                        <FormLabel>Description</FormLabel>
                        <FormControl>
                            <Textarea placeholder="Provide details here..." {...field} />
                        </FormControl>
                        <FormMessage />
                        </FormItem>
                    )}
                    />

                    <DialogFooter>
                        <Dialog open={isConfirmOpen} onOpenChange={setIsConfirmOpen}>
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
                                    <Button variant="outline" onClick={() => setIsConfirmOpen(false)}>Cancel</Button>
                                    <Button variant="destructive" onClick={() => onSubmit(form.getValues())} >Yes, Send Alert</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </DialogFooter>
                </form>
            </Form>
          </DialogContent>
        </Dialog>
    )
}
