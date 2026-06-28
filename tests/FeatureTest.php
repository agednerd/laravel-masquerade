<?php

namespace AgedNerd\Masquerade\Tests;

use Illuminate\Support\Facades\Event;
use AgedNerd\Masquerade\Events\MasqueradeEnded;
use AgedNerd\Masquerade\Events\MasqueradeStarted;
use AgedNerd\Masquerade\Services\MasqueradeManager;

final class FeatureTest extends TestCase
{
    public function test_it_starts_and_ends_a_masquerade(): void
    {
        $manager = app(MasqueradeManager::class);
        auth()->login($admin = $this->user('admin@example.test'));

        self::assertTrue($manager->take($admin, $this->user('user@example.test')));
        self::assertSame('user@example.test', auth()->user()->email);
        self::assertSame(1, $manager->depth());
        self::assertSame('admin@example.test', $manager->getMasquerader()->email);

        self::assertTrue($manager->leave());
        self::assertSame('admin@example.test', auth()->user()->email);
        self::assertFalse($manager->isMasquerading());
    }

    public function test_nested_masquerades_unwind_one_level_at_a_time(): void
    {
        $manager = app(MasqueradeManager::class);
        auth()->login($admin = $this->user('admin@example.test'));
        $manager->take($admin, $user = $this->user('user@example.test'));
        $manager->take($user, $blocked = $this->user('blocked@example.test'));

        self::assertSame(2, $manager->depth());
        self::assertSame($user->email, $manager->getMasquerader()->email);
        self::assertSame($admin->email, $manager->getOriginalMasquerader()->email);

        $manager->leave();
        self::assertSame($user->email, auth()->user()->email);
        self::assertSame(1, $manager->depth());
        $manager->leave();
        self::assertSame($admin->email, auth()->user()->email);
    }

    public function test_multi_guard_masquerade_restores_the_source_guard(): void
    {
        $manager = app(MasqueradeManager::class);
        auth('admin')->login($admin = $this->user('admin@example.test'));

        $manager->take($admin, $this->user('user@example.test'), 'web', false, 'admin');
        self::assertFalse(auth('admin')->check());
        self::assertSame('user@example.test', auth('web')->user()->email);

        $manager->leave();
        self::assertSame('admin@example.test', auth('admin')->user()->email);
    }

    public function test_remember_mode_creates_a_target_token_and_persists_the_stack_cookie(): void
    {
        $manager = app(MasqueradeManager::class);
        auth()->login($admin = $this->user('admin@example.test'), true);
        $manager->take($admin, $target = $this->user('user@example.test'));

        self::assertNotNull($target->fresh()->getRememberToken());
        self::assertNotEmpty(cookie()->getQueuedCookies());
    }

    public function test_events_include_guard_and_depth_audit_context(): void
    {
        Event::fake([MasqueradeStarted::class, MasqueradeEnded::class]);
        $manager = app(MasqueradeManager::class);
        auth()->login($admin = $this->user('admin@example.test'));
        $manager->take($admin, $this->user('user@example.test'));
        $manager->leave();

        Event::assertDispatched(MasqueradeStarted::class, fn ($event) => $event->sourceGuard === 'web' && $event->depth === 1);
        Event::assertDispatched(MasqueradeEnded::class, fn ($event) => $event->depth === 0);
    }

    public function test_redirect_overrides_are_flexible_but_external_urls_are_blocked(): void
    {
        $manager = app(MasqueradeManager::class);
        self::assertSame('/dashboard', $manager->getTakeRedirectTo('/dashboard'));
        self::assertSame('/', $manager->getTakeRedirectTo('https://evil.example/phish'));
        $manager->setTakeRedirectResolver(fn () => '/resolved');
        self::assertSame('/resolved', $manager->getTakeRedirectTo());
    }

    public function test_http_routes_enforce_authorization_and_use_non_get_verbs(): void
    {
        $admin = $this->user('admin@example.test');
        $user = $this->user('user@example.test');
        $this->actingAs($admin)->post(route('masquerade.take', $user->getAuthIdentifier()))->assertRedirect('/');

        self::assertSame('POST', app('router')->getRoutes()->getByName('masquerade.take')->methods()[0]);
        self::assertContains('DELETE', app('router')->getRoutes()->getByName('masquerade.leave')->methods());
    }

    public function test_non_admin_cannot_masquerade(): void
    {
        $this->actingAs($this->user('user@example.test'))
            ->post(route('masquerade.take', $this->user('admin@example.test')->getAuthIdentifier()))
            ->assertForbidden();
    }

    public function test_model_api_enforces_both_authorization_hooks(): void
    {
        auth()->login($admin = $this->user('admin@example.test'));

        self::assertTrue($admin->masqueradeAs($this->user('user@example.test')));
        app(MasqueradeManager::class)->leave();
        self::assertFalse($admin->masqueradeAs($this->user('blocked@example.test')));
        self::assertSame('admin@example.test', auth()->user()->email);
    }
}
