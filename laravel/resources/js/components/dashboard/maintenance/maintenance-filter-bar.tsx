import { useState } from "react";
import { router } from "@inertiajs/react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Search } from "lucide-react";
import { CATEGORIES, type MaintenanceFilters } from "./maintenance-types";

/** Search, category, status and priority filters; each change reloads the list from the server. */
export function MaintenanceFilterBar({ filters }: { filters: MaintenanceFilters }) {
  const [search, setSearch] = useState(filters.search ?? "");
  const [statusFilter, setStatusFilter] = useState(filters.status ?? "all");
  const [categoryFilter, setCategoryFilter] = useState(filters.category ?? "all");
  const [priorityFilter, setPriorityFilter] = useState(filters.priority ?? "all");

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

  return (
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
  );
}
