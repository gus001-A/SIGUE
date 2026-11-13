<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Buzón de notificaciones del usuario (sección 5.3 / 9). */
class Notification extends Model
{
    use SoftDeletes;

    protected $table = 'notifications';
    protected $guarded = ['id'];
    protected $casts = ['read_at' => 'datetime'];

    public const TYPES = [
        'falta' => ['Falta', 'red'], 'retardo' => ['Retardo', 'amber'], 'tarea' => ['Nueva tarea', 'indigo'],
        'calificacion' => ['Calificación', 'green'], 'incidencia' => ['Incidencia', 'pink'],
        'reunion' => ['Reunión', 'violet'], 'evento' => ['Evento', 'indigo'], 'adeudo' => ['Adeudo', 'amber'],
        'comunicado' => ['Comunicado', 'indigo'], 'mensaje' => ['Mensaje', 'violet'], 'documento' => ['Documento', 'slate'],
        'entrega' => ['Entrega', 'green'], 'info' => ['Aviso', 'slate'],
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function toView(): array
    {
        [$label, $color] = self::TYPES[$this->type] ?? self::TYPES['info'];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $label,
            'color' => $color,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'read' => $this->read_at !== null,
            'date' => $this->created_at?->diffForHumans(),
            'date_full' => $this->created_at?->format('d/m/Y H:i'),
            'group' => $this->dateGroup(),
        ];
    }

    private function dateGroup(): string
    {
        $f = $this->created_at;
        return match (true) {
            ! $f => 'Antes',
            $f->isToday() => 'Hoy',
            $f->isYesterday() => 'Ayer',
            $f->greaterThanOrEqualTo(now()->subDays(7)) => 'Esta semana',
            default => $f->format('d/m/Y'),
        };
    }
}
