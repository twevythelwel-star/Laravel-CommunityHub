import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { useToast } from '@/hooks/use-toast';
import { router } from '@inertiajs/react';
import {
  CreditCard,
  ShieldCheck,
  Lock,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Sparkles,
  Receipt,
  Copy,
  Check,
  Radio,
  ArrowRight,
} from 'lucide-react';

export interface SavedCardMethod {
  id: number;
  methodType: string;
  brand?: string;
  lastFour?: string;
  displayName?: string;
  isDefault?: boolean;
}

export interface CardPaymentModalProps {
  isOpen: boolean;
  onClose: () => void;
  amount: number;
  currency?: string;
  invoiceId?: number;
  invoiceReference?: string;
  savedCards?: SavedCardMethod[];
  onSuccess?: (slip: any) => void;
}

type PaymentStage = 'INPUT' | 'PROCESSING' | 'SETTLED' | 'FAILED';

export function CardPaymentModal({
  isOpen,
  onClose,
  amount,
  currency = 'JMD',
  invoiceId,
  invoiceReference = 'INV-2026-75000',
  savedCards = [],
  onSuccess,
}: CardPaymentModalProps) {
  const { toast } = useToast();

  const [useSavedCard, setUseSavedCard] = useState<boolean>(savedCards.length > 0);
  const [selectedSavedCard, setSelectedSavedCard] = useState<SavedCardMethod | null>(
    savedCards.find((c) => c.isDefault) ?? savedCards[0] ?? null
  );

  const [cardholderName, setCardholderName] = useState('Alexander Wright');
  const [cardNumber, setCardNumber] = useState('4242 4242 4242 4242');
  const [expiry, setExpiry] = useState('12/28');
  const [cvc, setCvc] = useState('123');
  const [postalCode, setPostalCode] = useState('KGN 08');
  const [saveCard, setSaveCard] = useState(true);

  const [stage, setStage] = useState<PaymentStage>('INPUT');
  const [processingStatus, setProcessingStatus] = useState('Authorizing with Card Processor...');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [confirmedSlip, setConfirmedSlip] = useState<any | null>(null);
  const [copiedId, setCopiedId] = useState(false);

  // Format currency
  const formattedAmount = `${currency} $${amount.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

  // Reset state when opening
  useEffect(() => {
    if (isOpen) {
      setStage('INPUT');
      setErrorMessage(null);
      setConfirmedSlip(null);
      if (savedCards.length > 0) {
        setUseSavedCard(true);
        setSelectedSavedCard(savedCards.find((c) => c.isDefault) ?? savedCards[0] ?? null);
      } else {
        setUseSavedCard(false);
      }
    }
  }, [isOpen, savedCards]);

  // Card brand detection
  const detectedBrand = React.useMemo(() => {
    if (useSavedCard && selectedSavedCard?.brand) {
      return selectedSavedCard.brand.toUpperCase();
    }
    const cleanNum = cardNumber.replace(/\s+/g, '');
    if (cleanNum.startsWith('4')) return 'VISA';
    if (cleanNum.startsWith('51') || cleanNum.startsWith('52') || cleanNum.startsWith('53') || cleanNum.startsWith('54') || cleanNum.startsWith('55')) return 'MASTERCARD';
    if (cleanNum.startsWith('34') || cleanNum.startsWith('37')) return 'AMEX';
    if (cleanNum.startsWith('6011')) return 'KEYCARD';
    return 'VISA';
  }, [cardNumber, useSavedCard, selectedSavedCard]);

  // Handle card number formatting
  const handleCardNumberChange = (val: string) => {
    const raw = val.replace(/\D/g, '').slice(0, 16);
    const parts = raw.match(/.{1,4}/g);
    setCardNumber(parts ? parts.join(' ') : raw);
  };

  // Handle expiry formatting
  const handleExpiryChange = (val: string) => {
    const raw = val.replace(/\D/g, '').slice(0, 4);
    if (raw.length >= 3) {
      setExpiry(`${raw.slice(0, 2)}/${raw.slice(2)}`);
    } else {
      setExpiry(raw);
    }
  };

  // Test card quick selector
  const fillTestCard = (number: string, exp: string, brandName: string) => {
    setUseSavedCard(false);
    handleCardNumberChange(number);
    setExpiry(exp);
    setCvc('123');
    toast({
      title: 'Test Card Selected',
      description: `Loaded ${brandName} (${number.slice(-4)}) test credentials.`,
    });
  };

  const handleCopyTransaction = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedId(true);
    setTimeout(() => setCopiedId(false), 2000);
    toast({
      title: 'Reference Copied',
      description: `Copied "${text}" to clipboard.`,
    });
  };

  // Process Card Payment
  const handlePay = async () => {
    setErrorMessage(null);
    setStage('PROCESSING');
    setProcessingStatus('Connecting to Card Payment Network...');

    try {
      // 1. Simulate 3D Secure / Card authorization handshake
      await new Promise((r) => setTimeout(r, 700));
      setProcessingStatus('Verifying 3D Secure & Fraud Shield...');
      await new Promise((r) => setTimeout(r, 600));
      setProcessingStatus('Settling Assessment with Community Ledger...');

      // Extract last 4 and brand
      const cleanNum = useSavedCard
        ? (selectedSavedCard?.lastFour ?? '4242')
        : cardNumber.replace(/\s+/g, '');
      const lastFour = cleanNum.slice(-4) || '4242';
      const brand = detectedBrand;

      // 2. Call backend endpoint POST /dashboard/billing/card-pay
      const res = await (window as any).axios.post('/dashboard/billing/card-pay', {
        invoice_id: invoiceId,
        amount: amount,
        currency: currency,
        cardholder_name: cardholderName,
        last_four: lastFour,
        brand: brand,
        save_card: saveCard,
        payer_reference: invoiceReference,
      });

      const slip = res.data?.slip ?? res.data?.transaction;
      setConfirmedSlip(slip);
      setStage('SETTLED');

      toast({
        title: 'Card Payment Approved',
        description: `Payment ${slip.transaction_id} authorized. Statement settled and receipt issued.`,
      });

      if (onSuccess) {
        onSuccess(slip);
      }

      // Reload page props so outstanding balance updates to 0
      router.reload({ only: ['billing', 'outstanding', 'transactions'] });
    } catch (err: any) {
      setStage('FAILED');
      const msg = err?.response?.data?.message || 'Card payment could not be authorized. Please verify details.';
      setErrorMessage(msg);
      toast({
        variant: 'destructive',
        title: 'Payment Authorization Failed',
        description: msg,
      });
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-lg p-0 overflow-hidden border-border/80 shadow-2xl">
        {/* Top Header Banner */}
        <div className="bg-gradient-to-r from-primary/10 via-background to-muted/40 p-5 border-b border-border/60">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-primary/20 text-primary flex items-center justify-center font-bold shadow-sm">
                <CreditCard className="w-5 h-5" />
              </div>
              <div>
                <DialogTitle className="text-lg font-bold font-headline flex items-center gap-2">
                  Credit / Debit Card Checkout
                </DialogTitle>
                <DialogDescription className="text-xs text-muted-foreground">
                  Secure, non-custodial card processing for Cypress Bay HOA dues.
                </DialogDescription>
              </div>
            </div>
            <Badge variant="outline" className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 border-emerald-500/30 flex items-center gap-1">
              <ShieldCheck className="w-3 h-3" /> PCI DSS L1
            </Badge>
          </div>
        </div>

        <div className="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
          {/* STAGE: INPUT */}
          {stage === 'INPUT' && (
            <>
              {/* Virtual Credit Card Display */}
              <div className="relative rounded-2xl p-5 bg-gradient-to-br from-slate-900 via-zinc-900 to-indigo-950 text-white shadow-xl border border-white/10 overflow-hidden">
                {/* Background decorative watermark */}
                <div className="absolute -right-8 -bottom-8 w-40 h-40 bg-primary/10 rounded-full blur-2xl pointer-events-none" />
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="font-headline font-black text-sm tracking-wider text-slate-200">
                      COMMUNITY HUB
                    </span>
                    <Badge variant="outline" className="border-white/20 text-[9px] uppercase text-white/80 py-0">
                      RESIDENT CARD
                    </Badge>
                  </div>
                  <span className="font-mono font-black text-base tracking-widest text-primary-foreground drop-shadow">
                    {detectedBrand}
                  </span>
                </div>

                {/* EMV Chip & Contactless */}
                <div className="flex items-center gap-3 my-5">
                  <div className="w-10 h-7 rounded bg-amber-400/80 border border-amber-300/60 shadow-inner flex items-center justify-center">
                    <div className="w-6 h-4 border border-amber-600/40 rounded-sm" />
                  </div>
                  <Radio className="w-5 h-5 text-white/60 rotate-90" />
                </div>

                {/* Card Number */}
                <div className="space-y-1">
                  <span className="text-[10px] text-white/50 uppercase font-mono tracking-widest">
                    CARD NUMBER
                  </span>
                  <div className="font-mono text-lg sm:text-xl font-bold tracking-widest text-white drop-shadow">
                    {useSavedCard && selectedSavedCard
                      ? `•••• •••• •••• ${selectedSavedCard.lastFour || '4242'}`
                      : cardNumber || '•••• •••• •••• ••••'}
                  </div>
                </div>

                {/* Cardholder & Expiry */}
                <div className="flex items-center justify-between pt-4 mt-2 border-t border-white/10 text-xs">
                  <div>
                    <span className="text-[9px] text-white/50 uppercase font-mono tracking-wider block">
                      CARDHOLDER
                    </span>
                    <span className="font-semibold tracking-wide uppercase text-white/90">
                      {cardholderName || 'COMMUNITY RESIDENT'}
                    </span>
                  </div>
                  <div className="text-right">
                    <span className="text-[9px] text-white/50 uppercase font-mono tracking-wider block">
                      EXPIRES
                    </span>
                    <span className="font-mono font-semibold text-white/90">
                      {useSavedCard ? '12/28' : expiry || 'MM/YY'}
                    </span>
                  </div>
                </div>
              </div>

              {/* Saved Card Selector Option */}
              {savedCards.length > 0 && (
                <div className="p-3 bg-muted/30 rounded-xl border border-border/70 space-y-2">
                  <div className="flex items-center justify-between text-xs">
                    <span className="font-bold text-foreground">Select Payment Method:</span>
                    <span className="text-muted-foreground text-[11px]">Saved on file</span>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <button
                      type="button"
                      onClick={() => setUseSavedCard(true)}
                      className={`p-2.5 rounded-lg border text-left text-xs transition-all ${
                        useSavedCard
                          ? 'border-primary bg-primary/10 font-bold text-primary shadow-sm'
                          : 'border-border/60 bg-card hover:bg-muted/50 text-foreground'
                      }`}
                    >
                      <div className="flex items-center gap-1.5">
                        <CreditCard className="w-3.5 h-3.5" />
                        <span>Saved Card ({selectedSavedCard?.brand || 'Visa'} •••• {selectedSavedCard?.lastFour || '4242'})</span>
                      </div>
                    </button>

                    <button
                      type="button"
                      onClick={() => setUseSavedCard(false)}
                      className={`p-2.5 rounded-lg border text-left text-xs transition-all ${
                        !useSavedCard
                          ? 'border-primary bg-primary/10 font-bold text-primary shadow-sm'
                          : 'border-border/60 bg-card hover:bg-muted/50 text-foreground'
                      }`}
                    >
                      <div className="flex items-center gap-1.5">
                        <Sparkles className="w-3.5 h-3.5 text-primary" />
                        <span>Enter New Card</span>
                      </div>
                    </button>
                  </div>
                </div>
              )}

              {/* Quick Fill Test Cards */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1">
                    <Sparkles className="w-3 h-3 text-primary" /> Instant Test Cards
                  </span>
                  <span className="text-[10px] text-muted-foreground">Click to fill</span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  <button
                    type="button"
                    onClick={() => fillTestCard('4242 4242 4242 4242', '12/28', 'Stripe Visa')}
                    className="px-2.5 py-1 rounded-md bg-slate-900 text-white dark:bg-white dark:text-black font-mono text-[10px] font-bold hover:scale-[1.02] transition-transform shadow-xs"
                  >
                    ⚡ 4242 •••• •••• 4242 (Visa Test)
                  </button>
                  <button
                    type="button"
                    onClick={() => fillTestCard('5555 5555 5555 4444', '11/27', 'Mastercard Test')}
                    className="px-2.5 py-1 rounded-md bg-blue-900 text-white font-mono text-[10px] font-bold hover:scale-[1.02] transition-transform shadow-xs"
                  >
                    ⚡ 5555 •••• •••• 4444 (Mastercard)
                  </button>
                  <button
                    type="button"
                    onClick={() => fillTestCard('6011 0000 0000 1111', '10/29', 'Keycard Test')}
                    className="px-2.5 py-1 rounded-md bg-amber-700 text-white font-mono text-[10px] font-bold hover:scale-[1.02] transition-transform shadow-xs"
                  >
                    ⚡ 6011 •••• •••• 1111 (Keycard)
                  </button>
                </div>
              </div>

              {/* New Card Fields (if not using saved card) */}
              {!useSavedCard && (
                <div className="space-y-3.5 border-t border-border/60 pt-3">
                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold">Cardholder Name</Label>
                    <Input
                      value={cardholderName}
                      onChange={(e) => setCardholderName(e.target.value)}
                      placeholder="Name on card"
                      className="text-xs font-medium"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold">Card Number</Label>
                    <div className="relative">
                      <Input
                        value={cardNumber}
                        onChange={(e) => handleCardNumberChange(e.target.value)}
                        placeholder="4242 4242 4242 4242"
                        className="font-mono text-xs font-bold tracking-wider pl-9"
                        maxLength={19}
                      />
                      <CreditCard className="w-4 h-4 text-muted-foreground absolute left-3 top-2.5" />
                    </div>
                  </div>

                  <div className="grid grid-cols-3 gap-2.5">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold">Expiry</Label>
                      <Input
                        value={expiry}
                        onChange={(e) => handleExpiryChange(e.target.value)}
                        placeholder="MM/YY"
                        className="font-mono text-xs font-bold text-center"
                        maxLength={5}
                      />
                    </div>
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold">CVC / CVV</Label>
                      <Input
                        value={cvc}
                        onChange={(e) => setCvc(e.target.value.replace(/\D/g, '').slice(0, 4))}
                        placeholder="123"
                        type="password"
                        className="font-mono text-xs font-bold text-center"
                        maxLength={4}
                      />
                    </div>
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold">Postal Code</Label>
                      <Input
                        value={postalCode}
                        onChange={(e) => setPostalCode(e.target.value)}
                        placeholder="KGN 08"
                        className="text-xs text-center uppercase"
                      />
                    </div>
                  </div>

                  <div className="flex items-center space-x-2 pt-1">
                    <Checkbox
                      id="save-card-check"
                      checked={saveCard}
                      onCheckedChange={(c) => setSaveCard(Boolean(c))}
                    />
                    <label
                      htmlFor="save-card-check"
                      className="text-[11px] text-muted-foreground cursor-pointer select-none"
                    >
                      Save card token securely for future assessments (Non-custodial reference)
                    </label>
                  </div>
                </div>
              )}

              {/* Assessment Breakdown Box */}
              <div className="p-3 bg-muted/40 rounded-xl border border-border/60 space-y-1.5 text-xs">
                <div className="flex justify-between items-center text-muted-foreground">
                  <span>Statement Ref:</span>
                  <span className="font-mono font-semibold text-foreground">{invoiceReference}</span>
                </div>
                <div className="flex justify-between items-center text-muted-foreground">
                  <span>Assessment Total:</span>
                  <span>{formattedAmount}</span>
                </div>
                <div className="flex justify-between items-center text-muted-foreground">
                  <span>Processing Fee:</span>
                  <span className="text-emerald-600 dark:text-emerald-400 font-semibold">$0.00 (HOA Absorbed)</span>
                </div>
                <div className="border-t border-border/50 pt-1.5 flex justify-between items-center font-bold text-foreground">
                  <span>Total Amount Due:</span>
                  <span className="text-sm text-primary font-black">{formattedAmount}</span>
                </div>
              </div>

              {/* Security Banner */}
              <div className="flex items-center gap-2 p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 dark:text-emerald-300 text-[11px]">
                <ShieldCheck className="w-4 h-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <span>
                  <strong>Non-Custodial Architecture:</strong> Card details are transmitted via PCI DSS Level 1 end-to-end encryption. CommunityHub never stores full card numbers or CVVs.
                </span>
              </div>

              {/* Action Button */}
              <Button
                onClick={handlePay}
                className="w-full h-11 text-sm font-bold bg-primary hover:bg-primary/90 text-primary-foreground shadow-md transition-all gap-2"
              >
                <Lock className="w-4 h-4" />
                Authorize & Pay {formattedAmount}
              </Button>
            </>
          )}

          {/* STAGE: PROCESSING */}
          {stage === 'PROCESSING' && (
            <div className="py-12 flex flex-col items-center justify-center text-center space-y-4">
              <div className="relative">
                <div className="w-16 h-16 rounded-full border-4 border-primary/20 border-t-primary animate-spin" />
                <CreditCard className="w-7 h-7 text-primary absolute inset-0 m-auto" />
              </div>
              <div className="space-y-1">
                <h4 className="font-bold text-base text-foreground">Processing Card Payment</h4>
                <p className="text-xs text-muted-foreground">{processingStatus}</p>
              </div>
              <Badge variant="outline" className="font-mono text-[10px] text-muted-foreground">
                INV: {invoiceReference} • {formattedAmount}
              </Badge>
            </div>
          )}

          {/* STAGE: SETTLED */}
          {stage === 'SETTLED' && (
            <div className="space-y-4 animate-in fade-in zoom-in-95">
              <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-center space-y-2">
                <div className="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto shadow-sm">
                  <CheckCircle2 className="w-7 h-7" />
                </div>
                <h4 className="font-bold text-lg text-emerald-800 dark:text-emerald-200">
                  Payment Successful!
                </h4>
                <p className="text-xs text-muted-foreground">
                  Your payment of <strong className="text-foreground">{formattedAmount}</strong> has been authorized and settled to the community ledger.
                </p>
              </div>

              {/* Canonical Slip Details */}
              <div className="rounded-xl border border-primary/20 bg-slate-50 dark:bg-slate-900/80 p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-border/60">
                  <span className="text-[11px] uppercase font-bold text-muted-foreground tracking-wider">
                    Official Transaction Slip
                  </span>
                  <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase">
                    PAID
                  </Badge>
                </div>

                <div className="grid grid-cols-2 gap-x-3 gap-y-2">
                  <div>
                    <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Transaction ID</span>
                    <div className="flex items-center gap-1 font-mono font-bold text-primary">
                      <span>{confirmedSlip?.transaction_id || 'CH-TXN-00000'}</span>
                      <button
                        type="button"
                        onClick={() => handleCopyTransaction(confirmedSlip?.transaction_id)}
                        className="text-muted-foreground hover:text-foreground"
                      >
                        {copiedId ? <Check className="w-3 h-3 text-emerald-600" /> : <Copy className="w-3 h-3" />}
                      </button>
                    </div>
                  </div>

                  <div>
                    <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Payment Method</span>
                    <span className="font-semibold text-foreground">
                      Card ({detectedBrand} •••• {cardNumber.slice(-4) || '4242'})
                    </span>
                  </div>

                  <div>
                    <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Statement Reference</span>
                    <span className="font-mono text-foreground font-semibold">{invoiceReference}</span>
                  </div>

                  <div>
                    <span className="text-muted-foreground block text-[10px] uppercase font-semibold">Status & Ledger</span>
                    <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                      Settled & Balanced
                    </span>
                  </div>
                </div>
              </div>

              <Button onClick={onClose} className="w-full font-bold">
                Done
              </Button>
            </div>
          )}

          {/* STAGE: FAILED */}
          {stage === 'FAILED' && (
            <div className="space-y-4 text-center py-4">
              <div className="w-12 h-12 rounded-full bg-destructive/10 text-destructive flex items-center justify-center mx-auto">
                <AlertCircle className="w-7 h-7" />
              </div>
              <div className="space-y-1">
                <h4 className="font-bold text-base text-foreground">Payment Authorization Refused</h4>
                <p className="text-xs text-muted-foreground">{errorMessage || 'Your card could not be charged.'}</p>
              </div>

              <div className="flex gap-2">
                <Button variant="outline" onClick={onClose} className="flex-1">
                  Cancel
                </Button>
                <Button onClick={() => setStage('INPUT')} className="flex-1 font-bold">
                  Try Again
                </Button>
              </div>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
