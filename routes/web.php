<?php

use App\Catalog\PageMeta;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DuplicateController;
use App\Http\Controllers\Admin\LlmModelsController;
use App\Http\Controllers\Admin\NewsRadarController;
use App\Http\Controllers\Admin\RealPolitikController;
use App\Http\Controllers\Admin\BusinessAdminController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PartyController;
use App\Http\Controllers\Admin\PoliticianAdminController;
use App\Http\Controllers\Admin\ProposalController;
use App\Http\Controllers\Admin\RelationshipAdminController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\AutoVetController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BusinessAffiliationController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PartyProfileController;
use App\Http\Controllers\PoliticianController;
use App\Http\Controllers\PublicRecordController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\SciPol\AffiliationController as SciPolAffiliationController;
use App\Http\Controllers\SciPol\CompareController as SciPolCompareController;
use App\Http\Controllers\SciPol\ExportController as SciPolExportController;
use App\Http\Controllers\SciPol\HubController as SciPolHubController;
use App\Http\Controllers\SciPol\IssueController as SciPolIssueController;
use App\Http\Controllers\SciPol\PracticeController as SciPolPracticeController;
use App\Http\Controllers\SciPol\RealPolitikController as SciPolRealPolitikController;
use App\Http\Controllers\SciPol\SuggestController as SciPolSuggestController;
use App\Http\Controllers\SciPol\TopicController as SciPolTopicController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\RealPolitik\BoardController as RealPolitikBoardController;
use App\Http\Controllers\RealPolitik\WatchlistController as RealPolitikWatchlistController;
use App\Http\Controllers\SubmissionTrackingController;
use App\Inference\ProposalQueue;
use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])
    ->middleware('throttle:attachments')
    ->name('attachments.show');

Route::get('/affiliation-attachments/{attachment}', [AttachmentController::class, 'showAffiliation'])
    ->middleware('throttle:attachments')
    ->name('affiliation-attachments.show');

Route::middleware('throttle:catalog')->group(function () {
    Route::get('/feed', FeedController::class)->name('feed');
    Route::get('/feed.xml', FeedController::class);

    Route::get('/', function (Request $request, PoliticianController $politicians) {
        $party = $request->filled('party')
            ? Party::query()->find($request->integer('party'))
            : null;
        $reports = $party
            ? $politicians->publishedReports($party->id)
            : ($request->filled('party') ? null : $politicians->publishedReports());

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => false,
            'reports' => $reports['items'] ?? [],
            'meta' => $reports['meta'] ?? PageMeta::empty(),
            'total' => $reports['total'] ?? 0,
            'party' => $party ? $party->only(['id', 'name', 'logo_url']) : null,
        ]);
    });

    Route::get('/politicians', function (Request $request, PoliticianController $politicians) {
        $catalog = $politicians->catalog($request->query('q'));

        return Inertia::render('Politicians', [
            'politicians' => $catalog['items'],
            'meta' => $catalog['meta'],
            'query' => trim((string) $request->query('q', '')),
        ]);
    })->name('politicians.index');

    Route::get('/parties', function (Request $request, PartyProfileController $profile) {
        $catalog = $profile->catalog($request->query('q'));

        return Inertia::render('Parties', [
            'parties' => $catalog['items'],
            'meta' => $catalog['meta'],
            'query' => trim((string) $request->query('q', '')),
        ]);
    })->name('parties.index');

    Route::get('/parties/{party}', function (Party $party, PartyProfileController $profile, PoliticianController $politicians) {
        $members = $politicians->memberSummaries($party->id);

        return Inertia::render('Party', [
            'party' => $profile->show($party),
            'politicians' => $members['items'],
            'meta' => $members['meta'],
        ]);
    })->name('parties.show');

    Route::get('/politicians/{politician}', function (Politician $politician, PoliticianController $politicians) {
        return Inertia::render('Politician', [
            'politician' => $politicians->show($politician),
        ]);
    })->name('politicians.show');

    Route::get('/records/{record}', [PublicRecordController::class, 'show'])
        ->name('records.show');

    Route::get('/topics/{topic}', [TopicController::class, 'show'])
        ->name('topics.show');

    Route::get('/businesses', function (Request $request, BusinessController $businesses) {
        $catalog = $businesses->catalog($request->query('q'));

        return Inertia::render('Businesses', [
            'businesses' => $catalog['items'],
            'meta' => $catalog['meta'],
            'query' => trim((string) $request->query('q', '')),
        ]);
    })->name('businesses.index');

    Route::get('/businesses/{business}', function (Business $business, BusinessController $businesses) {
        return Inertia::render('Business', [
            'business' => $businesses->show($business),
        ]);
    })->name('businesses.show');

    Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');

    Route::prefix('scipol')->name('scipol.')->middleware('web.feature:scipol')->group(function () {
        Route::get('/', SciPolHubController::class)->name('hub');
        Route::get('/bandingkan', SciPolCompareController::class)->name('compare');
        Route::get('/isu', [SciPolIssueController::class, 'index'])->name('issues.index');
        Route::get('/isu/{issue}', [SciPolIssueController::class, 'show'])
            ->where('issue', '[A-Za-z0-9_-]+')
            ->name('issues.show');
        Route::get('/afiliasi', SciPolAffiliationController::class)->name('affiliations');
        Route::get('/topik', SciPolTopicController::class)->name('topics');
        Route::get('/realpolitik', SciPolRealPolitikController::class)->name('realpolitik');
        Route::get('/latihan', SciPolPracticeController::class)->name('practice');
        Route::get('/suggest', SciPolSuggestController::class)
            ->middleware('throttle:60,1')
            ->name('suggest');
    });
});

