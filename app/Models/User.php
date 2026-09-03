<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\AdminPasswordResetLinkMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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

    /**
     * Maps a grantable role name to the pivot table whose existence (a row keyed by
     * user_id) constitutes "this user holds that role" — see grantedRoles() and
     * App\Services\RoleGrantService, which shares this map. Admin has no pivot table; it's
     * a direct users.role assignment, not something granted this way.
     */
    public static function grantTables(): array
    {
        return [
            'Committee' => 'committees',
            'Accountant' => 'accountants',
            'Priest' => 'priests',
            'Trustee' => 'trustees',
            'Staff' => 'staff',
        ];
    }

    /**
     * Every role this user may choose to log in as: their primary role, Devotee (universal —
     * anyone can already log in as Devotee, auto-provisioned), and any role for which a
     * pivot-table row exists for their user_id (an explicit grant made via the relevant
     * "Add X" page or RoleGrantService::grant()).
     */
    public function grantedRoles(): array
    {
        $roles = [$this->role, 'Devotee'];

        foreach (self::grantTables() as $role => $table) {
            if (DB::table($table)->where('user_id', $this->id)->exists()) {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * The most authoritative (lowest-numbered) level among every role this user holds —
     * the ceiling AuthController::login() enforces so a user can never select a role more
     * authoritative than what they're actually granted.
     */
    public function authorisedLevel(): int
    {
        $levels = RolePermission::levels();

        return min(array_map(fn ($role) => $levels[$role] ?? PHP_INT_MAX, $this->grantedRoles()));
    }
}
