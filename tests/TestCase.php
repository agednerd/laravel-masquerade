<?php

namespace AgedNerd\Masquerade\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use AgedNerd\Masquerade\MasqueradeServiceProvider;
use AgedNerd\Masquerade\Tests\Stubs\Models\User;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('can_be_masqueraded')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        DB::table('users')->insert([
            ['name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('password'), 'is_admin' => true, 'can_be_masqueraded' => true],
            ['name' => 'User', 'email' => 'user@example.test', 'password' => bcrypt('password'), 'is_admin' => false, 'can_be_masqueraded' => true],
            ['name' => 'Blocked', 'email' => 'blocked@example.test', 'password' => bcrypt('password'), 'is_admin' => true, 'can_be_masqueraded' => false],
        ]);

        $this->app['router']->middleware('web')->group(fn () => $this->app['router']->masquerade());
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('session.driver', 'array');
    }

    protected function getPackageProviders($app): array
    {
        return [MasqueradeServiceProvider::class];
    }

    protected function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
