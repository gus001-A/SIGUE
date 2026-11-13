<?php

namespace App\Services;

use App\Models\Notification;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Envío de notificaciones al buzón interno. Punto único para agregar
 * después otros canales (push, correo, SMS) según la configuración de la
 * institución.
 */
class Notifier
{
    /**
     * @param int|array<int> $userIds
     */
    public static function send(int|array $userIds, string $type, string $title, string $message, ?string $url = null, ?Model $reference = null): void
    {
        $ids = array_unique(array_filter((array) $userIds));
        // Quien realiza la acción ya ve el aviso en pantalla: no se le duplica en la campana.
        if ($me = auth()->id()) {
            $ids = array_values(array_diff($ids, [$me]));
        }

        foreach ($ids as $id) {
            try {
                Notification::create([
                    'institution_id' => Tenant::id(),
                    'user_id' => $id,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'url' => $url,
                    'reference_type' => $reference ? class_basename($reference) : null,
                    'reference_id' => $reference?->getKey(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Notifier::send falló: ' . $e->getMessage());
            }
        }
    }

    /** Notifica al alumno (si tiene cuenta) y a sus tutores. */
    public static function student(\App\Models\Student $student, string $type, string $title, string $message, ?string $url = null, ?Model $reference = null, bool $includeStudent = true): void
    {
        $ids = $student->guardianUserIds();
        if ($includeStudent && $student->user_id) {
            $ids[] = $student->user_id;
        }
        static::send($ids, $type, $title, $message, $url, $reference);
    }
}
