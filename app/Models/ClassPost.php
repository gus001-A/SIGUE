<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\HasFiles;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Aviso o material publicado en el Classroom de una clase. */
class ClassPost extends Model
{
    use BelongsToInstitution, HasFiles, ScopedToCampus, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'groupSubject';
    protected $casts = ['links' => 'array', 'pinned' => 'boolean'];

    public const TYPES = ['aviso' => 'Aviso', 'material' => 'Material'];

    public function groupSubject() { return $this->belongsTo(GroupSubject::class); }
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
}
