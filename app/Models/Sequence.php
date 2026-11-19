<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Named counters handed out under a row lock, so two people never get the same number. */
class Sequence extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    protected $guarded = [];

    /** Next value of the counter. A new counter starts after $seed (default 0). Call inside a transaction. */
    public static function next(string $name, int $seed = 0): int
    {
        return DB::transaction(function () use ($name, $seed) {
            DB::table('sequences')->insertOrIgnore(['name' => $name, 'value' => $seed]);

            $row = DB::table('sequences')->where('name', $name)->lockForUpdate()->first();
            $value = $row->value + 1;
            DB::table('sequences')->where('name', $name)->update(['value' => $value]);

            return $value;
        });
    }
}
