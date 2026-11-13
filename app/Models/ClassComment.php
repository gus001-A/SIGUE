<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Comentario de la clase (público) o privado entre docente y alumno, en una tarea o un aviso. */
class ClassComment extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $table = 'classroom_comments';
    protected $guarded = ['id'];
    protected $casts = ['is_private' => 'boolean'];

    public function author() { return $this->belongsTo(User::class, 'user_id'); }
    public function student() { return $this->belongsTo(Student::class); }
}
