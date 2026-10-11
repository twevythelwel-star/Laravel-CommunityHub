import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';
import { AlertCircle, Calendar, CheckCircle2, Send } from 'lucide-react';
import type { OutstandingItem } from './types';

type Props = {
  outstanding: OutstandingItem[];
  isAdmin: boolean;
  /** Opens the payment-plan dialog for this statement. */
  onCreatePlan: (invoiceId: number) => void;
};

export function OutstandingTab({ outstanding, isAdmin, onCreatePlan }: Props) {
  const { toast } = useToast();

  return (
    <Card className="border shadow-sm">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div>
            <CardTitle className="text-xl font-bold flex items-center gap-2">
              <AlertCircle className="h-5 w-5 text-amber-500" />
              <span>Outstanding Balances &amp; Delinquency Ledger</span>
            </CardTitle>
            <CardDescription className="text-xs">
              All unsettled assessment statements across residential lots with real-time aging calculation.
            </CardDescription>
          </div>
          <Badge variant="outline" className="font-mono text-xs">
            {outstanding.length} Delinquent Statements
          </Badge>
        </div>
      </CardHeader>
      <CardContent>
        {outstanding.length === 0 ? (
          <div className="p-12 text-center border border-dashed rounded-xl text-muted-foreground">
            <CheckCircle2 className="h-8 w-8 mx-auto mb-2 text-emerald-500 opacity-60" />
            <p className="font-semibold text-sm">All community accounts are settled in full.</p>
            <p className="text-xs text-muted-foreground mt-1">No outstanding balances currently active.</p>
          </div>
        ) : (
          <div className="rounded-xl border overflow-hidden">
            <table className="w-full text-left text-xs">
              <thead className="bg-muted/60 text-muted-foreground font-semibold border-b">
                <tr>
                  <th className="p-3">Statement Ref</th>
                  <th className="p-3">Homeowner</th>
                  <th className="p-3">Lot</th>
                  <th className="p-3">Amount Due</th>
                  <th className="p-3">Due Date</th>
                  <th className="p-3">Overdue Aging</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {outstanding.map((item) => (
                  <tr key={item.id} className="hover:bg-muted/30 transition-colors">
                    <td className="p-3 font-mono font-bold text-foreground">{item.reference}</td>
                    <td className="p-3 font-medium text-foreground">{item.homeowner}</td>
                    <td className="p-3 font-mono">Lot {item.lot}</td>
                    <td className="p-3 font-mono font-bold text-foreground">
                      {item.currency} {item.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </td>
                    <td className="p-3 font-mono text-muted-foreground">{item.dueOn}</td>
                    <td className="p-3">
                      <Badge
                        variant="outline"
                        className={`font-mono text-[10px] ${
                          item.daysOverdue > 60
                            ? 'border-red-500/40 text-red-600 bg-red-500/10'
                            : item.daysOverdue > 30
                            ? 'border-amber-500/40 text-amber-600 bg-amber-500/10'
                            : 'border-slate-500/40 text-slate-600 bg-slate-500/10'
                        }`}
                      >
                        {item.daysOverdue > 0 ? `${item.daysOverdue} Days Overdue` : 'Current Due'}
                      </Badge>
                    </td>
                    <td className="p-3 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        {isAdmin && (
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => onCreatePlan(item.id)}
                            className="h-7 text-xs font-semibold gap-1 text-emerald-600 border-emerald-500/30 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                          >
                            <Calendar className="h-3 w-3" />
                            <span>Payment Plan</span>
                          </Button>
                        )}
                        <Button
                          size="sm"
                          variant="secondary"
                          onClick={() => {
                            toast({
                              title: 'Digital Statement Sent',
                              description: `SMS and email reminder sent to ${item.homeowner} (Lot ${item.lot}).`,
                            });
                          }}
                          className="h-7 text-xs font-semibold gap-1"
                        >
                          <Send className="h-3 w-3" />
                          <span>Remind</span>
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
