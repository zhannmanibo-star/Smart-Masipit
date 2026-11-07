<?php

use App\Http\Controllers\AccountVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentReleaseController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\HouseholdMembershipController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\ResidentDocumentRequestController;
use App\Http\Controllers\ResidentStatusController;
use App\Http\Controllers\StaffDocumentRequestController;
use App\Http\Controllers\WalkInDocumentRequestController;
use App\Http\Middleware\EnsureAccountApproved;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ResidentCorrectionInboxController;
use App\Http\Controllers\DocumentClosureController;
use App\Http\Controllers\HealthPatientController;
use App\Http\Controllers\HealthConsultationController;
use App\Http\Controllers\HealthHistoryController;
use App\Http\Controllers\HealthImmunizationController;
use App\Http\Controllers\HealthAccessLogController;
use App\Http\Controllers\CensusController;
use App\Http\Controllers\EmailMonitoringController;
use App\Http\Controllers\ChatbotController;

// Public landing page.
Route::view('/', 'landing')->name('home');

// Dashboard.
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', EnsureAccountApproved::class])
    ->name('dashboard');

// Administration.
Route::view('/admin', 'admin')
    ->middleware([
        'auth',
        EnsureAccountApproved::class,
        'can:access-admin',
    ])
    ->name('admin.dashboard');

    Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:access-admin',
])
    ->prefix('admin/email-monitoring')
    ->name('admin.email-monitoring.')
    ->group(function () {
        Route::get('/', [
            EmailMonitoringController::class,
            'index',
        ])->name('index');

        Route::post('/{uuid}/retry', [
            EmailMonitoringController::class,
            'retry',
        ])
            ->whereUuid('uuid')
            ->middleware('throttle:5,1')
            ->name('retry');
    });

// Resident registry.
Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:manage-residents',
])->group(function () {
    Route::get('/residents', [
        ResidentController::class,
        'index',
    ])->name('residents.index');

    Route::get('/residents/create', [
        ResidentController::class,
        'create',
    ])->name('residents.create');

    Route::get('/residents/deleted', [
        ResidentController::class,
        'deleted',
    ])
        ->middleware('can:access-admin')
        ->name('residents.deleted');

    Route::post('/residents', [
        ResidentController::class,
        'store',
    ])->name('residents.store');

    Route::get('/residents/{resident}/edit', [
        ResidentController::class,
        'edit',
    ])->name('residents.edit');

    Route::patch('/residents/{resident}', [
        ResidentController::class,
        'update',
    ])->name('residents.update');

    // Secretary/Captain authorization is enforced in the controller.
    Route::get('/residents/{resident}/status', [
        ResidentStatusController::class,
        'edit',
    ])->name('residents.status.edit');

    Route::patch('/residents/{resident}/status', [
        ResidentStatusController::class,
        'update',
    ])->name('residents.status.update');

    Route::post('/residents/{resident}/status/corrections', [
    ResidentStatusController::class,
    'requestCorrection',
])->name('residents.status.corrections.store');

Route::patch('/residents/{resident}/status/corrections/{correction}', [
    ResidentStatusController::class,
    'reviewCorrection',
])
    ->whereNumber('correction')
    ->name('residents.status.corrections.review');

    Route::delete('/residents/{resident}', [
        ResidentController::class,
        'destroy',
    ])
        ->middleware('can:access-admin')
        ->name('residents.destroy');

    Route::patch('/residents/{id}/restore', [
        ResidentController::class,
        'restore',
    ])
        ->whereNumber('id')
        ->middleware('can:access-admin')
        ->name('residents.restore');
});

// Account status.
Route::get('/account/status', function (Request $request) {
    if ($request->user()->canAccessServices()) {
        return redirect()->route('dashboard');
    }

    return view('account.status');
})
    ->middleware('auth')
    ->name('account.status');

// Account verification.
Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:verify-residents',
])->group(function () {
    Route::get('/account-verification/history', [
        AccountVerificationController::class,
        'history',
    ])->name('verification.history');

    Route::get('/account-verification', [
        AccountVerificationController::class,
        'index',
    ])->name('verification.index');

    Route::get('/account-verification/{application}', [
        AccountVerificationController::class,
        'show',
    ])->name('verification.show');

    Route::post('/account-verification/{application}/decision', [
        AccountVerificationController::class,
        'decide',
    ])->name('verification.decide');

    Route::post('/account-verification/{application}/resident', [
        AccountVerificationController::class,
        'createResident',
    ])->name('verification.create-resident');
});

