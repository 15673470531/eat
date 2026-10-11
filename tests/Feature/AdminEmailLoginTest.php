<?php

namespace Tests\Feature;

use App\Filament\Auth\EmailLogin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminEmailLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_login_page_has_email_and_optional_password(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('账号邮箱')->assertSee('密码（选填）');
        Livewire::test(EmailLogin::class)->assertFormFieldExists('password', fn ($field) => ! $field->isRequired());
        $this->assertSame(EmailLogin::class, Filament::getPanel('admin')->getLoginRouteAction());
    }

    public function test_allowed_email_creates_admin_and_opens_chapter_monitor_without_password(): void
    {
        Livewire::test(EmailLogin::class)->fillForm(['email' => '1174430282@qq.com', 'password' => ''])->call('authenticate')->assertHasNoFormErrors()->assertRedirect();
        $this->assertAuthenticated();
        $user = User::where('email', '1174430282@qq.com')->sole();
        $this->assertSame($user->id, auth()->id());
        $this->assertTrue($user->is_admin);
        $this->get('/admin/chapter-monitor')->assertOk();
    }

    public function test_other_emails_and_invalid_input_cannot_create_or_access_admin(): void
    {
        User::factory()->create(['email' => 'other@qq.com', 'is_admin' => true]);
        foreach (['other@qq.com', '1174430282@qq.com.attacker.test', ''] as $email) {
            Livewire::test(EmailLogin::class)->fillForm(['email' => $email])->call('authenticate')->assertHasFormErrors(['email']);
            $this->assertGuest();
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_allowed_account_is_reused_without_changing_password(): void
    {
        $user = User::factory()->create(['email' => '1174430282@qq.com', 'is_admin' => false, 'password' => 'existing-secret']);
        Livewire::test(EmailLogin::class)->fillForm(['email' => '1174430282@qq.com', 'password' => ''])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertTrue(Hash::check('existing-secret', $user->fresh()->password));
    }
}