Route::get('/scipol/ekspor', SciPolExportController::class)
    ->middleware(['web.feature:scipol', 'throttle:scipol-export'])
    ->name('scipol.export');

Route::get('/realpolitik', RealPolitikBoardController::class)
    ->middleware('web.feature:realpolitik')
    ->name('realpolitik.board');

Route::get('/search', SearchController::class)->middleware('throttle:search')->name('search');

Route::middleware('web.feature:submissions')->group(function () {
    Route::get('/reports', function () {
        return Inertia::render('Pengajuan', [
            'parties' => Party::query()->orderBy('name')->get(['id', 'name']),
            'businesses' => Business::query()->orderBy('name')->get(['id', 'name']),
        ]);
    })->name('reports.create');

    Route::get('/reports/suggest', [PoliticianController::class, 'suggest'])
        ->middleware('throttle:60,1')
        ->name('reports.suggest');

    Route::get('/reports/businesses/suggest', [BusinessController::class, 'suggest'])
        ->middleware('throttle:60,1')
        ->name('reports.businesses.suggest');

    Route::post('/reports', [PoliticianController::class, 'create'])
        ->middleware('throttle:reports')
        ->name('newReport');

    Route::post('/reports/affiliations', [BusinessAffiliationController::class, 'create'])
        ->middleware('throttle:reports')
        ->name('reports.affiliations.store');
});

Route::middleware('web.feature:tracking')->group(function () {
    Route::get('/reports/received', [SubmissionTrackingController::class, 'receipt'])->name('reports.receipt');
    Route::get('/reports/track', [SubmissionTrackingController::class, 'create'])->name('reports.track');
    Route::post('/reports/track', [SubmissionTrackingController::class, 'show'])
        ->middleware('throttle:track')
        ->name('reports.track.submit');
});

