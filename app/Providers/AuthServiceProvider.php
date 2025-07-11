<?php
// app/Providers/AuthServiceProvider.php (UPDATE EXISTING FILE)

namespace App\Providers;

use App\Models\User;
use App\Models\Ticket;
use App\Models\Notification;
use App\Models\HelpCategory;
use App\Models\FAQ;
use App\Models\ResourceCategory;
use App\Models\Resource;
use App\Policies\UserPolicy;
use App\Policies\TicketPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\HelpCategoryPolicy;
use App\Policies\FAQPolicy;
use App\Policies\ResourceCategoryPolicy;
use App\Policies\ResourcePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Ticket::class => TicketPolicy::class,
        Notification::class => NotificationPolicy::class,
        // NEW: Help & FAQ Policies
        HelpCategory::class => HelpCategoryPolicy::class,
        FAQ::class => FAQPolicy::class,
        ResourceCategory::class => ResourceCategoryPolicy::class,
        Resource::class => ResourcePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Existing gates...
        
        // NEW: Help & Resources Gates
        Gate::define('manage-help-content', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('suggest-help-content', function (User $user) {
            return in_array($user->role, ['counselor', 'admin']);
        });

        Gate::define('manage-resources', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('access-help-analytics', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('export-content', function (User $user) {
            return $user->role === 'admin';
        });
    }
}