<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Mensajería interna (docentes ↔ padres, solicitudes de reunión, comunicados). */
class Message extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['read_at' => 'datetime', 'meeting_at' => 'datetime'];

    public const KINDS = ['mensaje' => 'Mensaje', 'reunion' => 'Solicitud de reunión', 'comunicado' => 'Comunicado'];

    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient() { return $this->belongsTo(User::class, 'recipient_id'); }
    public function student() { return $this->belongsTo(Student::class)->withoutGlobalScopes(); }
    public function parent() { return $this->belongsTo(Message::class, 'parent_id'); }
    public function replies() { return $this->hasMany(Message::class, 'parent_id')->oldest(); }
}
