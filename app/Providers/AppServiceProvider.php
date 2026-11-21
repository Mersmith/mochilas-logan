<?php

namespace App\Providers;

use App\Models\EmpresaConfiguracion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureSuperAdmin();
        $this->configureDefaults();
        $this->configureViewComposers();
    }

    /**
     * Compartir configuración global de la empresa a las vistas públicas.
     */
    protected function configureViewComposers(): void
    {
        View::composer([
            'layouts.publico',
            'layouts.publico.navbar',
            'components.footer-publico',
            'components.whatsapp-flotante',
        ], function ($view) {
            $view->with('empresa', EmpresaConfiguracion::getEmpresa());
        });
    }

    /**
     * Super Admin bypasses ALL permission and gate checks.
     * This user is invisible in the admin panel user list.
     */
    protected function configureSuperAdmin(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->is_super_admin) {
                return true;
            }

            return null; // defer to normal checks
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
