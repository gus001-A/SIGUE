<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Period extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'is_closed' => 'boolean'];

    public function schoolYear() { return $this->belongsTo(SchoolYear::class); }
}
