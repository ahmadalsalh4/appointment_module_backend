<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtisanBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the bridge is OFF unless the test opts in.
        putenv('INTERNAL_ARTISAN_TOKEN');
        unset($_ENV['INTERNAL_ARTISAN_TOKEN']);
    }

    public function test_bridge_returns_404_when_token_unset()
    {
        $this->postJson('/api/internal/artisan', ['command' => 'migrate'])
            ->assertStatus(404);
    }

    public function test_bridge_rejects_missing_token()
    {
        putenv('INTERNAL_ARTISAN_TOKEN=test-token');
        $_ENV['INTERNAL_ARTISAN_TOKEN'] = 'test-token';

        $this->postJson('/api/internal/artisan', ['command' => 'migrate'])
            ->assertStatus(403);
    }

    public function test_bridge_rejects_wrong_token()
    {
        putenv('INTERNAL_ARTISAN_TOKEN=correct-token');
        $_ENV['INTERNAL_ARTISAN_TOKEN'] = 'correct-token';

        $this->withHeaders(['X-Internal-Token' => 'wrong-token'])
            ->postJson('/api/internal/artisan', ['command' => 'migrate'])
            ->assertStatus(403);
    }

    public function test_bridge_rejects_unknown_command()
    {
        putenv('INTERNAL_ARTISAN_TOKEN=token');
        $_ENV['INTERNAL_ARTISAN_TOKEN'] = 'token';

        $this->withHeaders(['X-Internal-Token' => 'token'])
            ->postJson('/api/internal/artisan', ['command' => 'shell:rm_rf'])
            ->assertStatus(422);
    }

    public function test_bridge_rejects_flag_not_in_allowlist()
    {
        putenv('INTERNAL_ARTISAN_TOKEN=token');
        $_ENV['INTERNAL_ARTISAN_TOKEN'] = 'token';

        // `migrate` only allows --force / --no-interaction; --pretend
        // would be a way to peek at SQL without running it, which is
        // technically fine, but it's not in the allowlist so it must
        // be rejected to keep the surface closed.
        $this->withHeaders(['X-Internal-Token' => 'token'])
            ->postJson('/api/internal/artisan', [
                'command' => 'migrate',
                'flags' => ['--pretend'],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Flag --pretend is not in the allowlist for command `migrate`.']);
    }

    public function test_bridge_refuses_destructive_command_without_env()
    {
        putenv('INTERNAL_ARTISAN_TOKEN=token');
        $_ENV['INTERNAL_ARTISAN_TOKEN'] = 'token';

        $this->withHeaders(['X-Internal-Token' => 'token'])
            ->postJson('/api/internal/artisan', [
                'command' => 'migrate-fresh',
                'flags' => ['--force'],
            ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Refused: CONFIRM_RESET_DB must be set to YES.']);
    }

    public function test_bridge_runs_app_status()
    {
        putenv('INTERNAL_ARTISAN_TOKEN=token');
        $_ENV['INTERNAL_ARTISAN_TOKEN'] = 'token';

        $resp = $this->withHeaders(['X-Internal-Token' => 'token'])
            ->postJson('/api/internal/artisan', ['command' => 'app-status'])
            ->assertStatus(200)
            ->assertJsonStructure(['public', 'internal', 'exit_code', 'output']);

        $this->assertSame('app:status', $resp->json('internal'));
        $this->assertSame(0, $resp->json('exit_code'));
    }

    public function test_bridge_rejects_flagshaped_args_in_args_field()
    {
        putenv('INTERNAL_ARTISAN_TOKEN=token');
        $_ENV['INTERNAL_ARTISAN_TOKEN'] = 'token';

        // A free-form `args` slot must not accept strings that look like
        // flags, even if the underlying command would otherwise take
        // them — defense in depth against an attacker who slips an
        // unguarded flag through the front door.
        $this->withHeaders(['X-Internal-Token' => 'token'])
            ->postJson('/api/internal/artisan', [
                'command' => 'dedupe-preview',
                'args' => ['--no-interaction'],
            ])
            ->assertStatus(422);
    }

    protected function tearDown(): void
    {
        putenv('INTERNAL_ARTISAN_TOKEN');
        unset($_ENV['INTERNAL_ARTISAN_TOKEN']);
        parent::tearDown();
    }
}
