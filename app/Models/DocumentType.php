<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['is_required' => 'boolean'];
}
