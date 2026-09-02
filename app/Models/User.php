<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\AdminPasswordResetLinkMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
protected $fillable = [
    'name',
    'email',
    'mobile',
    'password',
    'role',
    'status',
    'last_login_at',
    'password_changed_at',
];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * Overrides Laravel's default "here's your token, format it however Notification wants"
     * behaviour, used by the admin-triggered Password::sendResetLink() flow (see
     * SystemUserController::sendResetLink()), so the email matches this app's own branded
     * templates instead of the framework's generic notification styling.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('admin-reset.form', ['token' => $token, 'email' => $this->email]);
        Mail::to($this->email)->send(new AdminPasswordResetLinkMail($this->name, $url));
    }
}
