<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Grado escolar (1°, 2°, 1er semestre…). */
class Grade extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $guarded = ['id'];
}
