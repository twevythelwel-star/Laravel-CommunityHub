import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { ClientFormattedDate, ClientFormattedDistanceToNow } from "@/components/client-formatted-date";
import { Camera, CheckCircle2, Clock, MapPin } from "lucide-react";
import { PriorityBadge, StatusBadge } from "./maintenance-badges";
import { MaintenanceTriageForm } from "./maintenance-triage-form";
import { categoryMeta, type MaintenanceIssueItem, type MaintenanceStatus, type StaffMember } from "./maintenance-types";

type IssueDetailsDialogProps = {
  issue: MaintenanceIssueItem;
  canTriage: boolean;
  staffRoster: StaffMember[];
  onClose: () => void;
};

/** One issue in full: description, photos (enlargeable), resolution, timeline, and staff triage. */
export function IssueDetailsDialog({ issue, canTriage, staffRoster, onClose }: IssueDetailsDialogProps) {
  const [lightboxPhoto, setLightboxPhoto] = useState<string | null>(null);

  return (
    <>
      <Dialog open onOpenChange={(open) => !open && onClose()}>
        <DialogContent className="max-w-3xl max-h-[92vh] overflow-y-auto">
          <DialogHeader>
            <div className="flex flex-wrap items-center justify-between gap-2 pr-6">
              <div className="flex items-center gap-2">
                <span className="font-mono text-xs font-bold text-muted-foreground bg-muted px-2.5 py-1 rounded-md">
                  {issue.referenceNumber}
                </span>
                <span className="text-xs font-semibold">
                  {categoryMeta(issue.category).label}
                </span>
              </div>
              <div className="flex items-center gap-2">
                <PriorityBadge priority={issue.priority} />
                <StatusBadge status={issue.status} />
              </div>
            </div>
            <DialogTitle className="text-xl font-bold pt-2">
              {issue.title}
            </DialogTitle>
            <DialogDescription className="flex items-center gap-2 text-xs pt-1">
              <MapPin className="h-3.5 w-3.5 text-muted-foreground" />
              <span>{issue.location}</span>
              <span>•</span>
              <span>Reported by {issue.reporter.name}</span>
              <span>•</span>
              <ClientFormattedDate date={issue.createdAt} formatString="MMM d, yyyy h:mm a" />
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-6 pt-3">
            {/* Description card */}
            <div className="p-4 rounded-xl bg-muted/30 border space-y-2">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Issue Description
              </h4>
              <p className="text-sm leading-relaxed whitespace-pre-wrap">
                {issue.description}
              </p>
            </div>

            {/* Attached photos gallery */}
            {issue.photos.length > 0 && (
              <div className="space-y-2">
                <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                  <Camera className="h-4 w-4" />
                  Attached Photos ({issue.photos.length})
                </h4>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  {issue.photos.map((photo, i) => (
                    <div
                      key={i}
                      onClick={() => setLightboxPhoto(photo.url)}
                      className="relative aspect-video rounded-lg overflow-hidden border bg-muted group cursor-pointer hover:ring-2 hover:ring-primary/40 transition-all"
                    >
                      <img
                        src={photo.url}
                        alt={photo.name}
                        className="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300"
                      />
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Resolution Notes (if closed/resolved) */}
            {issue.resolutionNotes && (
              <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 space-y-1.5">
                <h4 className="text-xs font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                  <CheckCircle2 className="h-4 w-4" />
                  Resolution & Closure Details
                </h4>
                <p className="text-sm leading-relaxed text-emerald-950 dark:text-emerald-100">
                  {issue.resolutionNotes}
                </p>
              </div>
            )}

            {/* Timeline History */}
            <div className="space-y-3">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                <Clock className="h-4 w-4" />
                Work Order Progress Timeline
              </h4>

              <div className="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-border">
                {issue.updates.map((update) => (
                  <div key={update.id} className="relative text-xs space-y-1">
                    <div className="absolute -left-6 top-1 h-3 w-3 rounded-full bg-primary ring-4 ring-background" />
                    <div className="flex items-center justify-between text-muted-foreground">
                      <span className="font-semibold text-foreground">
                        {update.user.name} ({update.user.role})
                      </span>
                      <ClientFormattedDistanceToNow date={update.createdAt} />
                    </div>
                    {update.note && (
                      <p className="text-muted-foreground leading-relaxed">
                        {update.note}
                      </p>
                    )}
                    {update.toStatus && (
                      <div className="pt-0.5">
                        <StatusBadge status={update.toStatus as MaintenanceStatus} />
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {canTriage && (
              <MaintenanceTriageForm key={issue.id} issue={issue} staffRoster={staffRoster} onSaved={onClose} />
            )}
          </div>
        </DialogContent>
      </Dialog>

      {/* Lightbox photo preview */}
      {lightboxPhoto && (
        <Dialog open={!!lightboxPhoto} onOpenChange={() => setLightboxPhoto(null)}>
          <DialogContent className="max-w-4xl p-2 bg-black/90 border-0 flex items-center justify-center">
            <img
              src={lightboxPhoto}
              alt="Enlarged attachment"
              className="max-h-[85vh] w-auto object-contain rounded-md"
            />
          </DialogContent>
        </Dialog>
      )}
    </>
  );
}
