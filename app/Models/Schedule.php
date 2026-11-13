<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use BelongsToInstitution, ScopedToCampus, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'groupSubject';

    public const DAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function groupSubject() { return $this->belongsTo(GroupSubject::class); }
}
