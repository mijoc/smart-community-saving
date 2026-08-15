<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ArrearController;
use App\Http\Controllers\CashbookController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\ChatboardController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionPaymentRequestController;
use App\Http\Controllers\ContributionScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupContextController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupRuleController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\PassbookController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RotationController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserNotificationController;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Route;

// Public PWA manifest. It includes the logo selected by the super admin.
Route::get('/pwa-manifest.json', function () {
    $logo = SystemSetting::publicUrl(SystemSetting::get('app_logo'));
    $logoPath = $logo ? parse_url($logo, PHP_URL_PATH) : null;
    $extension = strtolower(pathinfo($logoPath ?: '', PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    return response()->json([
        'name' => SystemSetting::get('app_name', config('app.name')),
        'short_name' => SystemSetting::get('app_name', config('app.name')),
        'description' => 'Village Savings and Loan Association management system',
        'start_url' => '/dashboard',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'portrait-primary',
        'background_color' => '#ffffff',
        'theme_color' => '#206bc4',
        'lang' => app()->getLocale(),
        'icons' => [[
            'src' => $logo ?: '/icons/icon.svg',
            'sizes' => 'any',
            'type' => $mimeTypes[$extension] ?? 'image/svg+xml',
            'purpose' => 'any maskable',
        ]],
    ]);
})->name('pwa.manifest');

Route::get('/under-construction', function () {
    return response()->view('under-construction', [
        'message' => SystemSetting::get(
            'under_construction_message',
            'We are putting the finishing touches on your experience. Please check back soon.'
        ),
        'appName' => SystemSetting::get('app_name', config('app.name')),
        'appLogo' => SystemSetting::publicUrl(SystemSetting::get('app_logo')),
        'user' => request()->user(),
    ], 503);
})->name('under-construction');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// --- Auth ---
Route::middleware('guest')->group(function () {
    Route::get('/login',    [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login',   [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
    Route::post('/register',[RegisterController::class, 'register']);

    Route::get('/forgot-password',         [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password',        [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}',  [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password',         [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Language switcher (works for both guests and authenticated users)
Route::post('/locale', [LocaleController::class, 'switch'])->name('locale.switch');

// --- Group context (no active_group middleware: this is how you set it) ---
Route::middleware('auth')->group(function () {
    Route::get('/groups/select',  [GroupContextController::class, 'select'])->name('groups.select');
    Route::post('/groups/switch', [GroupContextController::class, 'switch'])->name('groups.switch');
});

// --- Authenticated app (everything below requires an active group context for non-super-admins) ---
Route::middleware(['auth', 'active.group'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Group activity feed (visible to everyone in the group)
    Route::get ('/activity',          [ActivityController::class, 'index'])->name('activity.index');
    Route::post('/activity/read-all', [ActivityController::class, 'markAllRead'])->name('activity.read');

    // AI Insights chat (member only)
    Route::post('/ai/chat', [AiChatController::class, 'chat'])->name('ai.chat');

    // Chatboard (per-group group discussion)
    Route::get   ('/chatboard',           [ChatboardController::class, 'index'])->name('chatboard.index');
    Route::post  ('/chatboard',           [ChatboardController::class, 'store'])->name('chatboard.store');
    Route::get   ('/chatboard/poll',      [ChatboardController::class, 'poll'])->name('chatboard.poll');
    Route::delete('/chatboard/{message}', [ChatboardController::class, 'destroy'])->name('chatboard.destroy');

    // Cascading location lookups (province → district → sector → cell → village)
    Route::get('locations/provinces',                  [LocationController::class, 'provinces'])->name('locations.provinces');
    Route::get('locations/districts/{provinceCode}',   [LocationController::class, 'districts'])->name('locations.districts');
    Route::get('locations/sectors/{districtCode}',     [LocationController::class, 'sectors'])->name('locations.sectors');
    Route::get('locations/cells/{sectorCode}',         [LocationController::class, 'cells'])->name('locations.cells');
    Route::get('locations/villages/{cellCode}',        [LocationController::class, 'villages'])->name('locations.villages');

    // Members
    // Printable ID cards: bulk view (filtered by active group / search /
    // status) and single-member view. Declared *before* the resource so the
    // /members/cards path isn't swallowed by /members/{member}.
    Route::get('members/cards',          [MemberController::class, 'cards'])->name('members.cards');
    Route::get('members/{member}/card',  [MemberController::class, 'cards'])->name('members.card');

    Route::resource('members', MemberController::class);
    Route::post('members/{member}/login',          [MemberController::class, 'createLogin'])->name('members.login.create');
    Route::post('members/{member}/reset-password', [MemberController::class, 'resetPassword'])->name('members.password.reset');

    // Self-service profile (any authenticated user)
    Route::get ('/profile',          [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put ('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::put ('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Member settings (any authenticated user)
    Route::get ('/settings',          [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put ('/settings',          [SettingsController::class, 'update'])->name('settings.update');
    Route::put ('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');

    // Groups (super admins manage everything; group admins manage their assigned groups)
    Route::resource('groups', GroupController::class);
    Route::get('groups/{group}/members',  [GroupController::class, 'members'])->name('groups.members');
    Route::post('groups/{group}/members', [GroupController::class, 'syncMembers'])->name('groups.members.sync');
    Route::post('groups/{group}/staff',   [GroupController::class, 'syncStaff'])->name('groups.staff.sync');

    // Group rules
    Route::get('groups/{group}/rules',                  [GroupRuleController::class, 'index'])->name('groups.rules.index');
    Route::post('groups/{group}/rules',                 [GroupRuleController::class, 'store'])->name('groups.rules.store');
    Route::put('groups/{group}/rules/{rule}',           [GroupRuleController::class, 'update'])->name('groups.rules.update');
    Route::delete('groups/{group}/rules/{rule}',        [GroupRuleController::class, 'destroy'])->name('groups.rules.destroy');

    // Schedules
    Route::get('groups/{group}/schedules',                          [ContributionScheduleController::class, 'index'])->name('groups.schedules.index');
    Route::get('groups/{group}/schedules/create',                   [ContributionScheduleController::class, 'create'])->name('groups.schedules.create');
    Route::post('groups/{group}/schedules',                         [ContributionScheduleController::class, 'store'])->name('groups.schedules.store');
    Route::get('groups/{group}/schedules/{schedule}/edit',          [ContributionScheduleController::class, 'edit'])->name('groups.schedules.edit');
    Route::put('groups/{group}/schedules/{schedule}',               [ContributionScheduleController::class, 'update'])->name('groups.schedules.update');
    Route::delete('groups/{group}/schedules/{schedule}',            [ContributionScheduleController::class, 'destroy'])->name('groups.schedules.destroy');
    Route::post('groups/{group}/schedules/{schedule}/generate',     [ContributionScheduleController::class, 'generate'])->name('groups.schedules.generate');
    Route::post('groups/{group}/schedules/catchup',                 [ContributionScheduleController::class, 'catchUp'])->name('groups.schedules.catchup');
    Route::post('groups/{group}/schedules/{schedule}/reset-pointer',[ContributionScheduleController::class, 'resetPointer'])->name('groups.schedules.reset-pointer');

    // Personal user notifications
    Route::post('notifications/mark-all-read',         [UserNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read',   [UserNotificationController::class, 'markRead'])->name('notifications.read');

    // Contribution payment requests (member-submitted, admin-approved)
    Route::get ('payment-requests',                              [ContributionPaymentRequestController::class, 'index'])->name('payment-requests.index');
    Route::post('payment-requests',                              [ContributionPaymentRequestController::class, 'store'])->name('payment-requests.store');
    Route::post('payment-requests/{paymentRequest}/approve',     [ContributionPaymentRequestController::class, 'approve'])->name('payment-requests.approve');
    Route::post('payment-requests/{paymentRequest}/reject',      [ContributionPaymentRequestController::class, 'reject'])->name('payment-requests.reject');

    // Contributions
    Route::resource('contributions', ContributionController::class)->except(['edit', 'update']);
    Route::post('contributions/{contribution}/waive', [ContributionController::class, 'waive'])->name('contributions.waive');

    // Payments
    Route::get('payments',                       [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/create',                [PaymentController::class, 'create'])->name('payments.create');
    Route::post('payments',                      [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/bulk',                  [PaymentController::class, 'bulk'])->name('payments.bulk');
    Route::post('payments/bulk',                 [PaymentController::class, 'storeBulk'])->name('payments.bulk.store');
    Route::get('payments/bulk/sheet',            [PaymentController::class, 'bulkSheet'])->name('payments.bulk.sheet');
    Route::get('payments/bulk/sheet.csv',        [PaymentController::class, 'bulkSheetCsv'])->name('payments.bulk.sheet.csv');
    Route::get('payments/lookup/contributions',  [PaymentController::class, 'lookupContributions'])->name('payments.lookup');
    Route::get('payments/{payment}',             [PaymentController::class, 'show'])->name('payments.show');
    Route::delete('payments/{payment}',          [PaymentController::class, 'destroy'])->name('payments.destroy');
    Route::post('payments/{payment}/mark-pending', [PaymentController::class, 'markPending'])->name('payments.mark-pending');

    // Arrears
    Route::get('arrears',                  [ArrearController::class, 'index'])->name('arrears.index');
    Route::post('arrears/run-engine',      [ArrearController::class, 'runEngine'])->name('arrears.run');
    Route::post('arrears/{arrear}/waive',  [ArrearController::class, 'waive'])->name('arrears.waive');

    // Passbooks
    Route::get('passbooks',                 [PassbookController::class, 'index'])->name('passbooks.index');
    Route::get('passbooks/{member}',        [PassbookController::class, 'show'])->name('passbooks.show');

    // Cashbook (group-level deposits & withdrawals)
    Route::get('cashbook/regularize/create', [CashbookController::class, 'regularizeCreate'])
        ->name('cashbook.regularize.create');
    Route::post('cashbook/regularize', [CashbookController::class, 'regularizeStore'])
        ->name('cashbook.regularize.store');
    Route::resource('cashbook', CashbookController::class);

    // Treasury — group wealth and member equity / share-out projection
    Route::get('treasury',                   [TreasuryController::class, 'index'])->name('treasury.index');
    Route::get('treasury/report/preview',    [TreasuryController::class, 'reportPreview'])->name('treasury.report.preview');
    Route::get('treasury/report/pdf',        [TreasuryController::class, 'fullReport'])->name('treasury.report.pdf');
    Route::get('treasury/members/{member}',  [TreasuryController::class, 'member'])->name('treasury.member');

    // Meetings & attendance (per-meeting roll-call with money fines for late/absent)
    Route::resource('meetings', MeetingController::class)->except(['edit', 'update']);
    Route::post('meetings/{meeting}/attendance', [MeetingController::class, 'recordAttendance'])
        ->name('meetings.attendance');
    Route::post('meetings/{meeting}/attendance/{attendance}/pay', [MeetingController::class, 'payFine'])
        ->name('meetings.attendance.pay')->scopeBindings();
    Route::post('meetings/{meeting}/toggle-status', [MeetingController::class, 'toggleStatus'])
        ->name('meetings.toggle');

    // Rotations (merry-go-round payouts)
    Route::resource('rotations', RotationController::class)->except(['edit', 'update']);
    Route::post('rotations/{rotation}/turns/{turn}/execute', [RotationController::class, 'executeTurn'])
        ->name('rotations.turns.execute')->scopeBindings();
    Route::post('rotations/{rotation}/turns/{turn}/skip',    [RotationController::class, 'skipTurn'])
        ->name('rotations.turns.skip')->scopeBindings();

    // Loans
    Route::resource('loans', LoanController::class)->except(['edit', 'update']);
    Route::post('loans/{loan}/approve',     [LoanController::class, 'approve'])->name('loans.approve');
    Route::post('loans/{loan}/reject',      [LoanController::class, 'reject'])->name('loans.reject');
    Route::post('loans/{loan}/disburse',             [LoanController::class, 'disburse'])->name('loans.disburse');
    Route::post('loans/{loan}/update-disbursed-on',  [LoanController::class, 'updateDisbursedOn'])->name('loans.updateDisbursedOn');
    Route::post('loans/{loan}/repayments',                                    [LoanController::class, 'recordRepayment'])->name('loans.repayments.store');
    Route::post('loans/{loan}/repayments/{repayment}/approve',               [LoanController::class, 'approveRepayment'])->name('loans.repayments.approve');
    Route::post('loans/{loan}/repayments/{repayment}/reject',                [LoanController::class, 'rejectRepayment'])->name('loans.repayments.reject');
    Route::delete('loans/{loan}/repayments/{repayment}',                     [LoanController::class, 'deleteRepayment'])->name('loans.repayments.destroy');
    Route::post('loans/{loan}/accrue',               [LoanController::class, 'accrueInterest'])->name('loans.accrue');
    Route::post('loans/{loan}/recalculate',          [LoanController::class, 'recalculateBalance'])->name('loans.recalculate');
    Route::post('loans/apply-interest',              [LoanController::class, 'applyInterestPenalties'])->name('loans.apply.interest');
    Route::post('loans/{loan}/mark-defaulted',       [LoanController::class, 'markDefaulted'])->name('loans.markDefaulted');
    Route::post('loans/{loan}/write-off',            [LoanController::class, 'writeOff'])->name('loans.writeOff');

    // Group Loans Report — per-member loan breakdown with totals
    Route::get('reports/group-loans', [\App\Http\Controllers\ReportController::class, 'groupLoans'])
        ->name('reports.group_loans');

    // Reports — generic export endpoint for every list page (PDF/Excel/Word/CSV)
    Route::get('reports/{report}/{format}', [\App\Http\Controllers\ReportController::class, 'export'])
        ->where(['report' => '[a-z_]+', 'format' => 'pdf|xlsx|docx|csv'])
        ->name('reports.export');

    // Monthly VSLA Financial Report — multi-section treasurer ledger
    Route::get('reports/monthly',                 [\App\Http\Controllers\MonthlyReportController::class, 'index'])->name('reports.monthly');
    Route::get('reports/monthly/print',           [\App\Http\Controllers\MonthlyReportController::class, 'print'])->name('reports.monthly.print');
    Route::get('reports/monthly/export/{format}', [\App\Http\Controllers\MonthlyReportController::class, 'export'])
        ->where('format', 'pdf|xlsx')
        ->name('reports.monthly.export');

    // Users (super admin: all users; group admin: active group only)
    Route::resource('users', UserController::class)->except('show')->middleware('role:super_admin|group_admin');

    // System settings (super admin only)
    Route::get('settings/system',  [SystemSettingController::class, 'edit'])->name('settings.system')->middleware('role:super_admin');
    Route::put('settings/system',  [SystemSettingController::class, 'update'])->name('settings.system.update')->middleware('role:super_admin');
});
