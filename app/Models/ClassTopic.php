<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tema del Classroom de una clase (agrupa tareas). */
class ClassTopic extends Model
{
    use BelongsToInstitution, ScopedToCampus, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'groupSubject';

    public function groupSubject() { return $this->belongsTo(GroupSubject::class); }
    public function assignments() { return $this->hasMany(Assignment::class, 'topic_id'); }
}
