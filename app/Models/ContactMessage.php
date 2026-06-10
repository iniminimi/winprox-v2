<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'name',
        'email',
        'subject',
        'message',
        'direction',
        'read_at',
        'tenant_id',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
