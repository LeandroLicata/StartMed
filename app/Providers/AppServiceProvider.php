<?php

namespace App\Providers;

use App\Http\Controllers\AsistenteController;
use App\Support\Asistente\ProveedorAsistente;
use App\Support\Asistente\ProveedorAsistenteGemini;
use App\Support\GestorDocumental;
use App\Support\GestorDocumentalCloudinary;
use App\Support\GestorDocumentalLocal;
use Cloudinary\Cloudinary;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Sin CLOUDINARY_URL la aplicacion no se queda sin gestor documental:
         * cae al disco local, que guarda y entrega archivos de verdad. Asi
         * clonar el repo y correr migrate:fresh --seed alcanza para ver el
         * expediente completo, y la suite de tests no sale nunca a la red
         * (.env.testing / phpunit.xml no definen la variable).
         */
        $this->app->singleton(GestorDocumental::class, function () {
            $minutos = (int) config('cloudinary.minutos_url');
            $url = config('cloudinary.url');

            if (blank($url)) {
                return new GestorDocumentalLocal($minutos);
            }

            return new GestorDocumentalCloudinary(
                new Cloudinary((string) $url),
                (string) config('cloudinary.carpeta'),
                $minutos,
            );
        });

        /*
         * El modelo detras del asistente. Los tests lo reemplazan por uno
         * falso (phpunit.xml deja GEMINI_API_KEY vacia): la suite nunca le
         * habla a Gemini.
         */
        $this->app->bind(ProveedorAsistente::class, fn () => new ProveedorAsistenteGemini(
            config('asistente.clave'),
            (string) config('asistente.modelo'),
            (string) config('asistente.nivel_razonamiento'),
            (float) config('asistente.temperatura'),
            (int) config('asistente.max_tokens'),
            (int) config('asistente.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Topes del asistente, por quien escribe y no por IP: todos los
         * pedidos traen sesion. La clave lleva la clase porque un Usuario y una
         * Persona pueden compartir id. Cada limite necesita su propia clave:
         * con la misma, los dos contarian sobre un unico contador.
         */
        RateLimiter::for('asistente', function (Request $request) {
            $quien = class_basename($request->user()).':'.$request->user()?->getAuthIdentifier();
            $respuesta = fn (Request $request, array $headers) => response(
                AsistenteController::DEMASIADAS,
                429,
                [...$headers, 'Content-Type' => 'text/plain; charset=utf-8'],
            );

            return [
                Limit::perMinute((int) config('asistente.por_minuto'))->by("minuto:$quien")->response($respuesta),
                Limit::perDay((int) config('asistente.por_dia'))->by("dia:$quien")->response($respuesta),
            ];
        });

        /*
         * Brevo no viene como transporte nativo de Laravel; Symfony si lo
         * trae. Va por la API (`brevo+api`, HTTPS 443) y no por SMTP porque el
         * plan free de Render bloquea los puertos SMTP salientes.
         */
        Mail::extend('brevo', function () {
            $clave = config('services.brevo.key');

            if (blank($clave)) {
                throw new InvalidArgumentException('MAIL_MAILER=brevo necesita BREVO_API_KEY.');
            }

            return (new BrevoTransportFactory)->create(new Dsn('brevo+api', 'default', (string) $clave));
        });
    }
}
