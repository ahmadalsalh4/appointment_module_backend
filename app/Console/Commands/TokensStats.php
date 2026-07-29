<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TokensStats extends Command
{
    protected $signature = 'tokens:stats';

    protected $description = 'Report Sanctum bearer-token usage: counts per role, recent activity, unused/expired tokens.';

    public function handle(): int
    {
        $table = config('sanctum.personal_access_tokens_table', 'personal_access_tokens');

        $this->line('Sanctum token table: '.$table);
        $this->line('Reference time (now):  '.Carbon::now()->toIso8601String());

        $total = (int) DB::table($table)->count();
        $this->info("\nTotal tokens issued:   {$total}");

        $byRole = DB::table($table)
            ->select('tokenable_type', DB::raw('count(*) AS c'))
            ->groupBy('tokenable_type')
            ->get();

        $this->line("\nTokens by authenticatable role:");
        foreach ($byRole as $row) {
            $short = match ($row->tokenable_type) {
                'App\\Models\\Customer' => 'customer',
                'App\\Models\\Staff' => 'staff',
                'App\\Models\\Admin' => 'admin',
                default => (string) $row->tokenable_type,
            };
            $this->line(sprintf("  %-10s %5d", $short, $row->c));
        }

        $used24h = (int) DB::table($table)
            ->where('last_used_at', '>=', Carbon::now()->subDay())
            ->count();
        $used7d = (int) DB::table($table)
            ->where('last_used_at', '>=', Carbon::now()->subDays(7))
            ->count();
        $everUsed = (int) DB::table($table)
            ->whereNotNull('last_used_at')
            ->count();
        $unused = $total - $everUsed;

        $this->line("\nUsage stats:");
        $this->line("  Used in last 24h:  {$used24h}");
        $this->line("  Used in last 7d:   {$used7d}");
        $this->line("  Used at least once:{$everUsed}");
        $this->line("  Never used:        {$unused}");

        $expired = (int) DB::table($table)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->count();
        $liveOrFuture = (int) DB::table($table)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', Carbon::now());
            })
            ->count();
        $this->line("  Expired:           {$expired}");
        $this->line("  Still valid:       {$liveOrFuture}");

        $recent = DB::table($table)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'tokenable_type', 'tokenable_id', 'name', 'last_used_at', 'expires_at', 'created_at']);

        $this->line("\nMost recent 10 tokens:");
        $this->line(sprintf("  %-5s %-8s %-6s %-15s %-22s %-22s %s", 'id', 'role', 'user#', 'name', 'last_used_at', 'expires_at', 'created_at'));
        foreach ($recent as $r) {
            $short = match ($r->tokenable_type) {
                'App\\Models\\Customer' => 'customer',
                'App\\Models\\Staff' => 'staff',
                'App\\Models\\Admin' => 'admin',
                default => 'other',
            };
            $this->line(sprintf(
                "  %-5d %-8s %-6d %-15s %-22s %-22s %s",
                $r->id,
                $short,
                $r->tokenable_id,
                substr((string) $r->name, 0, 15),
                $r->last_used_at ?? '— never —',
                $r->expires_at ?? '— none —',
                $r->created_at,
            ));
        }

        // Top 10 most-used tokens (highest last_used_at recency is the
        // proxy we have — Sanctum doesn't store a use-count).
        $mostRecentUse = DB::table($table)
            ->orderByDesc('last_used_at')
            ->limit(10)
            ->get(['id', 'tokenable_type', 'tokenable_id', 'name', 'last_used_at']);

        $this->line("\nTop 10 most-recently-used tokens:");
        foreach ($mostRecentUse as $r) {
            $short = match ($r->tokenable_type) {
                'App\\Models\\Customer' => 'customer',
                'App\\Models\\Staff' => 'staff',
                'App\\Models\\Admin' => 'admin',
                default => 'other',
            };
            $this->line(sprintf(
                '  token #%-6d role=%-8s user=%-6d last_used_at=%s name=%s',
                $r->id,
                $short,
                $r->tokenable_id,
                $r->last_used_at,
                $r->name,
            ));
        }

        return self::SUCCESS;
    }
}
