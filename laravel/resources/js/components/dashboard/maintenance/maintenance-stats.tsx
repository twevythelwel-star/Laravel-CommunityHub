import { Card, CardContent } from "@/components/ui/card";
import { AlertTriangle, CheckCircle2, Clock, Layers, Wrench } from "lucide-react";
import type { MaintenanceStats as Stats } from "./maintenance-types";

/** The five headline counts above the issue list. */
export function MaintenanceStats({ stats }: { stats: Stats }) {
  return (
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
  );
}
