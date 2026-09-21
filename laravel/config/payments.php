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
    | anyone holding the link can submit. None of the payment drivers take
    | money — they return a simulated reference — and the controller then
    | writes a Transaction with status `completed` and answers "Payment
    | completed successfully".
    |
    | So while this is enabled, a stranger with the link can fabricate a settled
    | payment against the community ledger. Leave it off until a driver
    | genuinely settles funds and the result is confirmed out-of-band by the
    | processor rather than by the person submitting the form.
    |
    */

    'public_links_enabled' => (bool) env('PAYMENT_PUBLIC_LINKS_ENABLED', false),

];
