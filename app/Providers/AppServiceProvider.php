<?php

namespace App\Providers;

use App\Models\Curso;
use App\Models\Debate;
use App\Models\DebateComentario;
use App\Models\Entrega;
use App\Models\Grupo;
use App\Models\PropuestaBot;
use App\Models\Quiz;
use App\Models\Tarea;
use App\Models\User;
use App\Policies\CursoPolicy;
use App\Policies\DebatePolicy;
use App\Policies\EntregaPolicy;
use App\Policies\GrupoPolicy;
use App\Policies\PropuestaBotPolicy;
use App\Policies\QuizPolicy;
use App\Policies\TareaPolicy;
use App\Policies\UsuarioPolicy;
use App\Services\Bot\ClaudeBotService;
use App\Services\Bot\Contracts\ProveedorIA;
use App\Services\Bot\GroqProveedor;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Una sola lectura de la configuración por solicitud (RF-107, RF-108).
        $this->app->singleton(ConfiguracionPlataforma::class);
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
        Gate::policy(Debate::class, DebatePolicy::class);
        Gate::policy(DebateComentario::class, DebatePolicy::class);

        $this->aplicarConfiguracionRegional();

        // Política de contraseñas seguras, la misma en primer ingreso, perfil y
        // recuperación: mínimo 8 caracteres, mayúscula y minúscula, número y
        // carácter especial (- . @ # ! …). Las contraseñas temporales también la cumplen.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // ERS RNF-02: en producción toda URL generada usa HTTPS.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Límite de solicitudes de la API de integración (por IP), aplicado
        // antes de validar la API Key para frenar intentos de fuerza bruta.
        RateLimiter::for('api-integracion', fn (Request $request) => Limit::perMinute((int) config('services.integracion.limite_por_minuto', 60))
            ->by($request->ip())
            ->response(fn () => response()->json(['error' => 'Demasiadas solicitudes. Intente de nuevo en un minuto.'], 429)));
    }

    /**
     * ERS RF-108: zona horaria y formato de fecha elegidos en Configuración.
     *
     * La zona horaria se aplica en cada solicitud web y en las pruebas; los
     * comandos programados la aplican al iniciar, para no consultar la base
     * de datos en comandos de despliegue (config:cache, package:discover...).
     * Las vistas muestran las fechas con $fecha->formatoFecha(),
     * formatoFechaHora() y formatoDiaMes().
     */
    protected function aplicarConfiguracionRegional(): void
    {
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            $this->app->make(ConfiguracionPlataforma::class)->aplicarZonaHoraria();
        }

        Carbon::macro('formatoFecha', function (): string {
            /** @var Carbon $this */
            return $this->format(app(ConfiguracionPlataforma::class)->formatoFecha());
        });

        Carbon::macro('formatoFechaHora', function (bool $conSegundos = false): string {
            /** @var Carbon $this */
            return $this->format(app(ConfiguracionPlataforma::class)->formatoFechaHora($conSegundos));
        });

        Carbon::macro('formatoDiaMes', function (): string {
            /** @var Carbon $this */
            return $this->format(app(ConfiguracionPlataforma::class)->formatoDiaMes());
        });
    }
}
