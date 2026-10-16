import { useState, useMemo, type FormEvent, ChangeEvent } from "react";
import { Head, router } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
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
import { ClientFormattedDate, ClientFormattedDistanceToNow } from "@/components/client-formatted-date";
import { useToast } from "@/hooks/use-toast";
import {
  Wrench,
  AlertTriangle,
  CheckCircle2,
  Clock,
  MapPin,
  Camera,
  X,
  UploadCloud,
  Search,
  Filter,
  User,
  ShieldCheck,
  ChevronRight,
  Sparkles,
  Zap,
  Droplets,
  Trash2,
  Layers,
  Construction,
  Building,
  Check,
  HelpCircle,
} from "lucide-react";

export type MaintenanceCategory =
  | "potholes"
  | "streetlights"
  | "drainage"
  | "water_leaks"
  | "waste_collection"
  | "damaged_common_areas"
  | "other";

export type MaintenancePriority = "low" | "medium" | "high" | "emergency";

export type MaintenanceStatus =
  | "reported"
  | "triaged"
  | "assigned"
  | "in_progress"
  | "resolved"
  | "closed"
  | "cancelled";

export type PhotoItem = {
  name: string;
  path: string;
  url: string;
  size: number;
};

export type IssueUpdate = {
  id: number;
  fromStatus: string | null;
  toStatus: string | null;
  note: string | null;
  isInternal: boolean;
  photos: PhotoItem[];
  user: {
    id: number;
    name: string;
    role: string;
  };
  createdAt: string;
};

export type MaintenanceIssueItem = {
  id: number;
  referenceNumber: string;
  category: MaintenanceCategory;
  title: string;
  description: string;
  location: string;
  latitude: number | null;
  longitude: number | null;
  priority: MaintenancePriority;
  status: MaintenanceStatus;
  photos: PhotoItem[];
  resolutionNotes: string | null;
  reporter: {
    id: number;
    name: string;
    role: string;
    lot?: string;
    street?: string;
  };
  assignedTo: {
    id: number;
    name: string;
    role: string;
  } | null;
  assignedAt: string | null;
  resolvedAt: string | null;
  closedAt: string | null;
  createdAt: string;
  updatesCount: number;
  updates: IssueUpdate[];
};

type Props = {
  issues: {
    data: MaintenanceIssueItem[];
    current_page: number;
    last_page: number;
    total: number;
  };
  stats: {
    total: number;
    reported: number;
    in_progress: number;
    resolved: number;
    emergency: number;
  };
  staffRoster: Array<{
    id: number;
    name: string;
    role: string;
    email: string;
  }>;
  filters: {
    category?: string;
    status?: string;
    priority?: string;
    search?: string;
  };
  canTriage: boolean;
};

const CATEGORIES: Array<{
  id: MaintenanceCategory;
  label: string;
  icon: typeof Construction;
  color: string;
  description: string;
}> = [
  {
    id: "potholes",
    label: "Potholes & Roads",
    icon: Construction,
    color: "text-amber-500 bg-amber-500/10 border-amber-500/20",
    description: "Road cracks, potholes, sunken asphalt, or curbing damage",
  },
  {
    id: "streetlights",
    label: "Streetlights & Power",
    icon: Zap,
    color: "text-yellow-500 bg-yellow-500/10 border-yellow-500/20",
    description: "Dark poles, flickering lights, exposed wiring, or transformer issues",
  },
  {
    id: "drainage",
    label: "Drainage & Flooding",
    icon: Droplets,
    color: "text-cyan-500 bg-cyan-500/10 border-cyan-500/20",
    description: "Blocked culverts, overflowing ditches, or storm drain clogging",
  },
  {
    id: "water_leaks",
    label: "Water Leaks & Mains",
    icon: Droplets,
    color: "text-blue-500 bg-blue-500/10 border-blue-500/20",
    description: "Burst pipes, leaking meters, standing clean water, or valve leaks",
  },
  {
    id: "waste_collection",
    label: "Waste Collection",
    icon: Trash2,
    color: "text-emerald-500 bg-emerald-500/10 border-emerald-500/20",
    description: "Missed pickups, overflowing communal bins, or bulk trash removal",
  },
  {
    id: "damaged_common_areas",
    label: "Damaged Amenities",
    icon: Building,
    color: "text-rose-500 bg-rose-500/10 border-rose-500/20",
    description: "Clubhouse, playground, jogging track, gates, or gym repairs",
  },
  {
    id: "other",
    label: "Other Maintenance",
    icon: Wrench,
    color: "text-slate-500 bg-slate-500/10 border-slate-500/20",
    description: "General repairs and estate grounds issues",
  },
];

