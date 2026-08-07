"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { generateTargetedNotifications } from "@/ai/flows/generate-targeted-notifications";
import { Button } from "@/components/ui/button";
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
import { Card, CardContent } from "@/components/ui/card";
import { useToast } from "@/hooks/use-toast";
import { Sparkles, Wand2 } from "lucide-react";
import { Separator } from "@/components/ui/separator";
import { AIConsentDialog } from "@/components/ai-consent-dialog";

const formSchema = z.object({
  title: z.string().min(1, "Title is required"),
  document: z.string().describe("The document/long text for AI summary."),
  community: z.string().describe("The target community for AI generation."),
  finalContent: z.string().min(1, "Notification content is required"),
});

export function NotificationForm() {
  const [isLoading, setIsLoading] = useState(false);
  const [showConsent, setShowConsent] = useState(false);
  const [consentGranted, setConsentGranted] = useState(false);
  const { toast } = useToast();

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      title: "",
      document: "",
      community: "Green Meadows",
      finalContent: "",
    },
  });

  async function handleGenerate() {
    if (!consentGranted) {
      setShowConsent(true);
      return;
    }
    await executeGenerate();
  }

  async function executeGenerate() {
    setIsLoading(true);
    try {
      const { document, community } = form.getValues();
      if (!document || !community) {
        toast({
            variant: "destructive",
            title: "Error",
            description: "Document content and community are required to generate notifications.",
        });
        return;
      }
      const result = await generateTargetedNotifications({ document, community });
      const generatedText = result.notificationItems.join("\n- ");
      form.setValue("finalContent", `- ${generatedText}`);
      toast({
        title: "Success",
        description: "Generated notification items and populated the content field.",
      });
    } catch (error) {
      console.error(error);
      toast({
        variant: "destructive",
        title: "Generation Failed",
        description: "Could not generate notification items. Please try again.",
      });
    } finally {
      setIsLoading(false);
    }
  }

  function onSubmit(values: z.infer<typeof formSchema>) {
    console.log(values);
    toast({
      title: "Notification Sent",
      description: `Notification "${values.title}" has been sent.`,
    });
    form.reset();
  }

  return (
    <>
      {showConsent && (
        <AIConsentDialog
          onAccept={() => {
            setConsentGranted(true);
            setShowConsent(false);
            executeGenerate();
          }}
          onDecline={() => setShowConsent(false)}
        />
      )}
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
        <FormField
          control={form.control}
          name="title"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Notification Title</FormLabel>
              <FormControl>
                <Input placeholder="e.g., Annual Maintenance Schedule" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        
        <div className="grid md:grid-cols-2 gap-6 items-start">
            <div className="space-y-4">
                 <h3 className="text-sm font-medium text-muted-foreground">AI Content Generation (Optional)</h3>
                <FormField
                control={form.control}
                name="document"
                render={({ field }) => (
                    <FormItem>
                    <FormLabel>Document to Summarize</FormLabel>
                    <FormControl>
                        <Textarea
                        placeholder="Paste your long document or announcement text here..."
                        className="h-24"
                        {...field}
                        />
                    </FormControl>
                    </FormItem>
                )}
                />
                 <FormField
                    control={form.control}
                    name="community"
                    render={({ field }) => (
                        <FormItem>
                        <FormLabel>Target Community</FormLabel>
                        <FormControl>
                            <Input placeholder="e.g., Green Meadows" {...field} />
                        </FormControl>
                        </FormItem>
                    )}
                />
                <Button type="button" onClick={handleGenerate} disabled={isLoading} className="w-full">
                  <Wand2 className="mr-2 h-4 w-4" />
                  {isLoading ? "Generating..." : "Generate & Populate Content"}
                </Button>
                <p className="text-xs text-muted-foreground flex items-center gap-1">
                  <Sparkles className="h-3 w-3" /> Powered by Google Gemini
                </p>
            </div>
             <FormField
                control={form.control}
                name="finalContent"
                render={({ field }) => (
                    <FormItem>
                    <FormLabel>Final Notification Content</FormLabel>
                    <FormControl>
                        <Textarea
                        placeholder="Write your notification content here, or generate it with AI."
                        className="min-h-[190px]"
                        {...field}
                        />
                    </FormControl>
                    <FormMessage />
                    </FormItem>
                )}
                />
        </div>
        
        <Separator />

        <Button type="submit">Send Notification</Button>
      </form>
    </Form>
    </>
  );
}
