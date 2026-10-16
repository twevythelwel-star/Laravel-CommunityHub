import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ChevronRight, Info, Plus } from 'lucide-react';
import { DelegationPersonCard } from './delegation-person-card';
import { inCategory } from './delegation-rules';
import type { AuthorizedPerson, HOMEOWNER_SECTIONS_CONFIG } from './delegation-types';

/** What can be done to a person from their card. */
export type PersonCardActions = Omit<ComponentProps<typeof DelegationPersonCard>, 'person'>;

type DelegationSectionsViewProps = {
  sections: typeof HOMEOWNER_SECTIONS_CONFIG;
  people: AuthorizedPerson[];
  actions: PersonCardActions;
  onAuthorize: (categoryKey: string) => void;
};

/** One panel per section (residents, visitors, staff…), each listing its people as cards. */
export function DelegationSectionsView({ sections, people, actions, onAuthorize }: DelegationSectionsViewProps) {
  return (
    <div className="space-y-6">
      {sections.map((sec) => {
        const SectionIcon = sec.icon;
        const secItems = people.filter((p) => inCategory(p, sec.key));

        return (
          <div key={sec.key} className="space-y-3.5 border border-border/50 bg-card/40 rounded-2xl p-5 shadow-xs transition-all">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border/30 pb-3">
              <div className="flex items-center gap-3">
                <div className={`p-2 rounded-xl border ${sec.badgeColor}`}>
                  <SectionIcon className="w-5 h-5" />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h2 className="text-base sm:text-lg font-bold tracking-tight text-foreground">{sec.title}</h2>
                    <Badge variant="outline" className={`text-[10px] font-semibold ${sec.badgeColor}`}>
                      {secItems.length} {secItems.length === 1 ? 'Person' : 'People'}
                    </Badge>
                  </div>
                  <p className="text-xs text-muted-foreground font-medium">{sec.subtitle}</p>
                </div>
              </div>

              {sec.actionLabel && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => onAuthorize(sec.actionCategory)}
                  className="h-8 text-xs gap-1.5 self-start sm:self-auto border-border/60 hover:bg-muted/30"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>{sec.actionLabel}</span>
                </Button>
              )}
            </div>

            {secItems.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-1">
                {secItems.map((person) => (
                  <DelegationPersonCard key={person.id} person={person} {...actions} />
                ))}
              </div>
            ) : (
              <div className="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-xl border border-dashed border-border/60 bg-muted/10 text-xs text-muted-foreground">
                <div className="flex items-center gap-2.5">
                  <Info className="w-4 h-4 text-muted-foreground/70 shrink-0" />
                  <span>{sec.emptyText}</span>
                </div>
                {sec.actionLabel && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onAuthorize(sec.actionCategory)}
                    className="h-7 text-xs text-primary hover:text-primary/80 gap-1"
                  >
                    <span>{sec.actionLabel}</span>
                    <ChevronRight className="w-3 h-3" />
                  </Button>
                )}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