export default function MaintenancePage({
  issues,
  stats,
  staffRoster,
  filters,
  canTriage,
}: Props) {
  const { toast } = useToast();

  // Dialog state for Report Issue
  const [reportOpen, setReportOpen] = useState(false);
  const [selectedIssue, setSelectedIssue] = useState<MaintenanceIssueItem | null>(null);
  const [lightboxPhoto, setLightboxPhoto] = useState<string | null>(null);

  // Filter state
  const [search, setSearch] = useState(filters.search ?? "");
  const [statusFilter, setStatusFilter] = useState(filters.status ?? "all");
  const [categoryFilter, setCategoryFilter] = useState(filters.category ?? "all");
  const [priorityFilter, setPriorityFilter] = useState(filters.priority ?? "all");

  // Report form state
  const [reportCategory, setReportCategory] = useState<MaintenanceCategory>("potholes");
  const [reportTitle, setReportTitle] = useState("");
  const [reportDescription, setReportDescription] = useState("");
  const [reportLocation, setReportLocation] = useState("");
  const [reportPriority, setReportPriority] = useState<MaintenancePriority>("medium");
  const [reportFiles, setReportFiles] = useState<File[]>([]);
  const [submittingReport, setSubmittingReport] = useState(false);

  // Triage update form state
  const [triageStatus, setTriageStatus] = useState<string>("");
  const [triageAssignee, setTriageAssignee] = useState<string>("");
  const [triagePriority, setTriagePriority] = useState<string>("");
  const [triageNote, setTriageNote] = useState("");
  const [triageResolution, setTriageResolution] = useState("");
  const [triageInternal, setTriageInternal] = useState(false);
  const [triageFiles, setTriageFiles] = useState<File[]>([]);
  const [updatingIssue, setUpdatingIssue] = useState(false);

  // Handle open issue details
  const openIssueDetails = (issue: MaintenanceIssueItem) => {
    setSelectedIssue(issue);
    setTriageStatus(issue.status);
    setTriageAssignee(issue.assignedTo ? String(issue.assignedTo.id) : "unassigned");
    setTriagePriority(issue.priority);
    setTriageNote("");
    setTriageResolution(issue.resolutionNotes || "");
    setTriageInternal(false);
    setTriageFiles([]);
  };

  // Handle filter changes
  const applyFilters = (
    newStatus = statusFilter,
    newCategory = categoryFilter,
    newPriority = priorityFilter,
    newSearch = search
  ) => {
    router.get(
      "/dashboard/maintenance",
      {
        ...(newStatus !== "all" ? { status: newStatus } : {}),
        ...(newCategory !== "all" ? { category: newCategory } : {}),
        ...(newPriority !== "all" ? { priority: newPriority } : {}),
        ...(newSearch.trim() ? { search: newSearch.trim() } : {}),
      },
      { preserveState: true, replace: true }
    );
  };

  // Photo file selection
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

  // Submit new report
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
        setReportOpen(false);
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

  // Submit triage / status update
  const handleTriageSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (!selectedIssue) return;

    if (triageStatus === "resolved" && !triageResolution.trim() && !selectedIssue.resolutionNotes) {
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
    if (triageInternal) formData.append("is_internal", "1");

    triageFiles.forEach((file) => {
      formData.append("photos[]", file);
    });

    router.post(`/dashboard/maintenance/${selectedIssue.id}`, formData, {
      onSuccess: () => {
        setSelectedIssue(null);
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

  // Helpers for category metadata
  const getCategoryMeta = (cat: MaintenanceCategory) => {
    return CATEGORIES.find((c) => c.id === cat) || CATEGORIES[6];
  };

  const getStatusBadge = (status: MaintenanceStatus) => {
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
  };

  const getPriorityBadge = (priority: MaintenancePriority) => {
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
    }
  };

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

        {/* High-level KPI stat cards */}
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          <Card className="border bg-card/60 backdrop-blur-sm">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-medium text-muted-foreground">Total Issues</p>
                <p className="text-2xl font-bold mt-0.5">{stats.total}</p>
              </div>
              <div className="p-2.5 rounded-full bg-primary/10 text-primary">
                <Layers className="h-4 w-4" />
              </div>
            </CardContent>
          </Card>

          <Card className="border bg-card/60 backdrop-blur-sm">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-medium text-muted-foreground">Reported / New</p>
                <p className="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-0.5">{stats.reported}</p>
              </div>
              <div className="p-2.5 rounded-full bg-amber-500/10 text-amber-600">
                <Clock className="h-4 w-4" />
              </div>
            </CardContent>
          </Card>

          <Card className="border bg-card/60 backdrop-blur-sm">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-medium text-muted-foreground">In Progress</p>
                <p className="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-0.5">{stats.in_progress}</p>
              </div>
              <div className="p-2.5 rounded-full bg-blue-500/10 text-blue-600">
                <Wrench className="h-4 w-4" />
              </div>
            </CardContent>
          </Card>

          <Card className="border bg-card/60 backdrop-blur-sm">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-medium text-muted-foreground">Resolved</p>
                <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{stats.resolved}</p>
              </div>
              <div className="p-2.5 rounded-full bg-emerald-500/10 text-emerald-600">
                <CheckCircle2 className="h-4 w-4" />
              </div>
            </CardContent>
          </Card>

          <Card className="border bg-card/60 backdrop-blur-sm col-span-2 md:col-span-1">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-medium text-muted-foreground">Safety Hazards</p>
                <p className="text-2xl font-bold text-destructive mt-0.5">{stats.emergency}</p>
              </div>
              <div className="p-2.5 rounded-full bg-destructive/10 text-destructive">
                <AlertTriangle className="h-4 w-4" />
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Filter & Search Bar */}
        <Card className="border bg-card/50">
          <CardContent className="p-4 space-y-3">
            <div className="flex flex-col md:flex-row gap-3">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search by reference #, title, or street location..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyDown={(e) => e.key === "Enter" && applyFilters(statusFilter, categoryFilter, priorityFilter, search)}
                  className="pl-9 h-9"
                />
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <Select
                  value={categoryFilter}
                  onValueChange={(val) => {
                    setCategoryFilter(val);
                    applyFilters(statusFilter, val, priorityFilter, search);
                  }}
                >
                  <SelectTrigger className="h-9 w-[170px]">
                    <SelectValue placeholder="Category" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Categories</SelectItem>
                    {CATEGORIES.map((c) => (
                      <SelectItem key={c.id} value={c.id}>{c.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                <Select
                  value={statusFilter}
                  onValueChange={(val) => {
                    setStatusFilter(val);
                    applyFilters(val, categoryFilter, priorityFilter, search);
                  }}
                >
                  <SelectTrigger className="h-9 w-[140px]">
                    <SelectValue placeholder="Status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    <SelectItem value="reported">Reported</SelectItem>
                    <SelectItem value="assigned">Assigned</SelectItem>
                    <SelectItem value="in_progress">In Progress</SelectItem>
                    <SelectItem value="resolved">Resolved</SelectItem>
                    <SelectItem value="closed">Closed</SelectItem>
                  </SelectContent>
                </Select>

                <Select
                  value={priorityFilter}
                  onValueChange={(val) => {
                    setPriorityFilter(val);
                    applyFilters(statusFilter, categoryFilter, val, search);
                  }}
                >
                  <SelectTrigger className="h-9 w-[140px]">
                    <SelectValue placeholder="Priority" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Priorities</SelectItem>
                    <SelectItem value="emergency">Emergency</SelectItem>
                    <SelectItem value="high">High</SelectItem>
                    <SelectItem value="medium">Medium</SelectItem>
                    <SelectItem value="low">Low</SelectItem>
                  </SelectContent>
                </Select>

                {(statusFilter !== "all" || categoryFilter !== "all" || priorityFilter !== "all" || search) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setStatusFilter("all");
                      setCategoryFilter("all");
                      setPriorityFilter("all");
                      setSearch("");
                      router.get("/dashboard/maintenance");
                    }}
                    className="h-9 text-xs text-muted-foreground hover:text-foreground"
                  >
                    Reset
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Issues listing */}
        {issues.data.length === 0 ? (
          <Card className="border border-dashed p-12 text-center">
            <div className="flex flex-col items-center justify-center space-y-3">
              <div className="p-3 rounded-full bg-muted text-muted-foreground">
                <Wrench className="h-8 w-8 stroke-[1.5]" />
              </div>
              <h3 className="font-semibold text-base">No maintenance issues found</h3>
              <p className="text-sm text-muted-foreground max-w-sm">
                No reports match your current filter settings. You can report a new issue or clear filters to see all community work orders.
              </p>
              <Button onClick={() => setReportOpen(true)} size="sm" className="mt-2">
                Report Issue
              </Button>
            </div>
          </Card>
        ) : (
          <div className="grid gap-3">
            {issues.data.map((issue) => {
              const meta = getCategoryMeta(issue.category);
              const CategoryIcon = meta.icon;

              return (
                <Card
                  key={issue.id}
                  onClick={() => openIssueDetails(issue)}
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
                          {getPriorityBadge(issue.priority)}
                          {getStatusBadge(issue.status)}
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
        )}

        {/* Report New Issue Modal */}
        <Dialog open={reportOpen} onOpenChange={setReportOpen}>
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
                  onClick={() => setReportOpen(false)}
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

        {/* Issue Details & Triage Modal */}
        {selectedIssue && (
          <Dialog open={!!selectedIssue} onOpenChange={(open) => !open && setSelectedIssue(null)}>
            <DialogContent className="max-w-3xl max-h-[92vh] overflow-y-auto">
              <DialogHeader>
                <div className="flex flex-wrap items-center justify-between gap-2 pr-6">
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-xs font-bold text-muted-foreground bg-muted px-2.5 py-1 rounded-md">
                      {selectedIssue.referenceNumber}
                    </span>
                    <span className="text-xs font-semibold">
                      {getCategoryMeta(selectedIssue.category).label}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    {getPriorityBadge(selectedIssue.priority)}
                    {getStatusBadge(selectedIssue.status)}
                  </div>
                </div>
                <DialogTitle className="text-xl font-bold pt-2">
                  {selectedIssue.title}
                </DialogTitle>
                <DialogDescription className="flex items-center gap-2 text-xs pt-1">
                  <MapPin className="h-3.5 w-3.5 text-muted-foreground" />
                  <span>{selectedIssue.location}</span>
                  <span>•</span>
                  <span>Reported by {selectedIssue.reporter.name}</span>
                  <span>•</span>
                  <ClientFormattedDate date={selectedIssue.createdAt} formatString="MMM d, yyyy h:mm a" />
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-6 pt-3">
                {/* Description card */}
                <div className="p-4 rounded-xl bg-muted/30 border space-y-2">
                  <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Issue Description
                  </h4>
                  <p className="text-sm leading-relaxed whitespace-pre-wrap">
                    {selectedIssue.description}
                  </p>
                </div>

                {/* Attached photos gallery */}
                {selectedIssue.photos.length > 0 && (
                  <div className="space-y-2">
                    <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                      <Camera className="h-4 w-4" />
                      Attached Photos ({selectedIssue.photos.length})
                    </h4>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                      {selectedIssue.photos.map((photo, i) => (
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
                {selectedIssue.resolutionNotes && (
                  <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 space-y-1.5">
                    <h4 className="text-xs font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                      <CheckCircle2 className="h-4 w-4" />
                      Resolution & Closure Details
                    </h4>
                    <p className="text-sm leading-relaxed text-emerald-950 dark:text-emerald-100">
                      {selectedIssue.resolutionNotes}
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
                    {selectedIssue.updates.map((update, i) => (
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
                            {getStatusBadge(update.toStatus as MaintenanceStatus)}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                </div>

                {/* Admin & Staff Triage Form */}
                {canTriage && (
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
                )}
              </div>
            </DialogContent>
          </Dialog>
        )}

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
      </div>
    </DashboardLayout>
  );
}
