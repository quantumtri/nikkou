<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'whatsapp_number', 'address', 'city', 'district', 'lat', 'lng', 'data_consent_at', 'whatsapp_consent_at'])]
class Customer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'data_consent_at' => 'datetime',
            'whatsapp_consent_at' => 'datetime',
        ];
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
