<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public function grade() { return $this->belongsTo(Grade::class); }
}
