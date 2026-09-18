<?php

namespace App\Providers;

use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Grupo;
use App\Models\PropuestaBot;
use App\Models\Quiz;
use App\Models\Tarea;
use App\Models\User;
use App\Policies\CursoPolicy;
use App\Policies\EntregaPolicy;
use App\Policies\GrupoPolicy;
use App\Policies\PropuestaBotPolicy;
use App\Policies\QuizPolicy;
use App\Policies\TareaPolicy;
use App\Policies\UsuarioPolicy;
use App\Services\Bot\ClaudeBotService;
use App\Services\Bot\Contracts\ProveedorIA;
use App\Services\Bot\GroqProveedor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bot de sugerencias (Módulos 6-7): IA_PROVEEDOR decide qué
        // implementación de ProveedorIA se inyecta en todo el resto de la
        // app (App\Livewire\BotSugerencias\Widget no depende de cuál sea).
        $this->app->bind(ProveedorIA::class, function ($app) {
            return config('services.ia.proveedor') === 'claude'
                ? $app->make(ClaudeBotService::class)
                : $app->make(GroqProveedor::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UsuarioPolicy::class);
        Gate::policy(Curso::class, CursoPolicy::class);
        Gate::policy(Tarea::class, TareaPolicy::class);
        Gate::policy(Entrega::class, EntregaPolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(PropuestaBot::class, PropuestaBotPolicy::class);
        Gate::policy(Grupo::class, GrupoPolicy::class);
    }
}
