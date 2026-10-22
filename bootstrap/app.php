<?php

use App\Http\Middleware\CirugiaDelPaciente;
use App\Http\Middleware\SesionPorArea;
use App\Http\Middleware\VerificarRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => VerificarRol::class,
            'cirugia.del.paciente' => CirugiaDelPaciente::class,
        ]);

        // Cookie de sesion propia para el portal: ver SesionPorArea. Tiene que
        // fijar el nombre antes de que StartSession lea la cookie.
        $middleware->web(prepend: [SesionPorArea::class]);
        $middleware->prependToPriorityList(before: StartSession::class, prepend: SesionPorArea::class);

        // El portal del paciente tiene su propia puerta: mandarlo al login del
        // staff le pediria un usuario y contrasena que un paciente no tiene.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('portal', 'portal/*')
            ? route('portal.ingresar')
            : route('login'));

        // Render (y plataformas similares) terminan el TLS en su proxy y
        // reenvían por HTTP simple. Sin confiar en el proxy, Laravel arma
        // las URLs de assets como http:// y el navegador las bloquea por
        // contenido mixto en una página servida por https://. Solo se
        // recibe tráfico a través del proxy de la plataforma, así que
        // confiar en cualquier origen acá es seguro.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Token CSRF vencido: un formulario que quedo abierto mas que la sesion
        // (120 min). Vuelve a su pantalla con un aviso en vez del «419 Page
        // Expired» en ingles y sin salida. Lo que se habia escrito vuelve con
        // withInput (sin la contraseña ni archivos).
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            return redirect()
                ->back(fallback: $request->is('portal', 'portal/*') ? route('portal.ingresar') : route('login'))
                ->withInput($request->except('password', '_token'))
                ->with('aviso', 'La página estuvo abierta mucho tiempo y venció por seguridad. Volvé a intentarlo.');
        });
    })->create();
