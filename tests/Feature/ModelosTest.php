<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

class ModelosTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<class-string<Model>> */
    private function modelos(): array
    {
        $clases = [];

        foreach (glob(app_path('Models/*.php')) as $archivo) {
            $clase = 'App\\Models\\'.basename($archivo, '.php');

            if (is_subclass_of($clase, Model::class)) {
                $clases[] = $clase;
            }
        }

        sort($clases);

        return $clases;
    }

    /** @return list<ReflectionMethod> */
    private function relaciones(string $clase): array
    {
        $metodos = [];

        foreach ((new ReflectionClass($clase))->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            $tipo = $metodo->getReturnType();

            if ($tipo instanceof ReflectionNamedType
                && ! $tipo->isBuiltin()
                && is_subclass_of($tipo->getName(), Relation::class)) {
                $metodos[] = $metodo;
            }
        }

        return $metodos;
    }

    public function test_hay_un_modelo_por_cada_tabla_del_dominio(): void
    {
        // 65 del DBML. Las 7 de infraestructura de Laravel no llevan modelo.
        $this->assertCount(65, $this->modelos());
    }

    public function test_cada_modelo_apunta_a_una_tabla_existente(): void
    {
        $tablas = array_map(
            'strtolower',
            array_column(
                json_decode(json_encode(
                    \DB::connection()->getSchemaBuilder()->getTables()
                ), true),
                'name'
            )
        );

        foreach ($this->modelos() as $clase) {
            $tabla = (new $clase)->getTable();

            $this->assertContains(
                strtolower($tabla),
                $tablas,
                "{$clase} apunta a la tabla inexistente `{$tabla}`."
            );
        }
    }

    public function test_la_primary_key_de_cada_modelo_existe_en_su_tabla(): void
    {
        foreach ($this->modelos() as $clase) {
            $modelo = new $clase;

            $this->assertTrue(
                \Schema::hasColumn($modelo->getTable(), $modelo->getKeyName()),
                "{$clase}: la PK `{$modelo->getKeyName()}` no existe en `{$modelo->getTable()}`."
            );
        }
    }

    public function test_todas_las_relaciones_generan_sql_valido(): void
    {
        $total = 0;

        foreach ($this->modelos() as $clase) {
            foreach ($this->relaciones($clase) as $metodo) {
                $nombre = $metodo->getName();
                $relacion = (new $clase)->{$nombre}();

                $this->assertInstanceOf(Relation::class, $relacion);

                // Fuerza la compilacion del SQL: revienta si la FK, la tabla
                // o el modelo relacionado estan mal escritos.
                $this->assertNotEmpty($relacion->getQuery()->toSql());

                $total++;
            }
        }

        $this->assertGreaterThan(150, $total, 'Se esperaban muchas mas relaciones.');
    }

    public function test_las_columnas_de_las_relaciones_existen_en_la_base(): void
    {
        foreach ($this->modelos() as $clase) {
            foreach ($this->relaciones($clase) as $metodo) {
                $relacion = (new $clase)->{$metodo->getName()}();
                $etiqueta = $clase.'::'.$metodo->getName();

                foreach ($this->columnasDe($relacion) as [$tabla, $columna]) {
                    $this->assertTrue(
                        \Schema::hasColumn($tabla, $columna),
                        "{$etiqueta}: la columna `{$tabla}.{$columna}` no existe."
                    );
                }
            }
        }
    }

    /** @return list<array{0:string,1:string}> */
    private function columnasDe(Relation $relacion): array
    {
        $tablaPadre = $relacion->getParent()->getTable();
        $tablaHija = $relacion->getRelated()->getTable();

        return match (true) {
            $relacion instanceof BelongsTo => [
                [$tablaPadre, $relacion->getForeignKeyName()],
                [$tablaHija, $relacion->getOwnerKeyName()],
            ],
            $relacion instanceof BelongsToMany => [
                [$relacion->getTable(), Str::afterLast($relacion->getForeignPivotKeyName(), '.')],
                [$relacion->getTable(), Str::afterLast($relacion->getRelatedPivotKeyName(), '.')],
            ],
            $relacion instanceof HasOneOrMany => [
                [$tablaHija, Str::afterLast($relacion->getForeignKeyName(), '.')],
                [$tablaPadre, Str::afterLast($relacion->getLocalKeyName(), '.')],
            ],
            default => [],
        };
    }

    public function test_ningun_modelo_espera_timestamps(): void
    {
        // El esquema no tiene created_at / updated_at en ninguna tabla.
        foreach ($this->modelos() as $clase) {
            $this->assertFalse(
                (new $clase)->usesTimestamps(),
                "{$clase} tiene \$timestamps activo pero su tabla no tiene esas columnas."
            );
        }
    }
}
