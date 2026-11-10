<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'whatsapp_number', 'role', 'is_active', 'invited_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSales(): bool
    {
        return $this->role === UserRole::Sales;
    }

    public function isWarehouse(): bool
    {
        return $this->role === UserRole::Warehouse;
    }

    /** The Filament panel is for admin and warehouse staff; sales use the mobile app. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && ($this->isAdmin() || $this->isWarehouse());
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'sales_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'sales_id');
    }
}
