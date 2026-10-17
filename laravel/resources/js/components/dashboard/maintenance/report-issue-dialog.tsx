import { useState, type ChangeEvent, type FormEvent } from "react";
import { router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useToast } from "@/hooks/use-toast";
import { Camera, MapPin, UploadCloud, Wrench, X } from "lucide-react";
import { CATEGORIES, type MaintenanceCategory, type MaintenancePriority } from "./maintenance-types";

type ReportIssueDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

/** A resident reports an issue: category, title, priority, location, description and up to five photos. */
export function ReportIssueDialog({ open, onOpenChange }: ReportIssueDialogProps) {
  const { toast } = useToast();

  const [reportCategory, setReportCategory] = useState<MaintenanceCategory>("potholes");
  const [reportTitle, setReportTitle] = useState("");
  const [reportDescription, setReportDescription] = useState("");
  const [reportLocation, setReportLocation] = useState("");
  const [reportPriority, setReportPriority] = useState<MaintenancePriority>("medium");
  const [reportFiles, setReportFiles] = useState<File[]>([]);
  const [submittingReport, setSubmittingReport] = useState(false);

  const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
    if (e.target.files) {
      const selected = Array.from(e.target.files);
      if (reportFiles.length + selected.length > 5) {
        toast({
          variant: "destructive",
          title: "Attachment Limit",
          description: "You can attach a maximum of 5 photos.",
        });
        return;
      }
      setReportFiles((prev) => [...prev, ...selected]);
    }
  };

  const removeFile = (index: number) => {
    setReportFiles((prev) => prev.filter((_, i) => i !== index));
  };

  const handleReportSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (!reportTitle.trim() || !reportDescription.trim() || !reportLocation.trim()) {
      toast({
        variant: "destructive",
        title: "Required Fields",
        description: "Please fill out the title, description, and location.",
      });
      return;
    }

    setSubmittingReport(true);
    const formData = new FormData();
    formData.append("category", reportCategory);
    formData.append("title", reportTitle.trim());
    formData.append("description", reportDescription.trim());
    formData.append("location", reportLocation.trim());
    formData.append("priority", reportPriority);

    reportFiles.forEach((file) => {
      formData.append("photos[]", file);
    });

    router.post("/dashboard/maintenance", formData, {
      onSuccess: () => {
        onOpenChange(false);
        setReportTitle("");
        setReportDescription("");
        setReportLocation("");
        setReportPriority("medium");
        setReportFiles([]);
        toast({
          title: "Issue Logged",
          description: "Your report has been received and prioritized for maintenance.",
        });
      },
      onError: (err) => {
        toast({
          variant: "destructive",
          title: "Submission Error",
          description: Object.values(err)[0] as string || "Could not log maintenance issue.",
        });
      },
      onFinish: () => setSubmittingReport(false),
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-xl font-bold">
            <Wrench className="h-5 w-5 text-primary" />
            Report Maintenance Issue
          </DialogTitle>
          <DialogDescription>
            Provide details and photos so our community operations team can inspect and dispatch repair crews.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleReportSubmit} className="space-y-4 pt-2">
          {/* Category selection */}
          <div className="space-y-2">
            <label className="text-xs font-semibold text-foreground uppercase tracking-wider">
              Issue Category *
            </label>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
              {CATEGORIES.map((cat) => {
                const CatIcon = cat.icon;
                const isSelected = reportCategory === cat.id;

                return (
                  <button
                    key={cat.id}
                    type="button"
                    onClick={() => setReportCategory(cat.id)}
                    className={`p-3 rounded-xl border text-left flex flex-col gap-1.5 transition-all ${
                      isSelected
                        ? "border-primary ring-2 ring-primary/20 bg-primary/5 font-semibold"
                        : "border-border hover:border-border/80 hover:bg-muted/30"
                    }`}
                  >
                    <div className={`p-1.5 rounded-lg w-fit ${cat.color}`}>
                      <CatIcon className="h-4 w-4" />
                    </div>
                    <span className="text-xs">{cat.label}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Title & Priority */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div className="sm:col-span-2 space-y-1">
              <label className="text-xs font-semibold text-foreground uppercase tracking-wider">
                Summary / Title *
              </label>
              <Input
                placeholder="e.g. Deep pothole near North Gate exit"
                value={reportTitle}
                onChange={(e) => setReportTitle(e.target.value)}
                required
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-foreground uppercase tracking-wider">
                Urgency / Priority *
              </label>
              <Select
                value={reportPriority}
                onValueChange={(val: MaintenancePriority) => setReportPriority(val)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Priority" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="low">Low (Routine)</SelectItem>
                  <SelectItem value="medium">Medium (Normal)</SelectItem>
                  <SelectItem value="high">High (Urgent)</SelectItem>
                  <SelectItem value="emergency">Emergency Hazard</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {/* Location */}
          <div className="space-y-1">
            <label className="text-xs font-semibold text-foreground uppercase tracking-wider">
              Location in Community *
            </label>
            <div className="relative">
              <MapPin className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="e.g. Corner of Palm Grove Way & Block B, near pole #14"
                value={reportLocation}
                onChange={(e) => setReportLocation(e.target.value)}
                className="pl-9"
                required
              />
            </div>
          </div>

          {/* Description */}
          <div className="space-y-1">
            <label className="text-xs font-semibold text-foreground uppercase tracking-wider">
              Detailed Description *
            </label>
            <Textarea
              placeholder="Describe the issue, dimensions of damage, safety hazards, or any details to assist repair teams..."
              value={reportDescription}
              onChange={(e) => setReportDescription(e.target.value)}
              rows={3}
              required
            />
          </div>

          {/* Photo attachments */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <label className="text-xs font-semibold text-foreground uppercase tracking-wider">
                Photo Attachments (Optional, max 5)
              </label>
              <span className="text-[11px] text-muted-foreground">
                {reportFiles.length}/5 photos attached
              </span>
            </div>

            <div className="border-2 border-dashed border-border rounded-xl p-4 text-center hover:bg-muted/20 transition-colors relative">
              <input
                type="file"
                multiple
                accept="image/*"
                onChange={handleFileChange}
                className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                disabled={reportFiles.length >= 5}
              />
              <div className="flex flex-col items-center justify-center gap-1.5 pointer-events-none">
                <UploadCloud className="h-6 w-6 text-muted-foreground" />
                <p className="text-xs font-medium">Click or drag photos to upload</p>
                <p className="text-[10px] text-muted-foreground">JPG, PNG, or WebP up to 10MB each</p>
              </div>
            </div>

            {reportFiles.length > 0 && (
              <div className="flex flex-wrap gap-2 pt-1">
                {reportFiles.map((file, idx) => (
                  <div
                    key={idx}
                    className="relative group bg-muted rounded-lg p-1.5 flex items-center gap-2 border text-xs"
                  >
                    <Camera className="h-3.5 w-3.5 text-muted-foreground" />
                    <span className="truncate max-w-[120px] font-medium">{file.name}</span>
                    <button
                      type="button"
                      onClick={() => removeFile(idx)}
                      className="p-1 rounded-full hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors"
                    >
                      <X className="h-3 w-3" />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>

          <DialogFooter className="pt-4 border-t">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={submittingReport}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={submittingReport}>
              {submittingReport ? "Logging Report..." : "Submit Report"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
