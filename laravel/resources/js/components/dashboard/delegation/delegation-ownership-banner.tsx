import { Badge } from '@/components/ui/badge';
import { Building, Users } from 'lucide-react';
import type { Props } from './delegation-types';

type DelegationOwnershipBannerProps = {
  ownershipEntity: NonNullable<Props['ownershipEntity']>;
  unitLabel: string;
};

/** The legal owner of a company- or trust-owned property, and who represents it. */
export function DelegationOwnershipBanner({ ownershipEntity, unitLabel }: DelegationOwnershipBannerProps) {
  return (
    <div className="p-4 sm:p-5 rounded-2xl border border-indigo-500/30 bg-gradient-to-r from-indigo-950/40 via-card to-background shadow-xs">
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div className="flex items-start gap-3.5">
          <div className="p-3 rounded-xl bg-indigo-500/15 border border-indigo-500/30 text-indigo-400 shrink-0">
            <Building className="w-6 h-6" />
          </div>
          <div>
            <div className="flex flex-wrap items-center gap-2 mb-1">
              <span className="text-[11px] font-bold uppercase tracking-wider text-indigo-400">Owner Entity</span>
              <Badge variant="outline" className="text-[10px] py-0.5 bg-indigo-500/10 text-indigo-300 border-indigo-500/30">
                Ownership Type: {ownershipEntity.entity_type === 'company' ? 'Company' : (ownershipEntity.entity_type.charAt(0).toUpperCase() + ownershipEntity.entity_type.slice(1))}
              </Badge>
              <Badge variant="outline" className="text-[10px] py-0.5 bg-muted/60 text-muted-foreground border-border/40">
                Property: {unitLabel || '—'}
              </Badge>
            </div>
            <h2 className="text-xl font-bold text-foreground tracking-tight">
              {ownershipEntity.legal_name}
            </h2>
            <p className="text-xs text-muted-foreground mt-0.5">
              Legal owner of {unitLabel || 'this property'}. Representatives act on behalf of the owner without becoming the legal owner.
            </p>
          </div>
        </div>

        {ownershipEntity.authorizedPeople && ownershipEntity.authorizedPeople.length > 0 && (
          <div className="flex flex-col gap-1.5 pt-2 lg:pt-0 lg:border-l lg:border-border/30 lg:pl-5">
            <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
              <Users className="w-3.5 h-3.5 text-indigo-400" />
              <span>Representatives ({ownershipEntity.authorizedPeople.length})</span>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              {ownershipEntity.authorizedPeople.map((rep) => (
                <div
                  key={rep.id}
                  className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs ${
                    rep.is_current_user
                      ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300 font-medium'
                      : 'bg-muted/40 border-border/50 text-foreground'
                  }`}
                >
                  <span className="font-semibold">{rep.name}</span>
                  <span className="text-[11px] text-muted-foreground">— {rep.role}</span>
                  {rep.is_current_user && (
                    <span className="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-400 font-bold uppercase tracking-wider">
                      You
                    </span>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
