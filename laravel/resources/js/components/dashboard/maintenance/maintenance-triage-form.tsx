import { useState, type FormEvent } from "react";
import { router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useToast } from "@/hooks/use-toast";
import { ShieldCheck } from "lucide-react";
import type { MaintenanceIssueItem, StaffMember } from "./maintenance-types";

type MaintenanceTriageFormProps = {
  issue: MaintenanceIssueItem;
  staffRoster: StaffMember[];
  onSaved: () => void;
};

/**
 * Staff triage of one issue: status, assignee, priority, resolution summary and a progress
 * note. Starts from the issue's current values; mount it keyed by issue so it resets.
 */
export function MaintenanceTriageForm({ issue, staffRoster, onSaved }: MaintenanceTriageFormProps) {
  const { toast } = useToast();

  const [triageStatus, setTriageStatus] = useState<string>(issue.status);
  const [triageAssignee, setTriageAssignee] = useState<string>(issue.assignedTo ? String(issue.assignedTo.id) : "unassigned");
  const [triagePriority, setTriagePriority] = useState<string>(issue.priority);
  const [triageNote, setTriageNote] = useState("");
  const [triageResolution, setTriageResolution] = useState(issue.resolutionNotes || "");
  const [updatingIssue, setUpdatingIssue] = useState(false);

  const handleTriageSubmit = (e: FormEvent) => {
    e.preventDefault();

    if (triageStatus === "resolved" && !triageResolution.trim() && !issue.resolutionNotes) {
      toast({
        variant: "destructive",
        title: "Resolution Notes Required",
        description: "Please document the resolution notes before resolving this issue.",
      });
      return;
    }

    setUpdatingIssue(true);
    const formData = new FormData();
    formData.append("_method", "PATCH");
    if (triageStatus) formData.append("status", triageStatus);
    if (triagePriority) formData.append("priority", triagePriority);
    if (triageAssignee) {
      formData.append(
        "assigned_to_user_id",
        triageAssignee === "unassigned" ? "" : triageAssignee
      );
    }
    if (triageNote.trim()) formData.append("note", triageNote.trim());
    if (triageResolution.trim()) formData.append("resolution_notes", triageResolution.trim());

    router.post(`/dashboard/maintenance/${issue.id}`, formData, {
      onSuccess: () => {
        onSaved();
        toast({
          title: "Work Order Updated",
          description: "Maintenance status, assignment, and closure notification have been updated.",
        });
      },
      onError: (err) => {
        toast({
          variant: "destructive",
          title: "Update Error",
          description: Object.values(err)[0] as string || "Could not update maintenance issue.",
        });
      },
      onFinish: () => setUpdatingIssue(false),
    });
  };

  return (
    <div className="p-4 rounded-xl border bg-card space-y-4">
      <h4 className="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5">
        <ShieldCheck className="h-4 w-4" />
        Staff Triage & Work Order Management
      </h4>

      <form onSubmit={handleTriageSubmit} className="space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="space-y-1">
            <label className="text-[11px] font-medium text-muted-foreground">
              Update Status
            </label>
            <Select
              value={triageStatus}
              onValueChange={setTriageStatus}
            >
              <SelectTrigger className="h-8 text-xs">
                <SelectValue placeholder="Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="reported">Reported</SelectItem>
                <SelectItem value="triaged">Triaged</SelectItem>
                <SelectItem value="assigned">Assigned</SelectItem>
                <SelectItem value="in_progress">In Progress</SelectItem>
                <SelectItem value="resolved">Resolved</SelectItem>
                <SelectItem value="closed">Closed</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1">
            <label className="text-[11px] font-medium text-muted-foreground">
              Assign to Staff Member
            </label>
            <Select
              value={triageAssignee}
              onValueChange={setTriageAssignee}
            >
              <SelectTrigger className="h-8 text-xs">
                <SelectValue placeholder="Assign Staff" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="unassigned">Unassigned</SelectItem>
                {staffRoster.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>
                    {s.name} ({s.role})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1">
            <label className="text-[11px] font-medium text-muted-foreground">
              Priority
            </label>
            <Select
              value={triagePriority}
              onValueChange={setTriagePriority}
            >
              <SelectTrigger className="h-8 text-xs">
                <SelectValue placeholder="Priority" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="low">Low</SelectItem>
                <SelectItem value="medium">Medium</SelectItem>
                <SelectItem value="high">High</SelectItem>
                <SelectItem value="emergency">Emergency</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Resolution notes if resolved/closed */}
        {(triageStatus === "resolved" || triageStatus === "closed") && (
          <div className="space-y-1 pt-1">
            <label className="text-[11px] font-medium text-foreground flex items-center justify-between">
              <span>Resolution Summary (Dispatched to Resident) *</span>
              <span className="text-[10px] text-muted-foreground">Triggers closure notification</span>
            </label>
            <Textarea
              placeholder="Describe how the issue was fixed (e.g. Pothole filled and rolled by contractor)..."
              value={triageResolution}
              onChange={(e) => setTriageResolution(e.target.value)}
              rows={2}
              required
            />
          </div>
        )}

        {/* Progress note */}
        <div className="space-y-1">
          <label className="text-[11px] font-medium text-muted-foreground">
            Add Progress Note
          </label>
          <Input
            placeholder="e.g. Scheduled for Wednesday inspection..."
            value={triageNote}
            onChange={(e) => setTriageNote(e.target.value)}
            className="h-8 text-xs"
          />
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button
            type="submit"
            size="sm"
            disabled={updatingIssue}
            className="text-xs font-semibold"
          >
            {updatingIssue ? "Updating..." : "Save Triage & Notify Resident"}
          </Button>
        </div>
      </form>
    </div>
  );
}
