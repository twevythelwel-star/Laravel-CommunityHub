import { Badge } from "@/components/ui/badge";
import { AlertTriangle } from "lucide-react";
import type { MaintenancePriority, MaintenanceStatus } from "./maintenance-types";

export function StatusBadge({ status }: { status: MaintenanceStatus }) {
  switch (status) {
    case "reported":
      return <Badge variant="outline" className="border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300">Reported</Badge>;
    case "triaged":
      return <Badge variant="secondary" className="bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/20">Triaged</Badge>;
    case "assigned":
      return <Badge variant="secondary" className="bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/20">Assigned</Badge>;
    case "in_progress":
      return <Badge variant="secondary" className="bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/20 animate-pulse">In Progress</Badge>;
    case "resolved":
      return <Badge variant="secondary" className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20">Resolved</Badge>;
    case "closed":
      return <Badge variant="outline" className="bg-muted text-muted-foreground">Closed</Badge>;
    case "cancelled":
      return <Badge variant="outline" className="text-muted-foreground line-through">Cancelled</Badge>;
    default:
      return <Badge variant="outline">{status}</Badge>;
  }
}

export function PriorityBadge({ priority }: { priority: MaintenancePriority }) {
  switch (priority) {
    case "emergency":
      return (
        <Badge className="bg-destructive text-destructive-foreground font-semibold animate-pulse shadow-sm flex items-center gap-1">
          <AlertTriangle className="h-3 w-3" /> Emergency Hazard
        </Badge>
      );
    case "high":
      return (
        <Badge className="bg-orange-500/15 text-orange-700 dark:text-orange-400 border border-orange-500/30">
          High Priority
        </Badge>
      );
    case "medium":
      return (
        <Badge variant="outline" className="text-slate-600 dark:text-slate-300">
          Medium Priority
        </Badge>
      );
    case "low":
      return (
        <Badge variant="outline" className="text-muted-foreground text-xs">
          Low / Routine
        </Badge>
      );
    default:
      return null;
  }
}
