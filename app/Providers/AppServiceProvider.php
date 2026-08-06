<?php

namespace App\Providers;

use App\Support\GestorDocumental;
use App\Support\GestorDocumentalCloudinary;
use App\Support\GestorDocumentalLocal;
use Cloudinary\Cloudinary;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
