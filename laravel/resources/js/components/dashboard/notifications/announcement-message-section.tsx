import type { UseFormReturn } from 'react-hook-form';
import { Button } from '@/components/ui/button';
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sparkles, Wand2, ShieldAlert, Tag, FileText } from 'lucide-react';
import type { NotificationFormValues } from './notification-form-schema';

type AnnouncementMessageSectionProps = {
  form: UseFormReturn<NotificationFormValues>;
  community: string;
  aiEnabled: boolean;
  isGenerating: boolean;
  onGenerate: () => void;
  onLoadExample: () => void;
};

/** What the announcement says: title, category, priority and message, with the AI summarizer beside it. */
export function AnnouncementMessageSection({
  form,
  community,
  aiEnabled,
  isGenerating,
  onGenerate,
  onLoadExample,
}: AnnouncementMessageSectionProps) {
  return (
    <>
      {/* Example Template Banner */}
      <div className="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border bg-muted/30">
        <div className="flex items-center gap-2">
          <FileText className="h-4 w-4 text-primary" />
          <span className="text-xs font-semibold text-foreground">Example Template Available:</span>
          <span className="text-xs text-muted-foreground hidden sm:inline">Administrative notice &bull; Entire community &bull; Scheduled</span>
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onLoadExample}
          className="h-7 text-xs flex items-center gap-1.5 border-primary/30 text-primary hover:bg-primary/10"
        >
          <Sparkles className="h-3 w-3" />
          <span>Load "Community Office Closure"</span>
        </Button>
      </div>

      {/* Metadata: Category & Priority */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <FormField
          control={form.control}
          name="title"
          render={({ field }) => (
            <FormItem className="sm:col-span-2">
              <FormLabel className="font-semibold text-xs">Announcement Title</FormLabel>
              <FormControl>
                <Input placeholder="e.g., Annual Road Maintenance & Gate Update" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="category"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="font-semibold text-xs flex items-center gap-1.5">
                <Tag className="h-3.5 w-3.5 text-primary" />
                <span>Category</span>
              </FormLabel>
              <Select onValueChange={field.onChange} defaultValue={field.value}>
                <FormControl>
                  <SelectTrigger className="h-10 text-xs">
                    <SelectValue placeholder="Select category" />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="general">General Advisory</SelectItem>
                  <SelectItem value="maintenance">Maintenance & Utilities</SelectItem>
                  <SelectItem value="security">Security & Access</SelectItem>
                  <SelectItem value="emergency">Emergency / Alert</SelectItem>
                  <SelectItem value="event">Community Event</SelectItem>
                  <SelectItem value="financial">Financial & Dues</SelectItem>
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="priority"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="font-semibold text-xs flex items-center gap-1.5">
                <ShieldAlert className="h-3.5 w-3.5 text-primary" />
                <span>Priority Level</span>
              </FormLabel>
              <Select onValueChange={field.onChange} defaultValue={field.value}>
                <FormControl>
                  <SelectTrigger className="h-10 text-xs">
                    <SelectValue placeholder="Select priority" />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="low">Low &bull; Information</SelectItem>
                  <SelectItem value="normal">Normal &bull; Standard</SelectItem>
                  <SelectItem value="urgent">Urgent &bull; Action Needed</SelectItem>
                  <SelectItem value="critical">Critical &bull; Safety Impact</SelectItem>
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>

      {/* AI Drafting & Main Message Content */}
      <div className="grid md:grid-cols-2 gap-6 items-start">
        <div className="space-y-4 rounded-xl border p-4 bg-muted/20">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
              <Sparkles className="h-4 w-4 text-primary" />
              AI Document Summarizer
            </h3>
            <Badge variant="outline" className="text-[10px] font-mono">
              {aiEnabled ? 'Gemini AI Ready' : 'Template Mode'}
            </Badge>
          </div>

          <FormField
            control={form.control}
            name="document"
            render={({ field }) => (
              <FormItem>
                <FormControl>
                  <Textarea
                    placeholder="Paste HOA minutes, utility notice, or complex bulletin to summarize..."
                    className="h-28 text-xs font-mono"
                    {...field}
                  />
                </FormControl>
                <FormDescription className="text-xs">
                  Drafted privately on the server for {community}.
                </FormDescription>
              </FormItem>
            )}
          />

          <Button
            type="button"
            onClick={onGenerate}
            disabled={isGenerating}
            variant="secondary"
            className="w-full text-xs h-9 font-medium"
          >
            <Wand2 className="mr-2 h-3.5 w-3.5" />
            {isGenerating ? 'Synthesizing...' : 'Summarize into Bullet Points'}
          </Button>
        </div>

        <FormField
          control={form.control}
          name="content"
          render={({ field }) => (
            <FormItem className="flex flex-col h-full">
              <FormLabel className="font-semibold text-xs">Announcement Message</FormLabel>
              <FormControl>
                <Textarea
                  placeholder="Write your announcement message. Markdown, bullet points, and paragraphs supported."
                  className="min-h-[180px] flex-1 text-xs leading-relaxed"
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
    </>
  );
}
