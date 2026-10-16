import { useState } from 'react';
import { FormControl, FormDescription, FormField, FormItem, FormLabel } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Select } from '@/components/ui/select';
import {
  Users,
  MapPin,
  Building,
  Layers,
  ShieldCheck,
  Mail,
  Smartphone,
  Bell,
  Loader2,
  AlertTriangle,
  Check,
} from 'lucide-react';
import type { UseFormReturn } from 'react-hook-form';
import type { AudiencePreview, NotificationFormValues } from './notification-form-schema';

type AudienceTargetingSectionProps = {
  form: UseFormReturn<NotificationFormValues>;
  roles: string[];
  streets: string[];
  blocks: string[];
  sections: string[];
  preview: AudiencePreview | null;
  isPreviewLoading: boolean;
};

/** Who receives the announcement, with the live recipient estimate and overlap check. */
export function AudienceTargetingSection({
  form,
  roles,
  streets,
  blocks,
  sections,
  preview,
  isPreviewLoading,
}: AudienceTargetingSectionProps) {
  const [showSampleRoster, setShowSampleRoster] = useState(false);

  const targetType = form.watch('targetType');
  const targetStreet = form.watch('targetStreet');
  const targetBlock = form.watch('targetBlock');
  const targetSection = form.watch('targetSection');
  const targetRoles = form.watch('targetRoles');
  const customStreets = form.watch('customStreets');

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
            <Users className="h-4 w-4 text-primary" />
            Audience Targeting & Overlap Protection
          </h3>
          <p className="text-xs text-muted-foreground mt-0.5">
            Select community boundaries. Deduplication engine guarantees zero duplicate deliveries to the same resident.
          </p>
        </div>
      </div>

      {/* Target Type Selector Buttons */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
        {[
          { id: 'entire_community', label: 'Entire Community', icon: Building },
          { id: 'street', label: 'By Street', icon: MapPin },
          { id: 'block', label: 'By Block', icon: Layers },
          { id: 'section', label: 'By Section', icon: Building },
          { id: 'custom_group', label: 'Custom Group', icon: Users },
          { id: 'roles', label: 'User Roles', icon: ShieldCheck },
        ].map((opt) => {
          const Icon = opt.icon;
          const isSelected = targetType === opt.id;
          return (
            <button
              key={opt.id}
              type="button"
              onClick={() => form.setValue('targetType', opt.id as any)}
              className={`flex flex-col items-center justify-center p-3 rounded-lg border text-xs font-medium transition-all ${
                isSelected
                  ? 'border-primary bg-primary/10 text-primary shadow-sm font-semibold'
                  : 'border-input hover:bg-muted/50 text-muted-foreground hover:text-foreground'
              }`}
            >
              <Icon className={`h-4 w-4 mb-1.5 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`} />
              <span>{opt.label}</span>
            </button>
          );
        })}
      </div>

      {/* Sub-criteria inputs */}
      {targetType === 'street' && (
        <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
          <FormLabel className="text-xs font-semibold">Select or Enter Street</FormLabel>
          {streets.length > 0 && (
            <div className="flex flex-wrap gap-2 mb-2">
              {streets.slice(0, 10).map((st) => (
                <Badge
                  key={st}
                  variant={targetStreet === st ? 'default' : 'outline'}
                  className="cursor-pointer text-xs"
                  onClick={() => form.setValue('targetStreet', st)}
                >
                  {st}
                </Badge>
              ))}
            </div>
          )}
          <FormField
            control={form.control}
            name="targetStreet"
            render={({ field }) => (
              <FormItem>
                <FormControl>
                  <Input placeholder="Enter street name (e.g. Palm Grove Way)" {...field} />
                </FormControl>
                <FormDescription className="text-xs">
                  Delivers to all occupants registered to properties on this street.
                </FormDescription>
              </FormItem>
            )}
          />
        </div>
      )}

      {targetType === 'block' && (
        <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
          <FormLabel className="text-xs font-semibold">Select or Enter Block</FormLabel>
          {blocks.length > 0 && (
            <div className="flex flex-wrap gap-2 mb-2">
              {blocks.map((b) => (
                <Badge
                  key={b}
                  variant={targetBlock === b ? 'default' : 'outline'}
                  className="cursor-pointer text-xs"
                  onClick={() => form.setValue('targetBlock', b)}
                >
                  Block {b}
                </Badge>
              ))}
            </div>
          )}
          <FormField
            control={form.control}
            name="targetBlock"
            render={({ field }) => (
              <FormItem>
                <FormControl>
                  <Input placeholder="e.g. Block B or 12" {...field} />
                </FormControl>
              </FormItem>
            )}
          />
        </div>
      )}

      {targetType === 'section' && (
        <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
          <FormLabel className="text-xs font-semibold">Section / Property Type</FormLabel>
          {sections.length > 0 && (
            <div className="flex flex-wrap gap-2 mb-2">
              {sections.map((s) => (
                <Badge
                  key={s}
                  variant={targetSection === s ? 'default' : 'outline'}
                  className="cursor-pointer text-xs"
                  onClick={() => form.setValue('targetSection', s)}
                >
                  {s}
                </Badge>
              ))}
            </div>
          )}
          <FormField
            control={form.control}
            name="targetSection"
            render={({ field }) => (
              <FormItem>
                <FormControl>
                  <Input placeholder="e.g. Residential, Villas, Townhouses" {...field} />
                </FormControl>
              </FormItem>
            )}
          />
        </div>
      )}

      {targetType === 'custom_group' && (
        <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
          <FormLabel className="text-xs font-semibold">Select Permitted Streets for Custom Group</FormLabel>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {streets.map((st) => (
              <label key={st} className="flex items-center gap-2 text-xs cursor-pointer p-1.5 rounded hover:bg-muted">
                <Checkbox
                  checked={customStreets.includes(st)}
                  onCheckedChange={(checked) => {
                    const updated = checked
                      ? [...customStreets, st]
                      : customStreets.filter((s) => s !== st);
                    form.setValue('customStreets', updated);
                  }}
                />
                <span className="truncate">{st}</span>
              </label>
            ))}
          </div>
        </div>
      )}

      {targetType === 'roles' && (
        <div className="p-4 rounded-lg border bg-muted/10 space-y-3">
          <FormLabel className="text-xs font-semibold">Select Permitted Roles</FormLabel>
          <div className="flex flex-wrap gap-4 pt-1">
            {roles.map((role) => (
              <label key={role} className="flex items-center gap-2 text-sm font-normal cursor-pointer">
                <Checkbox
                  checked={targetRoles.includes(role)}
                  onCheckedChange={(checked) => {
                    const updated = checked
                      ? [...targetRoles, role]
                      : targetRoles.filter((r) => r !== role);
                    form.setValue('targetRoles', updated);
                  }}
                />
                {role}
              </label>
            ))}
          </div>
        </div>
      )}

      {/* Audience Preview & Overlap Detection Box */}
      <div className="rounded-xl border p-4 bg-card/60 space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3 text-xs">
          <div className="flex items-center gap-2">
            <Users className="h-4 w-4 text-primary" />
            <span className="font-semibold text-foreground">Estimated Unique Recipients:</span>
            {isPreviewLoading ? (
              <span className="flex items-center gap-1 text-muted-foreground">
                <Loader2 className="h-3 w-3 animate-spin" /> Calculating...
              </span>
            ) : preview ? (
              <span className="font-bold text-foreground text-sm">
                {preview.uniqueRecipients} {preview.uniqueRecipients === 1 ? 'Recipient' : 'Recipients'}
              </span>
            ) : (
              <span className="text-muted-foreground">All eligible members</span>
            )}
          </div>

          {preview && (
            <div className="flex items-center gap-3 text-muted-foreground text-[11px]">
              <span className="flex items-center gap-1">
                <Bell className="h-3.5 w-3.5 text-primary" />
                In-App: <strong className="text-foreground">{preview.inAppCount}</strong>
              </span>
              <span className="flex items-center gap-1">
                <Mail className="h-3.5 w-3.5 text-sky-500" />
                Email: <strong className="text-foreground">{preview.emailCount}</strong>
              </span>
              <span className="flex items-center gap-1">
                <Smartphone className="h-3.5 w-3.5 text-emerald-500" />
                SMS/WA: <strong className="text-foreground">{preview.phoneCount}</strong>
              </span>
            </div>
          )}
        </div>

        {/* Overlap Detection Alert */}
        {preview?.hasOverlaps ? (
          <div className="p-3 rounded-lg border border-amber-500/30 bg-amber-500/10 flex items-center justify-between text-xs">
            <div className="flex items-center gap-2 text-amber-700 dark:text-amber-400">
              <AlertTriangle className="h-4 w-4 shrink-0" />
              <span>
                <strong>Overlap Detected:</strong> {preview.overlapCount} resident(s) match multiple targeting boundaries. Automatic deduplication prevents duplicate notifications.
              </span>
            </div>
            <Badge variant="outline" className="border-emerald-500/40 text-emerald-600 bg-emerald-500/10 font-mono text-[10px]">
              Deduplication Active
            </Badge>
          </div>
        ) : preview ? (
          <div className="p-2.5 rounded-lg border border-emerald-500/20 bg-emerald-500/5 flex items-center gap-2 text-xs text-emerald-600">
            <Check className="h-3.5 w-3.5" />
            <span>Clean targeting boundaries: No overlapping resident duplicates detected.</span>
          </div>
        ) : null}

        {/* Sample Roster Drawer Toggle */}
        {preview?.sample && preview.sample.length > 0 && (
          <div className="pt-2 border-t border-border/50">
            <div className="flex items-center justify-between">
              <button
                type="button"
                onClick={() => setShowSampleRoster(!showSampleRoster)}
                className="text-xs text-primary hover:underline font-medium"
              >
                {showSampleRoster ? 'Hide Sample Recipient Roster' : `Preview Sample Recipients (${preview.sample.length})`}
              </button>
              <span className="text-[11px] text-muted-foreground">Snapshot verification</span>
            </div>

            {showSampleRoster && (
              <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                {preview.sample.map((s) => (
                  <div key={s.id} className="p-2 rounded-lg border bg-background text-[11px] space-y-1">
                    <p className="font-semibold text-foreground truncate">{s.name}</p>
                    <p className="text-muted-foreground text-[10px] truncate">{s.street} &bull; {s.lot}</p>
                    <div className="flex items-center gap-1 text-[9px] font-mono">
                      <span className="text-primary font-bold">IN-APP</span>
                      {s.hasEmail && <span className="text-sky-500">&bull; EMAIL</span>}
                      {s.hasPhone && <span className="text-emerald-500">&bull; PHONE</span>}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
