<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\GroupSubject;
use App\Models\Student;
use App\Models\User;

/** Reglas de acceso a la información (regla 8: los permisos determinan qué ve cada usuario). */
class Access
{
    /** ¿El alumno es el propio usuario o uno de sus hijos/tutorados? */
    public static function isOwnOrChild(User $user, int $studentId): bool
    {
        if ($user->student && $user->student->id === $studentId) {
            return true;
        }
        return (bool) $user->guardian?->students()->where('students.id', $studentId)->exists();
    }

    /** ¿El docente imparte alguna clase al alumno? */
    public static function teachesStudent(User $user, int $studentId): bool
    {
        if (! $user->teacher) {
            return false;
        }
        $groupIds = GroupSubject::where('teacher_id', $user->teacher->id)->pluck('group_id');
        return Enrollment::where('student_id', $studentId)->whereIn('group_id', $groupIds)->where('status', 'autorizada')->exists();
    }

    public static function canSeeStudent(User $user, Student $student): bool
    {
        return $user->hasPermission('alumnos.ver')
            || static::isOwnOrChild($user, $student->id)
            || static::teachesStudent($user, $student->id)
            || static::isClassmate($user, $student);
    }

    /** Compañeros del mismo grupo (para ver su foto en "Personas" y en los comentarios). */
    public static function isClassmate(User $user, Student $student): bool
    {
        $mine = $user->student?->currentEnrollment?->group_id;

        return $mine && $mine === $student->currentEnrollment?->group_id;
    }

    /** Docente titular de la clase o personal con gestión académica. */
    public static function canManageClass(User $user, GroupSubject $class): bool
    {
        return $user->hasPermission('academico.gestionar')
            || ($user->teacher && $class->teacher_id === $user->teacher->id);
    }

    /** Quien gestiona la clase, o alumnos/tutores inscritos en el grupo. */
    public static function canSeeClass(User $user, GroupSubject $class): bool
    {
        if (static::canManageClass($user, $class)) {
            return true;
        }
        $ids = [];
        if ($user->student) {
            $ids[] = $user->student->id;
        }
        if ($user->guardian) {
            $ids = array_merge($ids, $user->guardian->students()->pluck('students.id')->all());
        }
        return $ids && Enrollment::whereIn('student_id', $ids)->where('group_id', $class->group_id)->where('status', 'autorizada')->exists();
    }
}
