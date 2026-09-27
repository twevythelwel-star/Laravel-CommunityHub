import { useEffect, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export type AmenityFields = {
  name: string;
  category: string;
  max_guests: number;
  opens_at: string;
  closes_at: string;
  landmark_id: number | null;
  is_active: boolean;
};

const EMPTY: AmenityFields = {
  name: '',
  category: '',
  max_guests: 10,
  opens_at: '08:00',
  closes_at: '22:00',
  landmark_id: null,
  is_active: true,
};

const NO_LANDMARK = 'none';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Present when editing. */
  initial?: AmenityFields;
  landmarks: { id: number; name: string }[];
  /** Resolves when the server accepted the change; rejects with its message. */
  onSave: (fields: AmenityFields) => Promise<void>;
};

export function AmenityForm({ open, onOpenChange, initial, landmarks, onSave }: Props) {
  const [fields, setFields] = useState<AmenityFields>(initial ?? EMPTY);
  const [error, setError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  // Reset only when the dialog opens. Re-running on every new `initial` would
  // wipe the admin's edits and the server's refusal when Inertia re-renders.
  useEffect(() => {
    if (open) {
      setFields(initial ?? EMPTY);
      setError(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const set = <K extends keyof AmenityFields>(key: K, value: AmenityFields[K]) =>
    setFields((current) => ({ ...current, [key]: value }));

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    setError(null);
    onSave(fields)
      .then(() => onOpenChange(false))
      .catch((message?: string) => setError(message ?? 'The server refused the change. Please try again.'))
      .finally(() => setIsSaving(false));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <form onSubmit={handleSubmit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{initial ? 'Edit Amenity' : 'Add Amenity'}</DialogTitle>
            <DialogDescription>
              Residents book in fixed slots: 08:00–12:00, 12:00–16:00, 16:00–20:00 and 20:00–22:00. Only slots inside the
              opening hours can be booked.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-1.5">
            <Label htmlFor="amenity-name">Name</Label>
            <Input id="amenity-name" value={fields.name} onChange={(e) => set('name', e.target.value)} maxLength={120} required />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="amenity-category">Category (optional)</Label>
              <Input
                id="amenity-category"
                value={fields.category}
                onChange={(e) => set('category', e.target.value)}
                placeholder="e.g. Park"
                maxLength={60}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="amenity-capacity">Capacity (guests)</Label>
              <Input
                id="amenity-capacity"
                type="number"
                min={1}
                max={1000}
                value={fields.max_guests}
                onChange={(e) => set('max_guests', parseInt(e.target.value) || 1)}
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="amenity-opens">Opens</Label>
              <Input id="amenity-opens" type="time" value={fields.opens_at} onChange={(e) => set('opens_at', e.target.value)} required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="amenity-closes">Closes</Label>
              <Input id="amenity-closes" type="time" value={fields.closes_at} onChange={(e) => set('closes_at', e.target.value)} required />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="amenity-landmark">Map pin (optional)</Label>
            <Select
              value={fields.landmark_id === null ? NO_LANDMARK : String(fields.landmark_id)}
              onValueChange={(value) => set('landmark_id', value === NO_LANDMARK ? null : Number(value))}
            >
              <SelectTrigger id="amenity-landmark">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NO_LANDMARK}>Not on the map</SelectItem>
                {landmarks.map((landmark) => (
                  <SelectItem key={landmark.id} value={String(landmark.id)}>
                    {landmark.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-[11px] text-muted-foreground">Linked amenities get a “Reserve” link on that pin.</p>
          </div>

          <div className="flex items-center justify-between rounded-lg border p-3">
            <div>
              <Label htmlFor="amenity-active">Open for booking</Label>
              <p className="text-[11px] text-muted-foreground">Turning this off keeps existing bookings.</p>
            </div>
            <Switch id="amenity-active" checked={fields.is_active} onCheckedChange={(checked) => set('is_active', checked)} />
          </div>

          {error && <p className="rounded-md bg-destructive/10 p-2.5 text-xs text-destructive">{error}</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSaving}>
              {isSaving ? 'Saving...' : initial ? 'Save Changes' : 'Add Amenity'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
