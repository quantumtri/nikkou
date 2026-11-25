<?php

namespace App\Models;

use App\Enums\AgeRange;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'whatsapp_number', 'address', 'city', 'province', 'gender', 'age_range', 'district', 'lat', 'lng', 'data_consent_at', 'whatsapp_consent_at', 'birth_date', 'email', 'referred_by'])]
class Customer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'age_range' => AgeRange::class,
            'birth_date' => 'date',
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
