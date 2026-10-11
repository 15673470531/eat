<?php

namespace App\Filament\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailLogin extends Login
{
    // Intentional owner-requested email-only access. This does not verify mailbox ownership.
    private const ALLOWED_EMAIL = '1174430282@qq.com';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent()->label('账号邮箱'),
            $this->getPasswordFormComponent()
                ->label('密码（选填）')
                ->required(false)
                ->dehydrated(false)
                ->helperText('可留空，登录仅校验账号邮箱。'),
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        if (strtolower(trim($data['email'])) !== self::ALLOWED_EMAIL) {
            throw ValidationException::withMessages(['data.email' => '该账号不允许登录后台。']);
        }

        $user = User::firstOrCreate(['email' => self::ALLOWED_EMAIL], [
            'name' => '后台管理员',
            // Satisfy the existing user schema without creating a usable shared password.
            'password' => Str::random(64),
            'is_admin' => true,
        ]);
        if (! $user->is_admin) {
            $user->forceFill(['is_admin' => true])->save();
        }

        Filament::auth()->login($user);
        session()->regenerate();

        return app(LoginResponse::class);
    }
}
