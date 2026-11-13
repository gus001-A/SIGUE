<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TutoringSession extends Model
{
    use BelongsToInstitution, ScopedToCampus, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected $casts = ['date' => 'date', 'follow_up_on' => 'date'];

    public function student() { return $this->belongsTo(Student::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
}
