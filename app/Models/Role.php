<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public const SUPERADMIN = 'superadmin';
    public const DIRECTOR = 'director';
    public const ADMIN = 'admin';
    public const DOCENTE = 'docente';
    public const ALUMNO = 'alumno';
    public const TUTOR = 'tutor';

    public function permissions() { return $this->belongsToMany(Permission::class); }
    public function users() { return $this->belongsToMany(User::class); }
}
