<?php

use App\Http\Middleware\CirugiaDelPaciente;
use App\Http\Middleware\VerificarRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
    })->create();
