<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Servers created/owned by this user.
     */
    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Servers assigned to this user via pivot table.
     */
    public function assignedServers()
    {
        return $this->belongsToMany(Server::class, 'server_user')->withTimestamps();
    }

    /**
     * Get all servers this user can access.
     * Admin: all servers. User: owned + assigned servers.
     */
    public function accessibleServers()
    {
        if ($this->isAdmin()) {
            return Server::query();
        }

        return Server::where('user_id', $this->id)
            ->orWhereIn('id', $this->assignedServers()->pluck('servers.id'));
    }

    public function alertRules()
    {
        return $this->hasMany(AlertRule::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
