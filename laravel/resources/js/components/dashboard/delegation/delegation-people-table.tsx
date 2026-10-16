import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Clock, QrCode, SlidersHorizontal } from 'lucide-react';
import { renderCredentialShapeIcon } from './delegation-person-card';
import type { AuthorizedPerson } from './delegation-types';

type DelegationPeopleTableProps = {
  people: AuthorizedPerson[];
  onViewPass: (person: AuthorizedPerson) => void;
  onEditRules: (person: AuthorizedPerson) => void;
};

const DURATION_BADGE: Record<string, string> = {
  '2_hours': 'bg-amber-500/10 text-amber-400 border-amber-500/30',
  '1_day': 'bg-blue-500/10 text-blue-400 border-blue-500/30',
  '1_week': 'bg-purple-500/10 text-purple-400 border-purple-500/30',
  recurring: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
  manual_revocation: 'bg-teal-500/10 text-teal-400 border-teal-500/30',
};

/** The table view: schedule, credential and status per person, with pass and rules actions. */
export function DelegationPeopleTable({ people, onViewPass, onEditRules }: DelegationPeopleTableProps) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border/40 bg-card/60">
      <table className="w-full text-left text-xs">
        <thead className="bg-muted/40 border-b border-border/40 text-muted-foreground uppercase tracking-wider text-[10px]">
          <tr>
            <th className="py-3 px-4">Person & Role</th>
            <th className="py-3 px-4">Category</th>
            <th className="py-3 px-4">Schedule & Rules</th>
            <th className="py-3 px-4">Credential</th>
            <th className="py-3 px-4">Status</th>
            <th className="py-3 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border/20">
          {people.map((person) => {
            const isActive = person.status === 'active' && person.credential?.isActive;
            return (
              <tr key={person.id} className="hover:bg-muted/20 transition-colors">
                <td className="py-3 px-4">
                  <div className="font-semibold text-foreground">{person.name}</div>
                  <div className="text-[11px] text-muted-foreground">{person.email || person.phone || person.relationship}</div>
                </td>
                <td className="py-3 px-4">
                  <Badge variant="outline" className={`text-[10px] ${person.categoryBadgeColor}`}>
                    {person.categoryLabel}
                  </Badge>
                </td>
                <td className="py-3 px-4">
                  <div className="flex items-center gap-1.5 mb-1">
                    {person.durationLabel && (
                      <span
                        className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-semibold border ${
                          DURATION_BADGE[person.durationType ?? ''] ?? 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30'
                        }`}
                      >
                        <Clock className="w-2.5 h-2.5" />
                        {person.durationLabel}
                      </span>
                    )}
                    <span className="font-medium text-foreground">{person.accessRules?.allowed_days?.join(', ') || 'Everyday'}</span>
                  </div>
                  <div className="text-[10px] text-muted-foreground">
                    {person.accessRules?.entry_start_time || '00:00'} - {person.accessRules?.entry_end_time || '23:59'}
                  </div>
                </td>
                <td className="py-3 px-4">
                  <div className="flex items-center gap-1.5 font-mono text-[11px]">
                    {renderCredentialShapeIcon(person.credential?.shape)}
                    <span>{person.credential?.passId || 'GP-PENDING'}</span>
                  </div>
                </td>
                <td className="py-3 px-4">
                  <span
                    className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium border ${
                      isActive
                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                        : 'bg-zinc-500/10 text-zinc-400 border-zinc-500/30'
                    }`}
                  >
                    {person.status.toUpperCase()}
                  </span>
                </td>
                <td className="py-3 px-4 text-right">
                  <div className="flex items-center justify-end gap-1.5">
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() => onViewPass(person)}
                      className="h-7 text-xs px-2"
                    >
                      <QrCode className="w-3.5 h-3.5 mr-1" /> View Pass
                    </Button>
                    {person.sourceType === 'delegation' && (
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => onEditRules(person)}
                        className="h-7 text-xs px-2"
                      >
                        <SlidersHorizontal className="w-3.5 h-3.5" />
                      </Button>
                    )}
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
