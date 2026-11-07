<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('access-admin', function (User $user): bool {
            return $user->canAccessServices()
                && $user->role === 'admin';
        });

        Gate::define('manage-residents', function (User $user): bool {
            return $user->canAccessServices()
                && in_array($user->role, [
                    'admin',
                    'secretary',
                    'captain',
                    'barangay_staff',
                ], true);
        });

        Gate::define('verify-residents', function (User $user): bool {
            return $user->canAccessServices()
                && in_array($user->role, [
                    'secretary',
                    'captain',
                ], true);
        });

        Gate::define('request-documents', function (User $user): bool {
            return $user->role === 'resident'
                && $user->canAccessServices();
        });

        Gate::define('process-document-requests', function (User $user): bool {
            return $user->canAccessServices()
                && in_array($user->role, [
                    'secretary',
                    'barangay_staff',
                    'captain',
                    'approving_officer',
                ], true);
        });

      Gate::define('approve-document-requests', function (User $user): bool {
    return $user->canAccessServices()
        && in_array($user->role, [
            'secretary',
            'captain',
            'approving_officer',
        ], true);
});

        // Health module permissions.
        Gate::define('access-health-records', function (User $user): bool {
            return $user->canAccessHealthRecords();
        });

        Gate::define('manage-health-records', function (User $user): bool {
            return $user->canAccessHealthRecords();
        });

        Gate::define('view-health-access-logs', function (User $user): bool {
    return $user->canAccessHealthRecords();
});
    }
}