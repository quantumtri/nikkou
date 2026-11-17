<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'whatsapp_number', 'address', 'city', 'district', 'lat', 'lng', 'data_consent_at', 'whatsapp_consent_at', 'whatsapp_opted_out_at'])]
class Customer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'data_consent_at' => 'datetime',
            'whatsapp_consent_at' => 'datetime',
            'whatsapp_opted_out_at' => 'datetime',
        ];
    }

    /** Opted in to WhatsApp follow-ups and has not asked to stop. */
    public function canReceiveFollowups(): bool
    {
        return $this->whatsapp_consent_at !== null && $this->whatsapp_opted_out_at === null;
    }

    public function chatId(): string
    {
        return $this->whatsapp_number.'@c.us';
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
