<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Operator-only HTTP bridge for running whitelisted Artisan commands.
 *
 * Why this exists: Render web services don't expose a shell, so there's
 * no way to run `php artisan ...` interactively. This controller lets an
 * operator trigger a closed allowlist of safe, idempotent commands via
 * a single authenticated HTTP call from outside Render.
 *
 * Security:
 *   - Disabled unless INTERNAL_ARTISAN_TOKEN is set in the environment.
 *   - The token is compared with hash_equals() to avoid timing attacks.
 *   - Every command and result is logged at info() level for auditing.
 *   - The allowlist is closed — there is no way to run arbitrary code.
 */
class ArtisanCommandController extends Controller
{
    /**
     * Closed allowlist of artisan commands available via this endpoint.
     * Each entry maps the public name → the artisan Command name + a
     * whitelist of flags and arguments allowed per invocation.
     *
     * @var array<string, array{command: string, allowedFlags: array<int, string>, takesArgs: bool}>
     */
    private const ALLOWLIST = [
        'migrate' => [
            'command' => 'migrate',
            'allowedFlags' => ['--force', '--no-interaction'],
            'takesArgs' => false,
        ],
        'migrate-fresh' => [
            'command' => 'migrate:fresh',
            'allowedFlags' => ['--force', '--no-interaction'],
            'takesArgs' => false,
            // Refuses without CONFIRM_RESET_DB=YES in env, so an
            // accidental trigger won't wipe the production database.
            'requires' => 'CONFIRM_RESET_DB',
            'requireValue' => 'YES',
        ],
        'migrate-rollback' => [
            'command' => 'migrate:rollback',
            'allowedFlags' => ['--force', '--no-interaction'],
            'takesArgs' => false,
        ],
        'migrate-status' => [
            'command' => 'migrate:status',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'config-clear' => [
            'command' => 'config:clear',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'cache-clear' => [
            'command' => 'cache:clear',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'route-clear' => [
            'command' => 'route:clear',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'view-clear' => [
            'command' => 'view:clear',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'event-clear' => [
            'command' => 'event:clear',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'optimize' => [
            'command' => 'optimize',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'optimize-clear' => [
            'command' => 'optimize:clear',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'storage-link' => [
            'command' => 'storage:link',
            'allowedFlags' => ['--force'],
            'takesArgs' => false,
        ],
        'queue-work-once' => [
            'command' => 'queue:work',
            'allowedFlags' => ['--once', '--stop-when-empty', '--no-interaction'],
            'takesArgs' => false,
        ],
        'app-status' => [
            'command' => 'app:status',
            'allowedFlags' => [],
            'takesArgs' => false,
        ],
        'dedupe-preview' => [
            // Read-only dedupe report. Safe to invoke in production.
            'command' => 'data:dedupe-before-unique',
            'allowedFlags' => ['--dry-run'],
            'takesArgs' => true, // tables argument list
        ],
    ];

    public function run(Request $request): JsonResponse
    {
        $token = (string) env('INTERNAL_ARTISAN_TOKEN', '');
        if ($token === '') {
            // Disabled — operator hasn't opted in. Return 404 to avoid
            // advertising the route exists.
            abort(404);
        }

        $provided = (string) $request->header('X-Internal-Token', '');
        if ($provided === '' || ! hash_equals($token, $provided)) {
            Log::warning('artisan-controller: invalid token', [
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);
            abort(403, 'Invalid token.');
        }

        $key = $request->input('command');
        if (! is_string($key) || ! isset(self::ALLOWLIST[$key])) {
            // Don't echo the full allowlist — internal commands like
            // `migrate-fresh` are sensitive. Token-protected, but defense
            // in depth says to not enumerate them externally.
            return response()->json([
                'message' => 'Unknown command.',
            ], 422);
        }

        $entry = self::ALLOWLIST[$key];

        if (isset($entry['requires'])) {
            if (env($entry['requires']) !== $entry['requireValue']) {
                return response()->json([
                    'message' => "Refused: {$entry['requires']} must be set to {$entry['requireValue']}.",
                ], 409);
            }
        }

        $flags = (array) $request->input('flags', []);
        $cleanFlags = [];
        foreach ($flags as $f) {
            if (! is_string($f)) {
                return response()->json(['message' => 'flags must be strings.'], 422);
            }
            if (! in_array($f, $entry['allowedFlags'], true)) {
                return response()->json([
                    'message' => "Flag {$f} is not in the allowlist for command `{$key}`.",
                ], 422);
            }
            $cleanFlags[] = $f;
        }

        $args = [];
        if ($entry['takesArgs']) {
            $args = (array) $request->input('args', []);
            foreach ($args as $a) {
                if (! is_string($a)) {
                    return response()->json(['message' => 'args must be strings.'], 422);
                }
                // Defense in depth: never accept anything that starts with
                // a dash followed by a letter in a free-form arg slot,
                // because that's an unguarded flag. Also blocks Unicode
                // dash lookalikes (em dash, en dash, hyphen, figure dash,
                // etc.) that PHP's regex would otherwise miss.
                if (preg_match('/^[\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}\x{2015}-]{1,2}[a-zA-Z]/u', $a) === 1) {
                    return response()->json([
                        'message' => "arg `{$a}` looks like a flag; use the `flags` field instead.",
                    ], 422);
                }
            }
        }

        Log::info('artisan-controller: invoke', [
            'public' => $key,
            'internal' => $entry['command'],
            'args' => $args,
            'flags' => $cleanFlags,
            'actor_ip' => $request->ip(),
        ]);

        try {
            $exit = Artisan::call($entry['command'], array_merge(
                $args,
                collect($cleanFlags)->flatMap(fn ($f) => [$f])->all(),
            ));
            $output = Artisan::output();
        } catch (\Throwable $e) {
            // Token-protected endpoint, but never leak DB connection
            // strings, file paths, or driver internals back to the caller.
            // Full exception is logged; the response only carries a generic
            // 500 with the exception class name.
            Log::error('artisan-controller: command threw', [
                'internal' => $entry['command'],
                'exception_class' => $e::class,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'public' => $key,
                'internal' => $entry['command'],
                'exit_code' => -1,
                'output' => Artisan::output(),
                'exception_class' => $e::class,
            ], 500);
        }

        return response()->json([
            'public' => $key,
            'internal' => $entry['command'],
            'exit_code' => $exit,
            'output' => $output,
        ]);
    }
}