// Resident document requests.
Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:request-documents',
])->group(function () {
    Route::get('/my-document-requests', [
        ResidentDocumentRequestController::class,
        'index',
    ])->name('documents.index');

    Route::get('/my-document-requests/create', [
        ResidentDocumentRequestController::class,
        'create',
    ])->name('documents.create');

    Route::post('/my-document-requests', [
        ResidentDocumentRequestController::class,
        'store',
    ])
        ->middleware('throttle:10,1')
        ->name('documents.store');
});

// Staff document processing.
Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:process-document-requests',
])
    ->prefix('staff/document-requests')
    ->name('staff.documents.')
    ->group(function () {
        Route::get('/', [
            StaffDocumentRequestController::class,
            'index',
        ])->name('index');

        Route::get('/walk-in', [
            WalkInDocumentRequestController::class,
            'create',
        ])->name('walk-in.create');

        Route::post('/walk-in', [
            WalkInDocumentRequestController::class,
            'store',
        ])
            ->middleware('throttle:10,1')
            ->name('walk-in.store');

        Route::get('/{documentRequest}', [
            StaffDocumentRequestController::class,
            'show',
        ])->name('show');

        Route::patch('/{documentRequest}/status', [
            StaffDocumentRequestController::class,
            'updateStatus',
        ])->name('status');

        Route::get('/{documentRequest}/residency-preview', [
            StaffDocumentRequestController::class,
            'previewResidency',
        ])->name('residency-preview');

        Route::post('/{documentRequest}/prepare-residency', [
            StaffDocumentRequestController::class,
            'prepareResidency',
        ])->name('prepare-residency');

        Route::patch('/{documentRequest}/release', [
            DocumentReleaseController::class,
            'update',
        ])->name('release');

        Route::patch('/{documentRequest}/close', [
    DocumentClosureController::class,
    'update',
])
    ->middleware('can:approve-document-requests')
    ->name('close');
    });

// Household registry and membership management.
Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:manage-residents',
])
    ->prefix('households')
    ->name('households.')
    ->group(function () {
        Route::get('/', [
            HouseholdController::class,
            'index',
        ])->name('index');

        Route::get('/create', [
            HouseholdController::class,
            'create',
        ])->name('create');

        Route::post('/', [
            HouseholdController::class,
            'store',
        ])->name('store');

        Route::get('/{household}', [
            HouseholdController::class,
            'show',
        ])
            ->whereNumber('household')
            ->name('show');

        Route::get('/{household}/edit', [
            HouseholdController::class,
            'edit',
        ])
            ->whereNumber('household')
            ->name('edit');

        Route::patch('/{household}', [
            HouseholdController::class,
            'update',
        ])
            ->whereNumber('household')
            ->name('update');

        Route::get('/{household}/history', [
            HouseholdController::class,
            'history',
        ])
            ->whereNumber('household')
            ->name('history');

        Route::post('/{household}/members', [
            HouseholdController::class,
            'addMember',
        ])
            ->whereNumber('household')
            ->name('members.store');

        Route::get('/{household}/manage-members', [
            HouseholdMembershipController::class,
            'manage',
        ])
            ->whereNumber('household')
            ->name('members.manage');

        Route::patch('/{household}/head', [
            HouseholdMembershipController::class,
            'changeHead',
        ])
            ->whereNumber('household')
            ->name('head.update');

        Route::post('/{household}/transfer-member', [
            HouseholdMembershipController::class,
            'transfer',
        ])
            ->whereNumber('household')
            ->name('members.transfer');
    });

