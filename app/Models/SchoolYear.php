<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolYear extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean'];

    public function periods() { return $this->hasMany(Period::class)->orderBy('order'); }
    public function groups() { return $this->hasMany(Group::class); }

    public static function current(): ?self
    {
        return static::where('is_current', true)->first() ?? static::latest('starts_on')->first();
    }
}
