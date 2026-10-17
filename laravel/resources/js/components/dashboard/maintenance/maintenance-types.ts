import { Building, Construction, Droplets, Trash2, Wrench, Zap } from "lucide-react";

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

export type MaintenanceStats = {
  total: number;
  reported: number;
  in_progress: number;
  resolved: number;
  emergency: number;
};

export type StaffMember = {
  id: number;
  name: string;
  role: string;
  email: string;
};

export type MaintenanceFilters = {
  category?: string;
  status?: string;
  priority?: string;
  search?: string;
};

export type MaintenanceCategoryMeta = {
  id: MaintenanceCategory;
  label: string;
  icon: typeof Construction;
  color: string;
  description: string;
};

export const CATEGORIES: MaintenanceCategoryMeta[] = [
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

/** A category's label, icon and colours; unknown categories read as "Other". */
export function categoryMeta(category: MaintenanceCategory): MaintenanceCategoryMeta {
  return CATEGORIES.find((c) => c.id === category) || CATEGORIES[6];
}
