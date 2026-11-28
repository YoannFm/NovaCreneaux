<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un envoi a faire (pending), reussi (success) ou abandonne apres plusieurs essais (failed). */
class WebhookDelivery extends Model
{
    public const PENDING = 'pending';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    public $timestamps = false;

    protected $fillable = ['webhook_id', 'uuid', 'event', 'payload', 'status', 'attempts', 'next_attempt_at', 'response_code', 'error', 'duration_ms', 'delivered_at', 'created_at'];

    protected $casts = ['next_attempt_at' => 'datetime', 'delivered_at' => 'datetime', 'created_at' => 'datetime'];

    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }
}
