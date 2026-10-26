<?php

namespace App\Models;

use App\Mail\UserNotificationMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class UserNotification extends Model
{
    protected $fillable = ['user_id', 'title', 'message', 'type', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    protected static function booted(): void
    {
        static::created(function (UserNotification $notification) {
            $user = $notification->user;
            if ($user && $user->email_notifications && $user->email) {
                Mail::to($user->email)->send(new UserNotificationMail($notification));
            }
        });
    }
}