Route::get('/resident-corrections', [
    ResidentCorrectionInboxController::class,
    'index',
])
    ->middleware([
        'auth',
        EnsureAccountApproved::class,
        'can:manage-residents',
    ])
    ->name('resident-corrections.index');

    Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:access-health-records',
])
    ->prefix('health/patients')
    ->name('health.patients.')
    ->group(function () {
        Route::get('/', [
            HealthPatientController::class,
            'index',
        ])->name('index');

        Route::get('/create', [
            HealthPatientController::class,
            'create',
        ])
            ->middleware('can:manage-health-records')
            ->name('create');

        Route::post('/', [
            HealthPatientController::class,
            'store',
        ])
            ->middleware([
                'can:manage-health-records',
                'throttle:10,1',
            ])
            ->name('store');

        Route::get('/{healthPatient}', [
            HealthPatientController::class,
            'show',
        ])
            ->whereNumber('healthPatient')
            ->name('show');
    });

    Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:access-health-records',
    'can:manage-health-records',
])
    ->prefix('health/patients/{healthPatient}/consultations')
    ->name('health.consultations.')
    ->where(['healthPatient' => '[0-9]+'])
    ->group(function () {
        Route::get('/', [
            HealthConsultationController::class,
            'index',
        ])->name('index');

        Route::post('/', [
            HealthConsultationController::class,
            'store',
        ])
            ->middleware('throttle:10,1')
            ->name('store');

        Route::post('/{consultation}/addendums', [
            HealthConsultationController::class,
            'addendum',
        ])
            ->whereNumber('consultation')
            ->middleware('throttle:10,1')
            ->name('addendums.store');
    });
    Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:access-health-records',
    'can:manage-health-records',
])
    ->prefix('health/patients/{healthPatient}/history')
    ->name('health.history.')
    ->where(['healthPatient' => '[0-9]+'])
    ->group(function () {
        Route::get('/', [
            HealthHistoryController::class,
            'index',
        ])->name('index');

        Route::post('/', [
            HealthHistoryController::class,
            'store',
        ])
            ->middleware('throttle:10,1')
            ->name('store');
    });

    Route::middleware([
    'auth',
    EnsureAccountApproved::class,
    'can:access-health-records',
    'can:manage-health-records',
])
    ->prefix('health/patients/{healthPatient}/immunizations')
    ->name('health.immunizations.')
    ->where(['healthPatient' => '[0-9]+'])
    ->group(function () {
        Route::get('/', [
            HealthImmunizationController::class,
            'index',
        ])->name('index');

        Route::post('/', [
            HealthImmunizationController::class,
            'store',
        ])
            ->middleware('throttle:10,1')
            ->name('store');

        Route::post('/{immunization}/corrections', [
            HealthImmunizationController::class,
            'correct',
        ])
            ->whereNumber('immunization')
            ->middleware('throttle:10,1')
            ->name('corrections.store');
    });

    Route::get('/health/access-history', [
    HealthAccessLogController::class,
    'index',
])
    ->middleware([
        'auth',
        EnsureAccountApproved::class,
        'can:access-health-records',
        'can:view-health-access-logs',
    ])
    ->name('health.access-history.index');


Route::get('/census', [
    CensusController::class,
    'index',
])
    ->middleware([
        'auth',
        EnsureAccountApproved::class,
        'can:manage-residents',
    ])
    ->name('census.index');

    Route::get('/census/print', [
    CensusController::class,
    'index',
])
    ->middleware([
        'auth',
        EnsureAccountApproved::class,
        'can:manage-residents',
    ])
    ->name('census.print');

    Route::middleware([
    'auth',
    EnsureAccountApproved::class,
])->group(function () {
    Route::view('/assistant', 'chatbot.index')
        ->name('chatbot.index');

    Route::post('/assistant/message', [
        ChatbotController::class,
        'reply',
    ])
        ->middleware('throttle:20,1')
        ->name('chatbot.reply');
});

// Pending residents may submit evidence before account approval.
Route::middleware(['auth'])->group(function () {
    Route::get('/account/identity', [
        \App\Http\Controllers\IdentityEvidenceController::class,
        'edit',
    ])->name('account.identity.edit');

    Route::post('/account/identity', [
        \App\Http\Controllers\IdentityEvidenceController::class,
        'store',
    ])
        ->middleware('throttle:5,1')
        ->name('account.identity.store');
});

// Only authorized account reviewers may view identity images.
Route::get('/account-verification/{application}/identity/{kind}/{version}', [
    \App\Http\Controllers\IdentityEvidenceController::class,
    'show',
])
    ->middleware([
        'auth',
        \App\Http\Middleware\EnsureAccountApproved::class,
        'can:verify-residents',
    ])
   ->where('kind', 'id|id-back|selfie')
    ->whereUuid('version')
    ->name('verification.identity.show');