Route::middleware('web.feature:disputes')->group(function () {
    Route::get('/dispute', [DisputeController::class, 'create'])->name('dispute.create');
    Route::get('/dispute/received', [DisputeController::class, 'received'])->name('dispute.received');
    Route::get('/dispute/records', [DisputeController::class, 'records'])
        ->middleware('throttle:60,1')
        ->name('dispute.records');
    Route::get('/dispute/affiliations', [DisputeController::class, 'affiliations'])
        ->middleware('throttle:60,1')
        ->name('dispute.affiliations');
    Route::post('/dispute', [DisputeController::class, 'store'])
        ->middleware('throttle:disputes')
        ->name('dispute.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('permission:checks.perform')->group(function () {
        Route::get('/dashboard', function (PoliticianController $politicians, BusinessAffiliationController $affiliations, DisputeController $disputes) {
            return Inertia::render('Dashboard', [
                'reports' => $politicians->pending(),
                'affiliations' => $affiliations->pending(),
                'decided' => $politicians->decided(),
                'decidedAffiliations' => $affiliations->decided(),
                'proposals' => app(ProposalQueue::class)->pending(),
                'disputes' => $disputes->pending(),
            ]);
        })->name('dashboard');

        Route::post('/admin/disputes/{dispute}', [DisputeController::class, 'decide'])->name('admin.disputes.decide');

        Route::get('/reviews/affiliations/{affiliation}', function (BusinessAffiliation $affiliation, BusinessAffiliationController $controller) {
            return Inertia::render('AffiliationReview', $controller->review($affiliation));
        })->name('affiliations.review');

        Route::post('/reviews/affiliations/{affiliation}', [BusinessAffiliationController::class, 'decide'])->name('affiliations.decide');

        Route::get('/reviews/{record}', function (PublicRecord $record, PoliticianController $politicians) {
            return Inertia::render('Review', $politicians->review($record));
        })->name('reports.review');

        Route::post('/reviews/{record}', [PoliticianController::class, 'decide'])->name('reports.decide');
        Route::post('/admin/proposals/{proposal}/accept', [ProposalController::class, 'accept'])->name('admin.proposals.accept');
        Route::post('/admin/proposals/{proposal}/dismiss', [ProposalController::class, 'dismiss'])->name('admin.proposals.dismiss');

        Route::post('/reviews/{record}/autovet', [AutoVetController::class, 'runRecord'])->name('reports.autovet');
        Route::post('/reviews/affiliations/{affiliation}/autovet', [AutoVetController::class, 'runAffiliation'])->name('affiliations.autovet');
        Route::post('/admin/realpolitik/signals/{signal}/dismiss', [RealPolitikController::class, 'dismiss'])
            ->name('admin.realpolitik.dismiss');
    });

    Route::post('/realpolitik/watchlist', [RealPolitikWatchlistController::class, 'store'])
        ->middleware('web.feature:realpolitik')
        ->name('realpolitik.watchlist.store');
    Route::delete('/realpolitik/watchlist/{watchlist}', [RealPolitikWatchlistController::class, 'destroy'])
        ->middleware('web.feature:realpolitik')
        ->name('realpolitik.watchlist.destroy');

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/admin/settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::get('/admin/settings/web', [SettingsController::class, 'editWeb'])->name('admin.settings.web');
        Route::post('/admin/settings/web', [SettingsController::class, 'updateWeb'])->name('admin.settings.web.update');
        Route::get('/admin/settings/llm', [SettingsController::class, 'editLlm'])->name('admin.settings.llm');
        Route::post('/admin/settings/llm', [SettingsController::class, 'updateLlm'])->name('admin.settings.llm.update');
        Route::get('/admin/settings/autovet', [SettingsController::class, 'editAutoVet'])->name('admin.settings.autovet');
        Route::post('/admin/settings/autovet', [SettingsController::class, 'updateAutoVet'])->name('admin.settings.autovet.update');
        Route::get('/admin/settings/newsradar', [SettingsController::class, 'editNewsRadar'])->name('admin.settings.newsradar');
        Route::post('/admin/settings/newsradar', [SettingsController::class, 'updateNewsRadar'])->name('admin.settings.newsradar.update');
        Route::post('/admin/newsradar/hunt', [NewsRadarController::class, 'hunt'])->name('admin.newsradar.hunt');
        Route::get('/admin/settings/realpolitik', [SettingsController::class, 'editRealPolitik'])->name('admin.settings.realpolitik');
        Route::post('/admin/settings/realpolitik', [SettingsController::class, 'updateRealPolitik'])->name('admin.settings.realpolitik.update');
        Route::post('/admin/realpolitik/tick', [RealPolitikController::class, 'tick'])->name('admin.realpolitik.tick');
        Route::get('/admin/llm/models', [LlmModelsController::class, 'index'])->name('admin.llm.models');
        Route::get('/admin/autovet/models', [LlmModelsController::class, 'index'])->name('admin.autovet.models');
    });

    Route::middleware('permission:duplicates.manage')->group(function () {
        Route::get('/admin/duplicates', [DuplicateController::class, 'index'])->name('admin.duplicates.index');
        Route::get('/admin/duplicates/merge', [DuplicateController::class, 'show'])->name('admin.duplicates.show');
        Route::post('/admin/duplicates/merge', [DuplicateController::class, 'merge'])->name('admin.duplicates.merge');
    });

    Route::get('/admin/audits', [AuditController::class, 'index'])
        ->middleware('permission:audits.view')
        ->name('admin.audits.index');
    Route::post('/admin/audits', [AuditController::class, 'runAll'])
        ->middleware('permission:audits.rerun')
        ->name('admin.audits.run');
    Route::post('/admin/audits/records/{record}', [AuditController::class, 'run'])
        ->middleware('permission:audits.rerun')
        ->name('admin.audits.records.run');

    Route::get('/admin/pages', [AdminPageController::class, 'index'])
        ->middleware('permission:pages.add|pages.edit')
        ->name('admin.pages.index');
    Route::get('/admin/pages/new', [AdminPageController::class, 'create'])
        ->middleware('permission:pages.add')
        ->name('admin.pages.create');
    Route::post('/admin/pages', [AdminPageController::class, 'store'])
        ->middleware('permission:pages.add')
        ->name('admin.pages.store');
    Route::get('/admin/pages/{page}', [AdminPageController::class, 'edit'])
        ->middleware('permission:pages.edit')
        ->name('admin.pages.edit');
    Route::post('/admin/pages/{page}', [AdminPageController::class, 'update'])
        ->middleware('permission:pages.edit')
        ->name('admin.pages.update');
    Route::delete('/admin/pages/{page}', [AdminPageController::class, 'destroy'])
        ->middleware('permission:pages.edit')
        ->name('admin.pages.destroy');

    Route::get('/admin/businesses', [BusinessAdminController::class, 'index'])
        ->middleware('permission:businesses.add|businesses.edit')
        ->name('admin.businesses.index');
    Route::get('/admin/businesses/new', [BusinessAdminController::class, 'create'])
        ->middleware('permission:businesses.add')
        ->name('admin.businesses.create');
    Route::post('/admin/businesses', [BusinessAdminController::class, 'store'])
        ->middleware('permission:businesses.add')
        ->name('admin.businesses.store');
    Route::get('/admin/businesses/{business}', [BusinessAdminController::class, 'edit'])
        ->middleware('permission:businesses.edit')
        ->name('admin.businesses.edit');
    Route::post('/admin/businesses/{business}', [BusinessAdminController::class, 'update'])
        ->middleware('permission:businesses.edit')
        ->name('admin.businesses.update');
    Route::delete('/admin/businesses/{business}', [BusinessAdminController::class, 'destroy'])
        ->middleware('permission:businesses.edit')
        ->name('admin.businesses.destroy');

    Route::get('/admin/parties', [PartyController::class, 'index'])
        ->middleware('permission:parties.add|parties.edit')
        ->name('admin.parties.index');
    Route::get('/admin/parties/suggest', [RelationshipAdminController::class, 'suggestParties'])
        ->middleware('permission:parties.add|parties.edit')
        ->name('admin.parties.suggest');
    Route::get('/admin/parties/new', [PartyController::class, 'create'])
        ->middleware('permission:parties.add')
        ->name('admin.parties.create');
    Route::post('/admin/parties', [PartyController::class, 'store'])
        ->middleware('permission:parties.add')
        ->name('admin.parties.store');
    Route::get('/admin/parties/{party}', [PartyController::class, 'edit'])
        ->middleware('permission:parties.edit')
        ->name('admin.parties.edit');
    Route::post('/admin/parties/{party}', [PartyController::class, 'update'])
        ->middleware('permission:parties.edit')
        ->name('admin.parties.update');
    Route::delete('/admin/parties/{party}', [PartyController::class, 'destroy'])
        ->middleware('permission:parties.edit')
        ->name('admin.parties.destroy');
    Route::middleware('permission:parties.edit')->group(function () {
        Route::post('/admin/parties/{party}/tendencies', [RelationshipAdminController::class, 'storePartyTendency'])->name('admin.parties.tendencies.store');
        Route::delete('/admin/parties/{party}/tendencies/{axis}', [RelationshipAdminController::class, 'destroyPartyTendency'])->name('admin.parties.tendencies.destroy');
        Route::post('/admin/parties/{party}/links', [RelationshipAdminController::class, 'storePartyLink'])->name('admin.parties.links.store');
        Route::delete('/admin/parties/{party}/links/{partyLink}', [RelationshipAdminController::class, 'destroyPartyLink'])->name('admin.parties.links.destroy');
        Route::post('/admin/parties/{party}/issues', [RelationshipAdminController::class, 'storePartyStance'])->name('admin.parties.stances.store');
        Route::delete('/admin/parties/{party}/issues/{issueStance}', [RelationshipAdminController::class, 'destroyPartyStance'])->name('admin.parties.stances.destroy');
    });

    Route::get('/admin/politicians', [PoliticianAdminController::class, 'index'])
        ->middleware('permission:figures.add|figures.edit')
        ->name('admin.politicians.index');
    Route::get('/admin/politicians/suggest', [RelationshipAdminController::class, 'suggestPoliticians'])
        ->middleware('permission:figures.add|figures.edit')
        ->name('admin.politicians.suggest');
    Route::get('/admin/politicians/new', [PoliticianAdminController::class, 'create'])
        ->middleware('permission:figures.add')
        ->name('admin.politicians.create');
    Route::post('/admin/politicians', [PoliticianAdminController::class, 'store'])
        ->middleware('permission:figures.add')
        ->name('admin.politicians.store');
    Route::get('/admin/politicians/{politician}', [PoliticianAdminController::class, 'edit'])
        ->middleware('permission:figures.edit')
        ->name('admin.politicians.edit');
    Route::post('/admin/politicians/{politician}', [PoliticianAdminController::class, 'update'])
        ->middleware('permission:figures.edit')
        ->name('admin.politicians.update');
    Route::delete('/admin/politicians/{politician}', [PoliticianAdminController::class, 'destroy'])
        ->middleware('permission:figures.edit')
        ->name('admin.politicians.destroy');
    Route::middleware('permission:figures.edit')->group(function () {
        Route::post('/admin/politicians/{politician}/tendencies', [RelationshipAdminController::class, 'storePoliticianTendency'])->name('admin.politicians.tendencies.store');
        Route::delete('/admin/politicians/{politician}/tendencies/{axis}', [RelationshipAdminController::class, 'destroyPoliticianTendency'])->name('admin.politicians.tendencies.destroy');
        Route::post('/admin/politicians/{politician}/links', [RelationshipAdminController::class, 'storePoliticianLink'])->name('admin.politicians.links.store');
        Route::delete('/admin/politicians/{politician}/links/{politicianLink}', [RelationshipAdminController::class, 'destroyPoliticianLink'])->name('admin.politicians.links.destroy');
        Route::post('/admin/politicians/{politician}/issues', [RelationshipAdminController::class, 'storePoliticianStance'])->name('admin.politicians.stances.store');
        Route::delete('/admin/politicians/{politician}/issues/{issueStance}', [RelationshipAdminController::class, 'destroyPoliticianStance'])->name('admin.politicians.stances.destroy');
        Route::post('/admin/politicians/{politician}/affiliations', [RelationshipAdminController::class, 'storeBusinessAffiliation'])->name('admin.politicians.affiliations.store');
        Route::delete('/admin/politicians/{politician}/affiliations/{businessAffiliation}', [RelationshipAdminController::class, 'destroyBusinessAffiliation'])->name('admin.politicians.affiliations.destroy');
    });

    Route::get('/admin/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('admin.roles.index');
    Route::get('/admin/roles/new', [RoleController::class, 'create'])
        ->middleware('permission:roles.manage')
        ->name('admin.roles.create');
    Route::post('/admin/roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.manage')
        ->name('admin.roles.store');
    Route::get('/admin/roles/{role}', [RoleController::class, 'edit'])
        ->middleware('permission:roles.view')
        ->name('admin.roles.edit');
    Route::post('/admin/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.manage')
        ->name('admin.roles.update');
    Route::delete('/admin/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.manage')
        ->name('admin.roles.destroy');

    Route::get('/admin/users', [UserAccessController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('admin.users.index');
    Route::get('/admin/users/{user}', [UserAccessController::class, 'edit'])
        ->middleware('permission:users.view')
        ->name('admin.users.edit');
    Route::post('/admin/users/{user}', [UserAccessController::class, 'update'])
        ->middleware('permission:users.manage')
        ->name('admin.users.update');
});

require __DIR__.'/auth.php';
