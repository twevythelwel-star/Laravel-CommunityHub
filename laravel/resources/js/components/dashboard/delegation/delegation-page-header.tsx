import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ChevronRight, Plus, ShieldAlert } from 'lucide-react';
import type { AccessPermissions } from './delegation-types';

type DelegationPageHeaderProps = {
  unitLabel: string;
  pageTitle?: string;
  pageSubtitle?: string;
  scopeBadge?: string;
  permissions?: AccessPermissions;
  isRenter: boolean;
  isShortTerm: boolean;
  onOpenContinuityPlan: () => void;
  onAuthorize: () => void;
};

/** Breadcrumb, title and the page's main actions. */
export function DelegationPageHeader({
  unitLabel,
  pageTitle,
  pageSubtitle,
  scopeBadge,
  permissions,
  isRenter,
  isShortTerm,
  onOpenContinuityPlan,
  onAuthorize,
}: DelegationPageHeaderProps) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-5">
      <div>
        <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground mb-1.5">
          <span>{unitLabel || 'My Property'}</span>
          <ChevronRight className="w-3.5 h-3.5 text-muted-foreground/60" />
          <span className="text-foreground font-semibold">
            {isRenter ? 'My Access Network' : 'People & Access'}
          </span>
          <Badge variant="outline" className={`ml-2 text-[10px] py-0 px-2 ${isRenter ? 'border-indigo-500/30 text-indigo-400 bg-indigo-500/10' : 'border-emerald-500/30 text-emerald-400 bg-emerald-500/10'}`}>
            {scopeBadge || permissions?.scopeBadge || (isRenter ? 'Tenant Access Scope • Bounded by Lease' : 'Zero Cross-Unit Leakage • Scoped')}
          </Badge>
        </div>
        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight">
          {pageTitle || (isRenter ? `${unitLabel || 'My Property'} — My Access Network` : `${unitLabel || 'My Property'} — People & Access`)}
        </h1>
        <p className="text-sm text-muted-foreground mt-0.5">
          {pageSubtitle || (isRenter
            ? `Authorized occupants, visitors, staff, and contractors for your tenancy at ${unitLabel || 'your property'}.`
            : `Authorized occupants, guests, staff, contractors, and legacy delegates strictly associated with ${unitLabel || 'your property'}.`
          )}
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-2.5">
        {!isRenter && !isShortTerm && (permissions?.isHomeowner || permissions?.isAdmin) && (
          <Button
            variant="outline"
            size="sm"
            onClick={onOpenContinuityPlan}
            className="gap-2 border-amber-500/30 hover:border-amber-500/60 text-amber-400 hover:text-amber-300"
          >
            <ShieldAlert className="w-4 h-4 text-amber-500" />
            <span>Continuity Plan</span>
          </Button>
        )}

        <Button
          size="sm"
          onClick={onAuthorize}
          className="gap-2 bg-primary hover:bg-primary/90 shadow-sm"
        >
          <Plus className="w-4 h-4" />
          <span>{isShortTerm ? 'Pre-Clear Visitor' : 'Authorize Person'}</span>
        </Button>
      </div>
    </div>
  );
}
