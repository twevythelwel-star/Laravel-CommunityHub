<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Public payment links
    |---------------------------------------------------------------------------
    |
    | Off by default, deliberately.
    |
    | `UniversalPaymentLinkController` serves an unauthenticated page that
    | anyone holding the link can submit. Its payments are now recorded as
    | `pending` and count only once an administrator confirms them from the
    | billing ledger, so a submission can no longer settle anything by itself.
    |
    | It stays off because the page is not ready: its form still posts a card
    | channel it has no checkout for, so it needs a "report a payment" form
    | offering the office-confirmed channels before it is switched on.
    |
    */

    'public_links_enabled' => (bool) env('PAYMENT_PUBLIC_LINKS_ENABLED', false),

];
