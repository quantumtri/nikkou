<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Exceptions\AccountRuleViolation;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Account management (spec A11). Accounts are never deleted; they are deactivated.
 * A "set password" link serves both invitations and password resets.
 */
class UserService
{
    public const LINK_DAYS = 7;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INVITED = 'invited';

    public const STATUS_INACTIVE = 'inactive';

    /** @param  array{name: string, email: string, whatsapp_number?: ?string, role: string}  $data */
    public function invite(array $data, User $admin): User
    {
        return DB::transaction(function () use ($data, $admin) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'whatsapp_number' => WhatsApp::normalize($data['whatsapp_number'] ?? null),
                'role' => UserRole::from($data['role']),
                // Nobody knows this password; the person sets their own through the invite link.
                'password' => Str::random(48),
                'is_active' => true,
                'invited_at' => now(),
            ]);

            ActivityLog::record($user, 'user.invited', $admin, null, $user->role->value, 'Akun dibuat dan undangan diterbitkan.');

            return $user;
        });
    }

    /** Issue a fresh set-password link. Older links stop working at once. */
    public function issueLink(User $user, User $admin): string
    {
        if (! $user->is_active) {
            throw new AccountRuleViolation('Akun nonaktif tidak bisa diberi tautan. Aktifkan akunnya lebih dulu.');
        }

        $user->forceFill(['invited_at' => now()])->save();
        ActivityLog::record($user, 'user.link_issued', $admin, notes: $this->status($user) === self::STATUS_INVITED ? 'Undangan dikirim ulang.' : 'Tautan atur ulang kata sandi diterbitkan.');

        return $this->linkFor($user);
    }

    public function linkFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'invite.show',
            $user->invited_at->copy()->addDays(self::LINK_DAYS),
            ['user' => $user->id, 't' => $user->invited_at->timestamp],
        );
    }

    /** A link is valid while it is the latest one issued and has not been used. */
    public function linkIsCurrent(User $user, int $timestamp): bool
    {
        return $user->is_active && $user->invited_at !== null && $user->invited_at->timestamp === $timestamp;
    }

    public function acceptLink(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'invited_at' => null,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            ActivityLog::record($user, 'user.password_set', $user, notes: 'Kata sandi diatur lewat tautan.');
        });
    }

    /** @param  array{name?: string, whatsapp_number?: ?string, role?: string}  $data */
    public function update(User $user, array $data, User $admin): User
    {
        return DB::transaction(function () use ($user, $data, $admin) {
            $newRole = isset($data['role']) ? UserRole::from($data['role']) : $user->role;

            if ($newRole !== $user->role) {
                $this->assertRoleChangeAllowed($user, $newRole, $admin);
            }

            $oldRole = $user->role;
            $user->fill([
                'name' => trim($data['name'] ?? $user->name),
                'whatsapp_number' => array_key_exists('whatsapp_number', $data) ? WhatsApp::normalize($data['whatsapp_number']) : $user->whatsapp_number,
                'role' => $newRole,
            ])->save();

            if ($oldRole !== $newRole) {
                ActivityLog::record($user, 'user.role_changed', $admin, $oldRole->value, $newRole->value);
                // Sign the person out so the new role applies from the next page.
                $this->endSessions($user);
            } else {
                ActivityLog::record($user, 'user.updated', $admin, notes: 'Data akun diubah.');
            }

            return $user;
        });
    }

    public function deactivate(User $user, User $admin): void
    {
        if ($user->is($admin)) {
            throw new AccountRuleViolation('Anda tidak bisa menonaktifkan akun Anda sendiri.');
        }

        $this->assertNotLastAdmin($user, 'dinonaktifkan');

        DB::transaction(function () use ($user, $admin) {
            $user->forceFill(['is_active' => false])->save();
            $this->endSessions($user);
            ActivityLog::record($user, 'user.deactivated', $admin);
        });
    }

    public function activate(User $user, User $admin): void
    {
        $user->forceFill(['is_active' => true])->save();
        ActivityLog::record($user, 'user.activated', $admin);
    }

    /** One of: active, invited, inactive. */
    public function status(User $user): string
    {
        return match (true) {
            ! $user->is_active => self::STATUS_INACTIVE,
            $user->invited_at !== null && $user->email_verified_at === null => self::STATUS_INVITED,
            default => self::STATUS_ACTIVE,
        };
    }

    public static function statusLabel(string $status): string
    {
        return ['active' => 'Aktif', 'invited' => 'Diundang', 'inactive' => 'Nonaktif'][$status];
    }

    /** Where to send the invite link: WhatsApp click-to-chat, like the rest of the business. */
    public function whatsappInviteUrl(User $user, string $link): string
    {
        $text = "Halo {$user->name}, akun Nikkou Anda sudah dibuat sebagai {$user->role->label()}. "
            .'Atur kata sandi Anda lewat tautan ini (berlaku '.self::LINK_DAYS." hari, hanya untuk Anda):\n{$link}";

        return WhatsApp::chatUrl($user->whatsapp_number, $text);
    }

    private function assertRoleChangeAllowed(User $user, UserRole $newRole, User $admin): void
    {
        if ($user->is($admin)) {
            throw new AccountRuleViolation('Anda tidak bisa mengubah peran akun Anda sendiri.');
        }

        if ($user->role === UserRole::Admin) {
            $this->assertNotLastAdmin($user, 'diturunkan perannya');
        }

        if ($user->role === UserRole::Sales && Loan::query()->where('sales_id', $user->id)->where('status', LoanStatus::Active)->exists()) {
            throw new AccountRuleViolation('Sales ini masih memegang unit yang dipinjam. Selesaikan pengembaliannya dulu sebelum mengubah perannya.');
        }
    }

    private function assertNotLastAdmin(User $user, string $action): void
    {
        if ($user->role !== UserRole::Admin || ! $user->is_active) {
            return;
        }

        $otherAdmins = User::query()->where('role', UserRole::Admin)->where('is_active', true)->whereKeyNot($user->id)->count();

        if ($otherAdmins === 0) {
            throw new AccountRuleViolation("Ini satu-satunya admin aktif, jadi tidak boleh {$action}.");
        }
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
