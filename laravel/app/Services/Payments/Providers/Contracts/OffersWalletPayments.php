<?php

namespace App\Services\Payments\Providers\Contracts;

/**
 * A card processor that can take device wallets — Apple Pay, Google Pay —
 * on its payment page.
 *
 * A device wallet is not a way of paying CommunityHub directly. The flow is:
 *
 *     CommunityHub creates the payment
 *       → the processor's page shows the wallet button
 *       → the payer authenticates in the wallet (Face ID, fingerprint)
 *       → the wallet hands the processor a payment token
 *       → processor → card network → issuer → approved or declined
 *       → the processor tells CommunityHub (webhook)
 *
 * Apple states that the merchant's payment processor must support Apple
 * Pay, so a wallet is offered only through a provider implementing this,
 * and only for the methods it lists. These were office-confirmed channels,
 * which a resident could "pay" by saying so; nothing but a processor can
 * confirm a wallet payment.
 */
interface OffersWalletPayments
{
    /**
     * Channel keys of the wallets offered on this processor's page.
     *
     * @return list<string> e.g. ['apple_pay', 'google_pay']
     */
    public function walletMethods(): array;
}
