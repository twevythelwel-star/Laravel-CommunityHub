import { useState } from 'react';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  CheckCircle2,
  AlertTriangle,
  Send,
  Users,
  Eye,
  Check,
  X,
  Loader2,
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

export function AudiencePreviewScreen({
  streets,
  blocks,
  sections,
  community,
  onComposeWithAudience,
}: {
  streets: string[];
  blocks: string[];
  sections: string[];
  community: string;
  onComposeWithAudience: () => void;
}) {
  const { toast } = useToast();
  const [targetType, setTargetType] = useState<string>('entire_community');
  const [selectedStreet, setSelectedStreet] = useState<string>('');
  const [selectedBlock, setSelectedBlock] = useState<string>('');
  const [selectedSection, setSelectedSection] = useState<string>('');
  const [customStreets, setCustomStreets] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const [previewResult, setPreviewResult] = useState<{
    totalRecipients: number;
    uniqueRecipients: number;
    overlapCount: number;
    hasOverlaps: boolean;
    inAppCount: number;
    emailCount: number;
    phoneCount: number;
    sample: Array<{
      id: number;
      name: string;
      street: string;
      lot: string;
      hasEmail: boolean;
      hasPhone: boolean;
    }>;
  } | null>(null);

  const runAudienceCalculation = async () => {
    setIsLoading(true);
    try {
      const criteria: Record<string, any> = {};
      if (targetType === 'street' && selectedStreet) {
        criteria.street = selectedStreet;
      } else if (targetType === 'block' && selectedBlock) {
        criteria.block = selectedBlock;
      } else if (targetType === 'section' && selectedSection) {
        criteria.section = selectedSection;
      } else if (targetType === 'custom_group') {
        criteria.streets = customStreets;
        if (selectedBlock) criteria.blocks = [selectedBlock];
      }

      const res = await axios.post('/dashboard/notifications/preview-audience', {
        target_type: targetType,
        target_criteria: criteria,
      });

      setPreviewResult(res.data);
    } catch (err: any) {
      toast({
        variant: 'destructive',
        title: 'Preview Failed',
        description: err.response?.data?.message ?? 'Could not calculate audience metrics.',
      });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <Card className="border shadow-sm">
        <CardHeader>
          <CardTitle className="text-xl flex items-center gap-2">
            <Users className="h-5 w-5 text-primary" />
            <span>Audience Target & Deduplication Explorer</span>
          </CardTitle>
          <CardDescription>
            Simulate and inspect audience scope across streets, blocks, and sections before sending. Detect overlapping boundaries and guarantee single-delivery deduplication.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Target Query Simulator Controls */}
          <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 p-4 rounded-xl border bg-muted/20">
            <div>
              <label className="text-xs font-semibold text-foreground block mb-1.5">Targeting Scope</label>
              <Select value={targetType} onValueChange={(val) => setTargetType(val)}>
                <SelectTrigger className="h-9 text-xs">
                  <SelectValue placeholder="Select target scope" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="entire_community">Entire Community</SelectItem>
                  <SelectItem value="street">Specific Street</SelectItem>
                  <SelectItem value="block">Specific Block</SelectItem>
                  <SelectItem value="section">Specific Section</SelectItem>
                  <SelectItem value="custom_group">Custom Multi-Target</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {targetType === 'street' && (
              <div>
                <label className="text-xs font-semibold text-foreground block mb-1.5">Select Street</label>
                <Select value={selectedStreet} onValueChange={setSelectedStreet}>
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue placeholder="Choose street..." />
                  </SelectTrigger>
                  <SelectContent>
                    {streets.map((st) => (
                      <SelectItem key={st} value={st}>{st}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {targetType === 'block' && (
              <div>
                <label className="text-xs font-semibold text-foreground block mb-1.5">Select Block</label>
                <Select value={selectedBlock} onValueChange={setSelectedBlock}>
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue placeholder="Choose block..." />
                  </SelectTrigger>
                  <SelectContent>
                    {blocks.map((bl) => (
                      <SelectItem key={bl} value={bl}>Block {bl}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {targetType === 'section' && (
              <div>
                <label className="text-xs font-semibold text-foreground block mb-1.5">Select Section / Type</label>
                <Select value={selectedSection} onValueChange={setSelectedSection}>
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue placeholder="Choose section..." />
                  </SelectTrigger>
                  <SelectContent>
                    {sections.map((sec) => (
                      <SelectItem key={sec} value={sec}>{sec}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {targetType === 'custom_group' && (
              <>
                <div>
                  <label className="text-xs font-semibold text-foreground block mb-1.5">Target Street</label>
                  <Select
                    value={customStreets[0] ?? ''}
                    onValueChange={(val) => setCustomStreets(val ? [val] : [])}
                  >
                    <SelectTrigger className="h-9 text-xs">
                      <SelectValue placeholder="Choose street..." />
                    </SelectTrigger>
                    <SelectContent>
                      {streets.map((st) => (
                        <SelectItem key={st} value={st}>{st}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <label className="text-xs font-semibold text-foreground block mb-1.5">Target Block</label>
                  <Select value={selectedBlock} onValueChange={setSelectedBlock}>
                    <SelectTrigger className="h-9 text-xs">
                      <SelectValue placeholder="Choose block..." />
                    </SelectTrigger>
                    <SelectContent>
                      {blocks.map((bl) => (
                        <SelectItem key={bl} value={bl}>Block {bl}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </>
            )}

            <div className="flex items-end">
              <Button
                onClick={runAudienceCalculation}
                disabled={isLoading}
                className="w-full h-9 text-xs flex items-center justify-center gap-1.5"
              >
                {isLoading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Eye className="h-3.5 w-3.5" />}
                <span>Calculate Audience</span>
              </Button>
            </div>
          </div>

          {/* Results Display */}
          {previewResult && (
            <div className="space-y-4 pt-2">
              {/* Overlap & Deduplication Notice */}
              {previewResult.hasOverlaps ? (
                <div className="p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 flex items-start gap-3">
                  <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                  <div className="space-y-1">
                    <h4 className="text-sm font-semibold text-amber-800 dark:text-amber-300">
                      Overlapping Selections Detected ({previewResult.overlapCount} overlaps)
                    </h4>
                    <p className="text-xs text-amber-700/90 dark:text-amber-400/90 leading-relaxed">
                      Multiple selected criteria overlap on identical resident properties (e.g. properties located on the selected street inside the selected block).
                      <strong className="block mt-1 font-semibold">
                        &bull; Antigravity Deduplication Active: Overlapping criteria are automatically unified using unique resident IDs. Exactly {previewResult.uniqueRecipients} distinct notices will be dispatched.
                      </strong>
                    </p>
                  </div>
                </div>
              ) : (
                <div className="p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 flex items-start gap-3">
                  <CheckCircle2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                  <div className="space-y-1">
                    <h4 className="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                      Clean Audience Separation (Zero Overlaps)
                    </h4>
                    <p className="text-xs text-emerald-700/90 dark:text-emerald-400/90">
                      The selected targeting boundary contains {previewResult.uniqueRecipients} distinct recipients with zero boundary overlaps.
                    </p>
                  </div>
                </div>
              )}

              {/* KPI Metrics */}
              <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <Card className="p-3 border bg-card/60">
                  <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Raw Matches</span>
                  <div className="text-2xl font-bold mt-1">{previewResult.totalRecipients}</div>
                  <span className="text-[10px] text-muted-foreground">Before deduplication</span>
                </Card>

                <Card className="p-3 border bg-primary/5 border-primary/20">
                  <span className="text-[11px] font-medium text-primary uppercase tracking-wider">Unique Recipients</span>
                  <div className="text-2xl font-bold text-primary mt-1">{previewResult.uniqueRecipients}</div>
                  <span className="text-[10px] text-muted-foreground">Net recipients to dispatch</span>
                </Card>

                <Card className="p-3 border bg-card/60">
                  <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Email Reachable</span>
                  <div className="text-2xl font-bold text-foreground mt-1">
                    {previewResult.emailCount}{' '}
                    <span className="text-xs text-muted-foreground font-normal">
                      ({previewResult.uniqueRecipients > 0 ? Math.round((previewResult.emailCount / previewResult.uniqueRecipients) * 100) : 0}%)
                    </span>
                  </div>
                  <span className="text-[10px] text-muted-foreground">Verified emails on file</span>
                </Card>

                <Card className="p-3 border bg-card/60">
                  <span className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Phone / SMS Reachable</span>
                  <div className="text-2xl font-bold text-foreground mt-1">
                    {previewResult.phoneCount}{' '}
                    <span className="text-xs text-muted-foreground font-normal">
                      ({previewResult.uniqueRecipients > 0 ? Math.round((previewResult.phoneCount / previewResult.uniqueRecipients) * 100) : 0}%)
                    </span>
                  </div>
                  <span className="text-[10px] text-muted-foreground">Mobile numbers on file</span>
                </Card>
              </div>

              {/* Sample Recipient Roster */}
              {previewResult.sample && previewResult.sample.length > 0 && (
                <div className="space-y-2 pt-2">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-foreground">
                      Sample Recipient Roster ({previewResult.sample.length} of {previewResult.uniqueRecipients})
                    </span>
                    <Button
                      size="sm"
                      onClick={onComposeWithAudience}
                      className="h-8 text-xs flex items-center gap-1.5"
                    >
                      <Send className="h-3.5 w-3.5" />
                      <span>Compose Announcement for this Target</span>
                    </Button>
                  </div>

                  <div className="rounded-xl border overflow-hidden">
                    <Table>
                      <TableHeader className="bg-muted/40">
                        <TableRow>
                          <TableHead>Resident Name</TableHead>
                          <TableHead>Street Address</TableHead>
                          <TableHead>Lot / Block</TableHead>
                          <TableHead>In-App</TableHead>
                          <TableHead>Email on File</TableHead>
                          <TableHead>Phone on File</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {previewResult.sample.map((r) => (
                          <TableRow key={r.id}>
                            <TableCell className="font-medium text-xs">{r.name}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">{r.street || '—'}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">{r.lot || '—'}</TableCell>
                            <TableCell>
                              <Badge variant="outline" className="text-[9px] border-emerald-500/40 text-emerald-600 bg-emerald-500/10">
                                Active
                              </Badge>
                            </TableCell>
                            <TableCell>
                              {r.hasEmail ? (
                                <Check className="h-4 w-4 text-emerald-600" />
                              ) : (
                                <X className="h-4 w-4 text-muted-foreground/40" />
                              )}
                            </TableCell>
                            <TableCell>
                              {r.hasPhone ? (
                                <Check className="h-4 w-4 text-emerald-600" />
                              ) : (
                                <X className="h-4 w-4 text-muted-foreground/40" />
                              )}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
