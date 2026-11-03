<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CensusController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user()?->fresh();

        abort_unless(
            $actor && $actor->canAccessServices(),
            403
        );

        Gate::forUser($actor)->authorize('manage-residents');

        $generatedAt = CarbonImmutable::now('Asia/Manila');
        $today = $generatedAt->startOfDay();

        $report = DB::transaction(function () use ($today) {
            $activeCount = 0;

            $sexCounts = [
                'Male' => 0,
                'Female' => 0,
                'Other / not recorded' => 0,
            ];

            $ageCounts = [
                '0–4 years' => 0,
                '5–9 years' => 0,
                '10–14 years' => 0,
                '15–17 years' => 0,
                '18–29 years' => 0,
                '30–59 years' => 0,
                '60 years and above' => 0,
                'Birth date needs review' => 0,
            ];

            $sitioCounts = [];

            // Process records in batches instead of loading the
            // entire resident registry into memory.
            $residents = Resident::query()
                ->whereNull('date_of_death')
                ->select([
                    'id',
                    'birth_date',
                    'sex',
                    'sitio',
                ])
                ->lazyById(500);

            foreach ($residents as $resident) {
                $activeCount++;

                $sex = trim((string) $resident->sex);

                if (in_array($sex, ['Male', 'Female'], true)) {
                    $sexCounts[$sex]++;
                } else {
                    $sexCounts['Other / not recorded']++;
                }

                $sitio = trim((string) $resident->sitio);

                if ($sitio === '') {
                    $sitio = 'Not recorded';
                }

                $sitioCounts[$sitio] =
                    ($sitioCounts[$sitio] ?? 0) + 1;

               $age = $this->completedAge(
    $resident->birth_date?->format('Y-m-d'),
    $today
);
               

                $ageGroup = match (true) {
                    $age === null => 'Birth date needs review',
                    $age <= 4 => '0–4 years',
                    $age <= 9 => '5–9 years',
                    $age <= 14 => '10–14 years',
                    $age <= 17 => '15–17 years',
                    $age <= 29 => '18–29 years',
                    $age <= 59 => '30–59 years',
                    default => '60 years and above',
                };

                $ageCounts[$ageGroup]++;
            }

            ksort($sitioCounts, SORT_NATURAL | SORT_FLAG_CASE);

            // These three resident groups are mutually exclusive:
            // active, unarchived deceased, and archived.
            $deceasedCount = Resident::query()
                ->whereNotNull('date_of_death')
                ->count();

            $archivedCount = Resident::onlyTrashed()->count();

            $householdCount = Household::query()->count();

            $householdsWithActiveMembers = Household::query()
                ->whereHas('memberships.resident', function ($query) {
                    $query->whereNull('residents.deleted_at')
                        ->whereNull('residents.date_of_death');
                })
                ->count();

            $householdsNeedingHeadReview = Household::query()
                ->whereDoesntHave('head', function ($query) {
                    $query->whereNull('residents.deleted_at')
                        ->whereNull('residents.date_of_death');
                })
                ->count();

            $activeResidentsWithHousehold = Resident::query()
                ->whereNull('date_of_death')
                ->whereHas('householdMembership')
                ->count();

            return [
                'activeCount' => $activeCount,
                'deceasedCount' => $deceasedCount,
                'archivedCount' => $archivedCount,
                'totalRegistryCount' =>
                    $activeCount + $deceasedCount + $archivedCount,

                'sexCounts' => $sexCounts,
                'ageCounts' => $ageCounts,
                'sitioCounts' => $sitioCounts,

                'householdCount' => $householdCount,
                'householdsWithActiveMembers' =>
                    $householdsWithActiveMembers,
                'householdsWithoutActiveMembers' =>
                    $householdCount - $householdsWithActiveMembers,
                'householdsNeedingHeadReview' =>
                    $householdsNeedingHeadReview,

                'activeResidentsWithHousehold' =>
                    $activeResidentsWithHousehold,
                'activeResidentsWithoutHousehold' =>
                    $activeCount - $activeResidentsWithHousehold,
            ];
        });

       $view = $request->routeIs('census.print')
    ? 'census.print'
    : 'census.index';

return response()
    ->view($view, array_merge($report, [
        'generatedAt' => $generatedAt,
    ]))
    ->header('Cache-Control', 'private, no-store')
    ->header('Referrer-Policy', 'no-referrer');
    }

    private function completedAge(
        mixed $rawBirthDate,
        CarbonImmutable $today
    ): ?int {
        if (! is_string($rawBirthDate) || $rawBirthDate === '') {
            return null;
        }

        try {
            $birthDate = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $rawBirthDate,
                'Asia/Manila'
            );

            if (
                ! $birthDate
                || $birthDate->format('Y-m-d') !== $rawBirthDate
                || $birthDate->greaterThan($today)
            ) {
                return null;
            }

            // Completed calendar years, not a rounded decimal age.
            return $birthDate->diff($today)->y;
        } catch (\Exception) {
            return null;
        }
    }
}