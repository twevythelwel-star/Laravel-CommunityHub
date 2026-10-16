import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Ban, Check, CheckCircle2, Lock, Shield, ShieldCheck, Sparkles } from 'lucide-react';
import type { AccessPermissions } from './delegation-types';

type DelegationScopeBannerProps = {
  isShortTerm: boolean;
  isRenter: boolean;
  unitLabel: string;
  permissions?: AccessPermissions;
};

/** For guests and tenants: what their access is bounded by, with a matrix of what they can and cannot see. */
export function DelegationScopeBanner({ isShortTerm, isRenter, unitLabel, permissions }: DelegationScopeBannerProps) {
  const [showScopeMatrix, setShowScopeMatrix] = useState(false);

  if (isShortTerm) {
    return (
      <div className="space-y-3">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-pink-500/30 bg-pink-500/10 text-pink-300 shadow-xs">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-pink-500/20 text-pink-400 shrink-0">
              <Sparkles className="w-5 h-5" />
            </div>
            <div>
              <p className="text-xs sm:text-sm font-semibold text-foreground">Short-Term Guest Access Network Active</p>
              <p className="text-[11px] sm:text-xs text-pink-300/80">
                Scoped strictly for your stay at {unitLabel || 'your unit'}. Bounded by reservation departure {permissions?.checkoutDate ? `(checkout: ${permissions.checkoutDate})` : ''}.
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setShowScopeMatrix(!showScopeMatrix)}
              className="h-7 text-xs px-2 text-pink-300 hover:text-white hover:bg-pink-500/20 gap-1 border border-pink-500/20"
            >
              <Lock className="w-3 h-3 text-pink-400" />
              <span>{showScopeMatrix ? 'Hide Scope Matrix' : 'Permissions & Scope'}</span>
            </Button>
            <Badge variant="outline" className="border-pink-500/30 text-pink-400 bg-pink-500/10 text-[10px]">
              Reservation Bounded
            </Badge>
          </div>
        </div>

        {showScopeMatrix && (
          <Card className="border border-pink-500/20 bg-card/95 backdrop-blur-sm overflow-hidden">
            <CardHeader className="p-4 pb-2 border-b border-border/30">
              <CardTitle className="text-sm font-bold flex items-center gap-2 text-foreground">
                <Shield className="w-4 h-4 text-pink-400" />
                <span>Short-Term Rental Scope & Isolated Access Boundaries</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="p-4 pt-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
              <div className="space-y-2 p-3 rounded-lg border border-emerald-500/20 bg-emerald-500/5">
                <div className="flex items-center gap-1.5 text-emerald-400 font-semibold text-xs">
                  <CheckCircle2 className="w-4 h-4" />
                  <span>Authorized Visibility & Stay Privileges</span>
                </div>
                <ul className="space-y-1.5 text-muted-foreground">
                  <li className="flex items-center gap-2"><Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" /><span><strong>My Stay:</strong> Reservation check-in / check-out window</span></li>
                  <li className="flex items-center gap-2"><Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" /><span><strong>My Visitors:</strong> Pre-clear social visitors</span></li>
                  <li className="flex items-center gap-2"><Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" /><span><strong>My Access:</strong> Dynamic QR gate credential</span></li>
                  <li className="flex items-center gap-2"><Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" /><span><strong>Property Services:</strong> Authorized housekeeping (read-only)</span></li>
                </ul>
              </div>
              <div className="space-y-2 p-3 rounded-lg border border-rose-500/20 bg-rose-500/5">
                <div className="flex items-center gap-1.5 text-rose-400 font-semibold text-xs">
                  <Lock className="w-4 h-4" />
                  <span>Strictly Excluded & Restricted Boundaries</span>
                </div>
                <ul className="space-y-1.5 text-muted-foreground">
                  <li className="flex items-center gap-2"><Ban className="w-3.5 h-3.5 text-rose-400 shrink-0" /><span><strong>Homeowner Legacy Contacts:</strong> Excluded</span></li>
                  <li className="flex items-center gap-2"><Ban className="w-3.5 h-3.5 text-rose-400 shrink-0" /><span><strong>Unrelated Residents & Co-Owners:</strong> Excluded</span></li>
                  <li className="flex items-center gap-2"><Ban className="w-3.5 h-3.5 text-rose-400 shrink-0" /><span><strong>Unrestricted Credentials:</strong> Clamped to checkout date</span></li>
                </ul>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    );
  }

  if (!isRenter) {
    return null;
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-indigo-500/30 bg-indigo-500/10 text-indigo-300 shadow-xs">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-indigo-500/20 text-indigo-400 shrink-0">
            <ShieldCheck className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs sm:text-sm font-semibold text-foreground">Tenant Access Network Active</p>
            <p className="text-[11px] sm:text-xs text-indigo-300/80">
              Scoped strictly for your tenancy at {unitLabel || 'your property'}. Authorized passes bounded by your lease {permissions?.leaseEndDate ? `(expires ${permissions.leaseEndDate})` : 'term'}.
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setShowScopeMatrix(!showScopeMatrix)}
            className="h-7 text-xs px-2 text-indigo-300 hover:text-white hover:bg-indigo-500/20 gap-1 border border-indigo-500/20"
          >
            <Lock className="w-3 h-3 text-indigo-400" />
            <span>{showScopeMatrix ? 'Hide Scope Matrix' : 'Permissions & Scope'}</span>
          </Button>
          <Badge variant="outline" className="border-indigo-500/30 text-indigo-400 bg-indigo-500/10 text-[10px]">
            Lease Bounded
          </Badge>
        </div>
      </div>

      {showScopeMatrix && (
        <Card className="border border-indigo-500/20 bg-card/95 backdrop-blur-sm overflow-hidden">
          <CardHeader className="p-4 pb-2 border-b border-border/30">
            <CardTitle className="text-sm font-bold flex items-center gap-2 text-foreground">
              <Shield className="w-4 h-4 text-indigo-400" />
              <span>Tenant Access Scope & Enforced Privilege Separation</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div className="space-y-2 p-3 rounded-lg border border-emerald-500/20 bg-emerald-500/5">
              <div className="flex items-center gap-1.5 text-emerald-400 font-semibold text-xs">
                <CheckCircle2 className="w-4 h-4" />
                <span>Authorized Visibility & Tenancy Privileges</span>
              </div>
              <ul className="space-y-1.5 text-muted-foreground">
                <li className="flex items-center gap-2"><Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" /><span><strong>Own Household & Occupants:</strong> Co-occupants residing under lease</span></li>
                <li className="flex items-center gap-2"><Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" /><span><strong>Invited Visitors:</strong> Pre-cleared guests</span></li>
                <li className="flex items-center gap-2"><Check className="w-3.5 h-3.5 text-emerald-400 shrink-0" /><span><strong>Staff & Contractors:</strong> Helpers cleared for {unitLabel}</span></li>
              </ul>
            </div>
            <div className="space-y-2 p-3 rounded-lg border border-amber-500/20 bg-amber-500/5">
              <div className="flex items-center gap-1.5 text-amber-400 font-semibold text-xs">
                <Lock className="w-4 h-4" />
                <span>Separate Homeowner Capabilities (Restricted)</span>
              </div>
              <ul className="space-y-1.5 text-muted-foreground">
                <li className="flex items-center gap-2"><Ban className="w-3.5 h-3.5 text-amber-400 shrink-0" /><span><strong>Remove Homeowner:</strong> Restricted</span></li>
                <li className="flex items-center gap-2"><Ban className="w-3.5 h-3.5 text-amber-400 shrink-0" /><span><strong>Property Ownership:</strong> Immutable</span></li>
                <li className="flex items-center gap-2"><Ban className="w-3.5 h-3.5 text-amber-400 shrink-0" /><span><strong>Unrestricted Credentials:</strong> Clamped to lease end</span></li>
              </ul>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
