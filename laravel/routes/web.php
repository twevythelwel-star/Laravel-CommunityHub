<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Dashboard\AccessLogController;
use App\Http\Controllers\Dashboard\AmenityBookingController;
use App\Http\Controllers\Dashboard\BillingController;
use App\Http\Controllers\Dashboard\BlocklistController;
use App\Http\Controllers\Dashboard\BoundaryController;
use App\Http\Controllers\Dashboard\CalendarController;
use App\Http\Controllers\Dashboard\ChangelogController;
use App\Http\Controllers\Dashboard\DeactivationController;
use App\Http\Controllers\Dashboard\DealsController;
use App\Http\Controllers\Dashboard\DirectoryController;
use App\Http\Controllers\Dashboard\FeedbackController;
use App\Http\Controllers\Dashboard\FundraisingController;
use App\Http\Controllers\Dashboard\GatePass\ScanGatePassController;
use App\Http\Controllers\Dashboard\GatePassController;
use App\Http\Controllers\Dashboard\GuidelinesController;
use App\Http\Controllers\Dashboard\MapController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\PaymentReturnController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Dashboard\RenterController;
use App\Http\Controllers\Dashboard\ReviewFeedbackController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\StripeCheckoutController;
use App\Http\Controllers\Dashboard\UpdateController;
use App\Http\Controllers\Dashboard\VisitorController;
use App\Http\Controllers\Dashboard\WarningController;
use App\Http\Controllers\EventRsvpController;
use App\Http\Controllers\GuestPassController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\UniversalPaymentLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages — rendered with Blade
|--------------------------------------------------------------------------
| These replace src/app/page.tsx and src/app/privacy/page.tsx. They are
| content pages with no application state, so Blade serves them without
| shipping the React bundle.
*/

Route::get('/', [PublicPageController::class, 'landing'])->name('landing');
Route::get('/privacy', [PublicPageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PublicPageController::class, 'terms'])->name('terms');
Route::get('/refunds', [PublicPageController::class, 'refunds'])->name('refunds');
Route::get('/cookies', [PublicPageController::class, 'cookies'])->name('cookies');
Route::get('/guest/pass/{token}', [GuestPassController::class, 'show'])->name('guest-pass.show');
Route::get('/guest/pass/{token}/code', [GuestPassController::class, 'code'])
    ->middleware('throttle:30,1')
    ->name('guest-pass.code');
Route::get('/guest/pass/{token}/pdf', [PdfController::class, 'downloadVisitorPass'])->name('pdf.visitor-pass');
Route::get('/pay/{token}', [UniversalPaymentLinkController::class, 'show'])->name('pay.show');
Route::get('/p/{token}', [UniversalPaymentLinkController::class, 'show'])->name('pay.short');
Route::get('/pay/{token}/poster', [UniversalPaymentLinkController::class, 'poster'])->name('pay.poster');
Route::post('/pay/{token}/process', [UniversalPaymentLinkController::class, 'process'])->name('pay.process');
Route::get('/rsvp/{token}', [EventRsvpController::class, 'show'])->name('rsvp.show');
Route::post('/rsvp/{token}', [EventRsvpController::class, 'submit'])
    ->middleware('throttle:30,1')
    ->name('rsvp.submit');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
| Replaces the mock login in src/context/auth-context.tsx.
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
});

