<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_admin_can_create_a_user_with_role(): void
    {
        Volt::actingAs($this->admin())
            ->test('users.index')
            ->set('name', 'Nueva Vendedora')
            ->set('email', 'nueva@aquakita.test')
            ->set('role', 'vendedor')
            ->set('password', 'secret123')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'nueva@aquakita.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->active);
        $this->assertTrue($user->hasRole('vendedor'));
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_password_is_required_on_create(): void
    {
        Volt::actingAs($this->admin())
            ->test('users.index')
            ->set('name', 'Sin Clave')
            ->set('email', 'sinclave@aquakita.test')
            ->set('role', 'vendedor')
            ->call('save')
            ->assertHasErrors(['password']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'repetido@aquakita.test']);

        Volt::actingAs($this->admin())
            ->test('users.index')
            ->set('name', 'Otro')
            ->set('email', 'repetido@aquakita.test')
            ->set('role', 'vendedor')
            ->set('password', 'secret123')
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_editing_without_password_keeps_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('original1')]);
        $user->assignRole('vendedor');

        Volt::actingAs($this->admin())
            ->test('users.index')
            ->call('edit', $user->id)
            ->set('name', 'Nombre Editado')
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Nombre Editado', $user->name);
        $this->assertTrue(Hash::check('original1', $user->password));
    }

    public function test_admin_can_deactivate_another_user_but_not_self(): void
    {
        $admin = $this->admin();
        $vendor = User::factory()->create(['active' => true]);
        $vendor->assignRole('vendedor');

        $component = Volt::actingAs($admin)->test('users.index');

        $component->call('toggle', $vendor->id);
        $this->assertFalse($vendor->fresh()->active);

        $component->call('toggle', $admin->id);
        $this->assertTrue($admin->fresh()->active); // no puede desactivarse a sí mismo
    }

    public function test_vendor_cannot_access_users(): void
    {
        $vendor = User::factory()->create();
        $vendor->assignRole('vendedor');

        $this->actingAs($vendor)->get(route('users.index'))->assertForbidden();
    }
}
