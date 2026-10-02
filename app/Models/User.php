<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Role, is_active and pharmacy_id are deliberately not mass assignable:
 * only the admin actions set them explicitly.
 */
#[Fillable(['name', 'phone', 'email', 'password', 'gender', 'birth_date', 'city', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'role' => Role::class,
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    public function practitioner(): HasOne
    {
        return $this->hasOne(Practitioner::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /** The doctor who follows this client (set when they approve the assessment; admins can reassign). */
    public function primaryPractitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'primary_practitioner_id');
    }

    /** A paid, started and not yet ended subscription. Medication can only be prescribed and ordered while true. */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()->current()->exists();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(MedicalAssessment::class);
    }

    public function weightPlans(): HasMany
    {
        return $this->hasMany(WeightPlan::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->current()->latest('ends_at');
    }

    public function activePlan(): HasOne
    {
        return $this->hasOne(WeightPlan::class)->where('status', 'active');
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isClient(): bool
    {
        return $this->role === Role::Client;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function preferredLocale(): string
    {
        return $this->locale ?: 'ar';
    }

    /** Mail is only possible when the client gave an email; everyone is reachable in-app. */
    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }
}
