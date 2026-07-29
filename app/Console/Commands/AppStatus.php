<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AppStatus extends Command
{
    protected $signature = 'app:status';

    protected $description = 'Print a one-shot health snapshot useful in Render logs and HTTP responses.';

    public function handle(): int
    {
        $this->line('App: '.config('app.name').' ('.config('app.env').')');
        $this->line('CWD: '.base_path());
        $this->line('PHP: '.PHP_VERSION);

        try {
            DB::connection()->getPdo();
            $this->info('DB: connected ('.config('database.default').' @ '.config('database.connections.'.config('database.default').'.host').')');
        } catch (\Throwable $e) {
            $this->error('DB: connect failed — '.$e->getMessage());
            // Skip the data-probing block so the operator sees the
            // clear root cause instead of a cascade of secondary
            // exceptions.
            return self::SUCCESS;
        }

        $count = DB::table('migrations')->count();
        $this->line('Migrations applied: '.$count);

        // Last few migration batches so an operator can tell whether the
        // schema is current.
        $recent = DB::table('migrations')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['migration', 'batch']);
        $this->line('Recent migrations:');
        foreach ($recent as $row) {
            $this->line('  - '.$row->migration.' (batch '.$row->batch.')');
        }

        // Counts on the core tables to flag obvious schema drift.
        foreach (['persons', 'admin', 'staff', 'customers', 'categories', 'services', 'statuses', 'appointments'] as $table) {
            try {
                $c = DB::table($table)->count();
                $this->line("  {$table}: {$c}");
            } catch (\Throwable $e) {
                $this->warn("  {$table}: NOT YET MIGRATED ({$e->getMessage()})");
            }
        }

        return self::SUCCESS;
    }
}
