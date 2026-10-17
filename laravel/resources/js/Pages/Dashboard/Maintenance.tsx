import { useState } from "react";
import { Head } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Button } from "@/components/ui/button";
import { Camera, Wrench } from "lucide-react";
import { MaintenanceStats } from "@/components/dashboard/maintenance/maintenance-stats";
import { MaintenanceFilterBar } from "@/components/dashboard/maintenance/maintenance-filter-bar";
import { MaintenanceIssueList } from "@/components/dashboard/maintenance/maintenance-issue-list";
import { ReportIssueDialog } from "@/components/dashboard/maintenance/report-issue-dialog";
import { IssueDetailsDialog } from "@/components/dashboard/maintenance/issue-details-dialog";
import type {
  MaintenanceFilters,
  MaintenanceIssueItem,
  MaintenanceStats as Stats,
  StaffMember,
} from "@/components/dashboard/maintenance/maintenance-types";

type Props = {
  issues: {
    data: MaintenanceIssueItem[];
    current_page: number;
    last_page: number;
    total: number;
  };
  stats: Stats;
  staffRoster: StaffMember[];
  filters: MaintenanceFilters;
  canTriage: boolean;
};

export default function MaintenancePage({
  issues,
  stats,
  staffRoster,
  filters,
  canTriage,
}: Props) {
  const [reportOpen, setReportOpen] = useState(false);
  const [selectedIssue, setSelectedIssue] = useState<MaintenanceIssueItem | null>(null);

  return (
    <DashboardLayout>
      <Head title="Maintenance & Issue Reporting — Community Hub" />

      <div className="space-y-6 pb-12">
        {/* Header section */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <div className="p-2 rounded-xl bg-primary/10 text-primary">
                <Wrench className="h-6 w-6" />
              </div>
              <h1 className="text-2xl font-bold tracking-tight">Maintenance & Issue Reporting</h1>
            </div>
            <p className="text-sm text-muted-foreground mt-1">
              Report infrastructure defects, road potholes, streetlight outages, and track repair work orders.
            </p>
          </div>

          <Button
            onClick={() => setReportOpen(true)}
            className="flex items-center gap-2 shadow-md hover:shadow-lg transition-all"
          >
            <Camera className="h-4 w-4" />
            <span>Report New Issue</span>
          </Button>
        </div>

        <MaintenanceStats stats={stats} />

        <MaintenanceFilterBar filters={filters} />

        <MaintenanceIssueList
          issues={issues.data}
          onOpenIssue={setSelectedIssue}
          onReportIssue={() => setReportOpen(true)}
        />

        <ReportIssueDialog open={reportOpen} onOpenChange={setReportOpen} />

        {selectedIssue && (
          <IssueDetailsDialog
            issue={selectedIssue}
            canTriage={canTriage}
            staffRoster={staffRoster}
            onClose={() => setSelectedIssue(null)}
          />
        )}
      </div>
    </DashboardLayout>
  );
}