Route::match(['get', 'post'], '/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Dashboard — rendered with Inertia + React
|--------------------------------------------------------------------------
| One route per page under src/app/dashboard/. Each maps to a component in
| resources/js/Pages/Dashboard/.
*/

Route::middleware(['auth', 'active'])->prefix('dashboard')->name('dashboard.')->group(function () {

    Route::get('/', OverviewController::class)->name('index');

    // Where a hosted payment page (WiPay) sends the payer back. The provider
    // verifies what the browser brings before anything changes; the
    // controller admits only the payment's own payer.
    Route::get('/payments/{payment}/return', PaymentReturnController::class)->name('payments.return');

    // ── Profile & personal settings ──
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/ai-consent', [ProfileController::class, 'setAiConsent'])->name('profile.ai-consent');
    Route::post('/profile/payment-preferences', [ProfileController::class, 'updatePaymentPreferences'])->name('profile.payment-preferences');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::middleware('role:Homeowner,Temporary Homeowner')->group(function () {
        Route::get('/deactivation', [DeactivationController::class, 'show'])->name('deactivation');
        Route::post('/deactivation', [DeactivationController::class, 'store'])->name('deactivation.store');
    });

    // ── Gate pass ──
    Route::get('/gate-pass', [GatePassController::class, 'index'])->name('gate-pass');
    Route::get('/gate-pass/token', [GatePassController::class, 'token'])->name('gate-pass.token');
    Route::post('/gate-pass/rotate', [GatePassController::class, 'rotate'])->name('gate-pass.rotate');
    Route::post('/gate-pass/scan', ScanGatePassController::class)
        ->middleware('can:scanPasses')
        ->name('gate-pass.scan');
    Route::post('/gate-pass/scans/{scan}/confirm', [GatePassController::class, 'confirmScan'])
        ->middleware(['can:scanPasses', 'throttle:60,1'])
        ->whereUuid('scan')
        ->name('gate-pass.scans.confirm');
    Route::get('/gate-scanner', [GatePassController::class, 'scanner'])
        ->middleware('can:scanPasses')
        ->name('gate-scanner');
    // Authorization is in the controller: security for every move, a host
    // for cancelling their own guest's pass.
    Route::post('/gate-pass/{gatePass}/transition', [GatePassController::class, 'transition'])
        ->name('gate-pass.transition');
    Route::post('/gate-pass/reissue/{user}', [GatePassController::class, 'reissue'])
        ->middleware('can:manageSecurity')
        ->name('gate-pass.reissue');
    Route::post('/gate-pass/{gatePass}/revoke', [GatePassController::class, 'revoke'])
        ->middleware('can:manageSecurity')
        ->name('gate-pass.revoke');
    Route::get('/gate-pass/pdf/{gatePass}', [PdfController::class, 'downloadGatePass'])->name('gate-pass.pdf');

    // ── Visitors & directory ──
    Route::middleware('can:accessEstateInformation')->group(function () {
        Route::get('/visitors', [VisitorController::class, 'index'])->name('visitors');
        Route::post('/visitors', [VisitorController::class, 'store'])->name('visitors.store');
        Route::post('/visitors/bulk', [VisitorController::class, 'storeBulk'])->name('visitors.bulk');
        Route::post('/visitors/invites', [EventRsvpController::class, 'createInvite'])->name('visitors.invites.create');
        Route::match(['put', 'patch'], '/visitors/{visitor}', [VisitorController::class, 'update'])->name('visitors.update');
        Route::post('/visitors/{visitor}/check-in', [VisitorController::class, 'checkIn'])->name('visitors.check-in');
        Route::post('/visitors/{visitor}/check-out', [VisitorController::class, 'checkOut'])->name('visitors.check-out');
        Route::post('/visitors/{visitor}/resend', [VisitorController::class, 'resendPass'])->name('visitors.resend');
        Route::post('/visitors/{visitor}/extend', [VisitorController::class, 'extendPass'])->name('visitors.extend');
        Route::delete('/visitors/{visitor}', [VisitorController::class, 'destroy'])->name('visitors.destroy');
    });

    /*
     | Admin-only, which is what the original always showed: the page rendered
     | an "Access Denied" card for everyone else and the sidebar offered it to
     | System Admin and Admin alone. The route was open, so those three
     | disagreed — a resident could load it and receive a payload the page then
     | refused to draw. The controller still redacts contact details for a
     | non-admin viewer; that is defence in depth behind this gate, not a
     | second access model.
     */
    Route::get('/directory', [DirectoryController::class, 'index'])
        ->middleware('can:manageUsers')
        ->name('directory');
    Route::post('/directory/staff', [DirectoryController::class, 'storeStaff'])->name('directory.staff.store');
    Route::patch('/directory/staff/{staff}', [DirectoryController::class, 'updateStaff'])->name('directory.staff.update');
    Route::delete('/directory/staff/{staff}', [DirectoryController::class, 'destroyStaff'])->name('directory.staff.destroy');
    Route::post('/directory/users', [DirectoryController::class, 'storeUser'])
        ->middleware('can:manageUsers')
        ->name('directory.users.store');
    Route::patch('/directory/users/{user}', [DirectoryController::class, 'updateUser'])
        ->middleware('can:manageUsers')
        ->name('directory.users.update');

    Route::get('/renters', [RenterController::class, 'index'])->name('renters');
    Route::post('/renters', [RenterController::class, 'store'])->name('renters.store');
    Route::match(['put', 'patch'], '/renters/{renter}', [RenterController::class, 'update'])->name('renters.update');
    Route::delete('/renters/{renter}', [RenterController::class, 'destroy'])->name('renters.destroy');

    // ── Security ──
    /*
     | `manageSecurity`, which is the audience the controller and the export
     | gate already assumed. The route was open while the page rendered "Access
     | Denied" to anyone who was not an Admin — so a Security guard, who works
     | the gate this log records, was shut out of it while a resident could load
     | a payload nothing would draw.
     */
    Route::middleware('can:manageSecurity')->group(function () {
        Route::get('/access-log', [AccessLogController::class, 'index'])->name('access-log');
        Route::get('/access-log/export', [AccessLogController::class, 'export'])->name('access-log.export');
    });

    /*
     | Read is open to any signed-in user, write is not.
     |
     | The sidebar has always offered Block List to residents, and the page has a
     | resident-only "Request Removal" action — but the GET was gated on
     | `manageBlocklist`, so the menu item 403'd and the feature was unreachable.
     | Residents now get a redacted list (name, status and date only; the reason,
     | photo and author are withheld by the controller).
     */
    Route::get('/block-list', [BlocklistController::class, 'index'])
        ->middleware('can:accessEstateInformation')
        ->name('block-list');

    Route::post('/block-list/{blocklistEntry}/request-removal', [BlocklistController::class, 'requestRemoval'])
        ->middleware('can:accessEstateInformation')
        ->whereNumber('blocklistEntry')
        ->name('block-list.request-removal');

    Route::middleware('can:manageBlocklist')->group(function () {
        Route::post('/block-list', [BlocklistController::class, 'store'])
            ->name('block-list.store');

        // Registered before the {blocklistEntry} PATCH: a wildcard segment would
        // otherwise capture the literal "requests" and fail model binding.
        Route::patch('/block-list/requests/{removalRequest}', [BlocklistController::class, 'reviewRemoval'])
            ->name('block-list.requests.review');

        Route::patch('/block-list/{blocklistEntry}', [BlocklistController::class, 'update'])
            ->whereNumber('blocklistEntry')
            ->name('block-list.update');
        Route::delete('/block-list/{blocklistEntry}', [BlocklistController::class, 'destroy'])
            ->whereNumber('blocklistEntry')
            ->name('block-list.destroy');
    });

    /*
     | Safety alerts. Any signed-in resident may raise one — the page is built
     | around resident reports, and the confirm/deny vote is the community
     | corroborating an unverified claim. Because an alert reaches everybody,
     | raising one is rate limited and `manageSecurity` can delete a false alarm.
     */
    Route::get('/warnings', [WarningController::class, 'index'])->name('warnings');
    Route::post('/warnings', [WarningController::class, 'store'])
        ->middleware('throttle:3,60')
        ->name('warnings.store');
    Route::post('/warnings/{warning}/respond', [WarningController::class, 'respond'])->name('warnings.respond');
    Route::delete('/warnings/{warning}', [WarningController::class, 'destroy'])
        ->middleware('can:manageSecurity')
        ->name('warnings.destroy');

    // ── Map & boundary ──
    Route::middleware('can:accessEstateInformation')->group(function () {
        Route::get('/map', [MapController::class, 'index'])->name('map');
        Route::post('/map/landmarks', [MapController::class, 'storeLandmark'])->name('map.landmarks.store');
        Route::delete('/map/landmarks/{landmark}', [MapController::class, 'destroyLandmark'])->name('map.landmarks.destroy');
        Route::post('/map/locate', [MapController::class, 'locate'])->name('map.locate');
        Route::post('/amenity-bookings', [AmenityBookingController::class, 'store'])->name('amenity-bookings.store');
        Route::delete('/amenity-bookings/{booking}', [AmenityBookingController::class, 'destroy'])->name('amenity-bookings.destroy');
    });

    Route::get('/map/boundary', [BoundaryController::class, 'edit'])
        ->middleware('can:viewBoundary')
        ->name('boundary');

    Route::middleware('can:manageBoundary')->group(function () {
        Route::post('/map/boundary/draft', [BoundaryController::class, 'saveDraft'])->name('boundary.draft');
        Route::post('/map/boundary/publish', [BoundaryController::class, 'publish'])->name('boundary.publish');
        Route::post('/map/boundary/replace/{boundaryConfig}', [BoundaryController::class, 'replace'])->name('boundary.replace');
        Route::delete('/map/boundary/{boundaryConfig}', [BoundaryController::class, 'destroy'])->name('boundary.destroy');
        Route::post('/map/boundary/validate', [BoundaryController::class, 'validateBoundary'])->name('boundary.validate');
        Route::get('/map/boundary/export', [BoundaryController::class, 'exportGeoJson'])->name('boundary.export');
        Route::post('/map/boundary/import', [BoundaryController::class, 'importGeoJson'])->name('boundary.import');
    });

    // ── Communications ──
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications', [NotificationController::class, 'store'])
        ->middleware('can:broadcastNotices')
        ->name('notifications.store');
    Route::post('/notifications/suggest-audience', [NotificationController::class, 'suggestAudience'])
        ->middleware('can:broadcastNotices')
        ->name('notifications.suggest-audience');
    Route::post('/notifications/broadcast', [NotificationController::class, 'broadcastEmergency'])
        ->middleware('can:broadcastNotices')
        ->name('notifications.broadcast');

    Route::get('/updates', [UpdateController::class, 'index'])
        ->middleware('can:accessCommunityLife')
        ->name('updates');
    Route::post('/updates', [UpdateController::class, 'store'])
        ->middleware('can:broadcastNotices')
        ->name('updates.store');

    Route::middleware('can:accessCommunityLife')->group(function () {
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
        Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
        Route::patch('/calendar/{communityEvent}', [CalendarController::class, 'update'])->name('calendar.update');
        Route::delete('/calendar/{communityEvent}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
    });

    Route::get('/guidelines', [GuidelinesController::class, 'index'])
        ->middleware('can:accessEstateInformation')
        ->name('guidelines');
    Route::middleware('can:broadcastNotices')->group(function () {
        Route::post('/guidelines', [GuidelinesController::class, 'store'])->name('guidelines.store');
        Route::patch('/guidelines/{guideline}', [GuidelinesController::class, 'update'])->name('guidelines.update');
        Route::delete('/guidelines/{guideline}', [GuidelinesController::class, 'destroy'])->name('guidelines.destroy');
    });

    Route::get('/changelog', [ChangelogController::class, 'index'])
        ->middleware('can:viewAppChangelog')
        ->name('changelog');
    Route::post('/changelog', [ChangelogController::class, 'store'])
        ->middleware('can:viewAppChangelog')
        ->name('changelog.store');

    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');

    Route::get('/review-feedback', [ReviewFeedbackController::class, 'index'])
        ->middleware('can:reviewFeedback')
        ->name('review-feedback');
    Route::patch('/review-feedback/{feedback}', [ReviewFeedbackController::class, 'update'])
        ->middleware('can:reviewFeedback')
        ->name('review-feedback.update');

    // ── Community life ──
    Route::get('/deals', [DealsController::class, 'index'])
        ->middleware('can:accessEstateInformation')
        ->name('deals');
    Route::post('/deals/businesses', [DealsController::class, 'storeBusiness'])
        ->middleware('can:manageUsers')
        ->name('deals.businesses.store');
    Route::delete('/deals/businesses/{business}', [DealsController::class, 'destroyBusiness'])
        ->middleware('can:manageUsers')
        ->name('deals.businesses.destroy');
    Route::post('/deals/food-apps', [DealsController::class, 'storeFoodApp'])
        ->middleware('can:manageUsers')
        ->name('deals.food-apps.store');
    Route::delete('/deals/food-apps/{foodApp}', [DealsController::class, 'destroyFoodApp'])
        ->middleware('can:manageUsers')
        ->name('deals.food-apps.destroy');
    Route::post('/deals/vouchers', [DealsController::class, 'storeVoucher'])
        ->middleware('can:manageUsers')
        ->name('deals.vouchers.store');
    Route::delete('/deals/vouchers/{voucher}', [DealsController::class, 'destroyVoucher'])
        ->middleware('can:manageUsers')
        ->name('deals.vouchers.destroy');

    Route::middleware('can:accessCommunityLife')->group(function () {
        Route::get('/fundraising', [FundraisingController::class, 'index'])->name('fundraising');
        Route::post('/fundraising', [FundraisingController::class, 'store'])
            ->middleware('can:manageFundraisers')
            ->name('fundraising.store');
        Route::post('/fundraising/{fundraiser}/donate', [FundraisingController::class, 'donate'])->name('fundraising.donate');
        Route::get('/fundraising/{fundraiser}/donate/stripe-success', [FundraisingController::class, 'donationStripeSuccess'])->name('fundraising.donate.stripe.success');
        Route::get('/fundraising/{fundraiser}/donate/stripe-cancel', [FundraisingController::class, 'donationStripeCancel'])->name('fundraising.donate.stripe.cancel');
        // Backs the card's "Enable Now", which had no handler and no endpoint.
        Route::patch('/fundraising/{fundraiser}', [FundraisingController::class, 'update'])
            ->middleware('can:manageFundraisers')
            ->name('fundraising.update');
    });

    /*
     | Payments are for administrators, Homeowners and Temporary Homeowners.
     |
     | The whole area was previously ungated, so Security and Staff could reach
     | it by URL — and `index()` calls Wallet::firstOrCreate(), so simply
     | loading the page created a wallet against an account the estate never
     | bills. The sidebar hid the link from them, which hid the entry point
     | without closing it.
     |
     | The two `can:manageBilling` routes inside stay administrator-only: those
     | change the estate's rates and settle other households' invoices.
     */
    Route::middleware('can:accessBilling')->group(function () {
        Route::get('/billing', [BillingController::class, 'index'])->name('billing');
        Route::post('/billing/pay', [BillingController::class, 'pay'])->name('billing.pay');
        Route::post('/billing/card-pay', [BillingController::class, 'processCardPayment'])->name('billing.card-pay');
        // Starts a Created payment and returns its slip. Nothing reaches the
        // ledger or the office queue from here; the limit keeps it that cheap.
        Route::post('/billing/transactions/initiate', [BillingController::class, 'initiatePayment'])
            ->middleware('throttle:30,1')
            ->name('billing.transactions.initiate');
        Route::match(['post', 'patch'], '/billing/autopay', [BillingController::class, 'updateAutoPay'])->name('billing.autopay');
        Route::post('/billing/payment-links', [BillingController::class, 'storePaymentLink'])->name('billing.payment-links.store');
        Route::post('/billing/wallet/topup', [BillingController::class, 'topUpWallet'])->name('billing.wallet.topup');
        Route::post('/billing/portal', [BillingController::class, 'customerPortal'])->name('billing.portal');
        Route::post('/billing/prorate', [BillingController::class, 'calculateProration'])->name('billing.prorate');
        /*
         | The master ledger is every household's name, lot, amount and notes
         | in one CSV. It sat on `accessBilling`, so widening that gate to
         | residents handed the estate's finances to any homeowner.
         */
        Route::middleware('can:manageBilling')->group(function () {
            Route::get('/billing/export/transactions', [BillingController::class, 'exportTransactions'])->name('billing.transactions.export');
            Route::get('/billing/export-transactions', [BillingController::class, 'exportTransactions'])->name('billing.transactions.export.alias');
        });
        Route::patch('/billing/settings', [BillingController::class, 'updateSettings'])
            ->middleware('can:manageBilling')
            ->name('billing.settings.update');
        Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'markPaid'])
            ->middleware('can:manageBilling')
            ->name('billing.invoices.pay');
        Route::get('/billing/invoices/{invoice}/pdf', [PdfController::class, 'downloadInvoice'])->name('billing.invoice.pdf');
        Route::post('/billing/invoices/{invoice}/stripe-checkout', [StripeCheckoutController::class, 'checkout'])->name('billing.stripe.checkout');
        Route::get('/billing/invoices/{invoice}/stripe-success', [StripeCheckoutController::class, 'success'])->name('billing.stripe.success');
        Route::get('/billing/invoices/{invoice}/stripe-cancel', [StripeCheckoutController::class, 'cancel'])->name('billing.stripe.cancel');
        Route::middleware('can:manageBilling')->group(function () {
            Route::post('/billing/channels/{channel}/validate', [BillingController::class, 'validateChannel'])->name('billing.channels.validate');
            Route::post('/billing/channels/{channel}/toggle', [BillingController::class, 'toggleChannel'])->name('billing.channels.toggle');
            Route::patch('/billing/channels/{channel}/account', [BillingController::class, 'updateChannelAccount'])->name('billing.channels.account');
            Route::post('/billing/payment-plans', [BillingController::class, 'storePaymentPlan'])->name('billing.payment-plans.store');
            Route::post('/billing/reconciliations', [BillingController::class, 'storeReconciliation'])->name('billing.reconciliations.store');
            // Office payments: one administrator logs receipt; verification is
            // part of storeReconciliation, by a different administrator.
            Route::post('/billing/payments/{payment}/receive', [BillingController::class, 'receivePayment'])->name('billing.payments.receive');
            // In-person card readers: registered and confirmed with the
            // provider, driven by staff; payments settle by its webhook.
            Route::post('/billing/terminals', [BillingController::class, 'registerTerminal'])->name('billing.terminals.store');
            Route::post('/billing/terminals/{terminal}/retire', [BillingController::class, 'retireTerminal'])->name('billing.terminals.retire');
            Route::post('/billing/terminals/charge', [BillingController::class, 'chargeOnTerminal'])->name('billing.terminals.charge');
            Route::post('/billing/terminals/payments/{payment}/retry', [BillingController::class, 'retryOnTerminal'])->name('billing.terminals.retry');
            Route::post('/billing/terminals/payments/{payment}/cancel', [BillingController::class, 'cancelOnTerminal'])->name('billing.terminals.cancel');
            Route::post('/billing/payments/{payment}/reject', [BillingController::class, 'rejectPayment'])->name('billing.payments.reject');
        });
        Route::get('/billing/transactions/{transaction}/receipt', [BillingController::class, 'downloadReceipt'])->name('billing.transactions.receipt');
        Route::post('/billing/transactions/{transaction}/refund', [StripeCheckoutController::class, 'refund'])
            ->middleware('can:manageBilling')
            ->name('billing.transactions.refund');
    });

    Route::middleware('can:accessCommunityLife')->group(function () {
        Route::get('/fundraising/donations/{donation}/receipt', [FundraisingController::class, 'receipt'])->name('fundraising.donation.receipt');
        Route::get('/fundraising/donation/{donation}/receipt', [FundraisingController::class, 'receipt'])->name('fundraising.donation.receipt.alias');
        Route::post('/fundraising/{fundraiser}/updates', [FundraisingController::class, 'addUpdate'])
            ->middleware('can:manageFundraisers')
            ->name('fundraising.updates.store');
        Route::post('/fundraising/donations/{donation}/refund', [FundraisingController::class, 'refund'])
            ->middleware('can:manageFundraisers')
            ->name('fundraising.donations.refund');
        Route::get('/fundraising/export/donations', [FundraisingController::class, 'exportDonations'])
            ->middleware('can:manageFundraisers')
            ->name('fundraising.donations.export');
    });
});
