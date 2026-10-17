import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ClientFormattedDistanceToNow } from "@/components/client-formatted-date";
import { Camera, ChevronRight, MapPin, Wrench } from "lucide-react";
import { PriorityBadge, StatusBadge } from "./maintenance-badges";
import { categoryMeta, type MaintenanceIssueItem } from "./maintenance-types";

type MaintenanceIssueListProps = {
  issues: MaintenanceIssueItem[];
  onOpenIssue: (issue: MaintenanceIssueItem) => void;
  onReportIssue: () => void;
};

/** The issue cards, or an empty state inviting a report. */
export function MaintenanceIssueList({ issues, onOpenIssue, onReportIssue }: MaintenanceIssueListProps) {
  if (issues.length === 0) {
    return (
      <Card className="border border-dashed p-12 text-center">
        <div className="flex flex-col items-center justify-center space-y-3">
          <div className="p-3 rounded-full bg-muted text-muted-foreground">
            <Wrench className="h-8 w-8 stroke-[1.5]" />
          </div>
          <h3 className="font-semibold text-base">No maintenance issues found</h3>
          <p className="text-sm text-muted-foreground max-w-sm">
            No reports match your current filter settings. You can report a new issue or clear filters to see all community work orders.
          </p>
          <Button onClick={onReportIssue} size="sm" className="mt-2">
            Report Issue
          </Button>
        </div>
      </Card>
    );
  }

  return (
    <div className="grid gap-3">
      {issues.map((issue) => {
        const meta = categoryMeta(issue.category);
        const CategoryIcon = meta.icon;

        return (
          <Card
            key={issue.id}
            onClick={() => onOpenIssue(issue)}
            className="border transition-all duration-200 hover:border-primary/40 hover:shadow-md cursor-pointer group"
          >
            <CardContent className="p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div className="flex items-start gap-3.5">
                <div className={`p-2.5 rounded-xl border shrink-0 mt-0.5 ${meta.color}`}>
                  <CategoryIcon className="h-5 w-5" />
                </div>

                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-mono text-xs font-bold text-muted-foreground bg-muted px-2 py-0.5 rounded-md">
                      {issue.referenceNumber}
                    </span>
                    <span className="text-xs font-semibold text-foreground/80">
                      {meta.label}
                    </span>
                    <PriorityBadge priority={issue.priority} />
                    <StatusBadge status={issue.status} />
                  </div>

                  <h3 className="text-base font-semibold group-hover:text-primary transition-colors">
                    {issue.title}
                  </h3>

                  <p className="text-xs text-muted-foreground line-clamp-2 max-w-2xl leading-relaxed">
                    {issue.description}
                  </p>

                  <div className="flex flex-wrap items-center gap-3 pt-1 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1 font-medium text-foreground/70">
                      <MapPin className="h-3.5 w-3.5 text-muted-foreground" />
                      {issue.location}
                    </span>
                    <span>•</span>
                    <span>
                      Reported by {issue.reporter.name}
                      {issue.reporter.lot ? ` (${issue.reporter.lot})` : ""}
                    </span>
                    <span>•</span>
                    <ClientFormattedDistanceToNow date={issue.createdAt} />

                    {issue.photos.length > 0 && (
                      <>
                        <span>•</span>
                        <span className="flex items-center gap-1 text-primary">
                          <Camera className="h-3.5 w-3.5" />
                          {issue.photos.length} photo{issue.photos.length > 1 ? "s" : ""}
                        </span>
                      </>
                    )}
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between md:justify-end gap-3 shrink-0 pt-3 md:pt-0 border-t md:border-t-0 border-border/40">
                {issue.assignedTo ? (
                  <div className="flex items-center gap-2 text-right">
                    <div className="text-xs">
                      <p className="text-[10px] text-muted-foreground">Assigned to</p>
                      <p className="font-medium">{issue.assignedTo.name}</p>
                    </div>
                    <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-xs text-primary">
                      {issue.assignedTo.name.charAt(0)}
                    </div>
                  </div>
                ) : (
                  <Badge variant="outline" className="text-muted-foreground text-xs">
                    Unassigned
                  </Badge>
                )}

                <ChevronRight className="h-5 w-5 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 transition-all" />
              </div>
            </CardContent>
          </Card>
        );
      })}
    </div>
  );
}
