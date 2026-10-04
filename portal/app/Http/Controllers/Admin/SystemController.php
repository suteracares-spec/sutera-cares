<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * The server has no SSH, so artisan cannot be run there. This page is the
 * substitute: it shows which database updates a newly uploaded version
 * needs, applies them, and checks the settings that are dangerous to get
 * wrong in production. Administrators only.
 *
 * It only ever runs `migrate`, which adds; never `migrate:fresh`, which
 * would wipe every record.
 */
class SystemController extends Controller
{
    public function index(Migrator $migrator): View
    {
        return view('admin.system', [
            'pending' => $this->pending($migrator),
            'checks'  => $this->checks(),
        ]);
    }

    public function migrate(Request $request, Migrator $migrator): RedirectResponse
    {
        $pending = $this->pending($migrator);

        if ($pending === []) {
            return back()->with('status', 'The database is already up to date.');
        }

        Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        AuditLog::record($request, 'database_migrated', null, null, implode(', ', $pending));

        return back()
            ->with('status', count($pending) . ' database ' . str('update')->plural(count($pending)) . ' applied.')
            ->with('migrate_output', $output);
    }

    /** @return list<string> migration names not yet run */
    private function pending(Migrator $migrator): array
    {
        $files = $migrator->getMigrationFiles([database_path('migrations')]);

        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
    }

    /** Settings that leak data or break things if they are wrong in production. */
    private function checks(): array
    {
        $production = app()->environment('production');

        return [
            ['Environment', app()->environment(), $production,
             'APP_ENV should be "production" on the live server.'],
            ['Debug mode', config('app.debug') ? 'On' : 'Off', ! config('app.debug'),
             'APP_DEBUG must be false in production: error pages would show configuration and data.'],
            ['Site address', config('app.url'), str_starts_with((string) config('app.url'), 'https://'),
             'APP_URL should be the https:// address of the portal.'],
            ['Two-factor for staff', config('portal.require_two_factor') ? 'Required' : 'Off', (bool) config('portal.require_two_factor'),
             'PORTAL_REQUIRE_2FA is false. Turn it back on once you can sign in.'],
            ['Invoice bank details', config('billing.account_number') ? 'Set' : 'Missing', (bool) config('billing.account_number'),
             'Set BILLING_BANK_NAME, BILLING_ACCOUNT_NAME and BILLING_ACCOUNT_NUMBER in .env, or invoices show no way to pay.'],
            ['Storage writable', is_writable(storage_path('framework')) ? 'Yes' : 'No', is_writable(storage_path('framework')),
             'storage/ must be writable by the web server, or sessions and caches fail.'],
            ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP 8.2 or newer is required.'],
            ['Laravel', app()->version(), true, ''],
        ];
    }
}
