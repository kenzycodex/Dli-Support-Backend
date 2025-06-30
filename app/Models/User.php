<?php
// app/Models/User.php (Updated with notification relationship)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
        'address',
        'date_of_birth',
        'student_id',
        'employee_id',
        'specializations',
        'bio',
        'profile_photo',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'date_of_birth' => 'date',
        'specializations' => 'array',
        'password' => 'hashed',
    ];

    // Role constants
    const ROLE_STUDENT = 'student';
    const ROLE_COUNSELOR = 'counselor';
    const ROLE_ADVISOR = 'advisor';
    const ROLE_ADMIN = 'admin';

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * Get user's notifications
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get tickets created by this user
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get tickets assigned to this user
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to')->orderBy('created_at', 'desc');
    }

    /**
     * Get ticket responses by this user
     */
    public function ticketResponses(): HasMany
    {
        return $this->hasMany(TicketResponse::class)->orderBy('created_at', 'desc');
    }

    // Role checking methods
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isCounselor(): bool
    {
        return $this->role === self::ROLE_COUNSELOR;
    }

    public function isAdvisor(): bool
    {
        return $this->role === self::ROLE_ADVISOR;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_COUNSELOR, self::ROLE_ADVISOR, self::ROLE_ADMIN]);
    }

    // Status checking methods
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    // Scopes
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('role', [self::ROLE_COUNSELOR, self::ROLE_ADVISOR, self::ROLE_ADMIN]);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function getRoleDisplayNameAttribute(): string
    {
        return ucfirst($this->role);
    }

    public function getStatusDisplayNameAttribute(): string
    {
        return ucfirst($this->status);
    }

    // Update last login
    public function updateLastLogin()
    {
        $this->update(['last_login_at' => now()]);
    }

    // Get available roles
    public static function getAvailableRoles(): array
    {
        return [
            self::ROLE_STUDENT => 'Student',
            self::ROLE_COUNSELOR => 'Counselor',
            self::ROLE_ADVISOR => 'Advisor',
            self::ROLE_ADMIN => 'Admin',
        ];
    }

    // Get available statuses
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_SUSPENDED => 'Suspended',
        ];
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Get open tickets count (for students)
     */
    public function getOpenTicketsCountAttribute(): int
    {
        return $this->tickets()->open()->count();
    }

    /**
     * Get assigned tickets count (for staff)
     */
    public function getAssignedTicketsCountAttribute(): int
    {
        return $this->assignedTickets()->open()->count();
    }

    /**
     * Create notification for this user
     */
    public function createNotification($type, $title, $message, $priority = 'medium', $data = null)
    {
        return $this->notifications()->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'data' => $data,
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead()
    {
        return $this->notifications()->unread()->update([
            'read' => true,
            'read_at' => now()
        ]);
    }
}