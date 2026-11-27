<?php

namespace App\Dominio;

use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Qué establecimientos alcanza un usuario (R-ALC-01 a R-ALC-03).
 *
 * Esta regla estaba escrita en cinco lugares y no coincidían: `Envio::scopeForUser`
 * y `CompletitudService` daban al revisor **todo el Servicio**; las pantallas de
 * revisión y completitud filtraban por `servicio_id`; el encargado filtraba por
 * el pivot. La v3 cambia el modelo —cada revisor ve solo lo que el administrador
 * le asigna— y con cinco copias eso no se puede sostener.
 *
 * Aquí vive la única versión. Cualquier lugar que necesite saber «¿esto lo puede
 * ver este usuario?» llama a `aplicar()` sobre su consulta, o a `ids()` cuando
 * necesita los identificadores para un `whereIn`.
 *
 * Reglas que implementa (`specs/MVP_V3.md` §8):
 *
 * - **R-ALC-01**: el revisor solo alcanza lo asignado. Sin asignación no alcanza
 *   **ninguno**: no se cae a «todo» por dato faltante. Un `servicio_id` suelto
 *   ya no abre el alcance; el alcance es la asignación explícita.
 * - **R-ALC-02**: el encargado alcanza lo asignado; el administrador, todo.
 * - **R-ALC-03**: varios revisores sobre un mismo establecimiento, sin
 *   exclusividad.
 */
final class Alcance
{
    /** ¿El usuario ve todos los establecimientos? */
    public function esGlobal(User $usuario): bool
    {
        return $usuario->hasRole('admin');
    }

    /**
     * ¿Puede este usuario operar sobre ese establecimiento?
     *
     * El administrador siempre; los demás, solo si está asignado.
     */
    public function incluye(User $usuario, Establecimiento|int $establecimiento): bool
    {
        if ($this->esGlobal($usuario)) {
            return true;
        }

        $id = $establecimiento instanceof Establecimiento ? $establecimiento->getKey() : $establecimiento;

        return $usuario->establecimientos()->whereKey($id)->exists();
    }

    /**
     * Aplica el alcance a una consulta sobre `establecimientos`.
     *
     * @param  Builder<Establecimiento>  $consulta
     * @return Builder<Establecimiento>
     */
    public function aplicar(Builder $consulta, User $usuario, string $columna = 'id'): Builder
    {
        if ($this->esGlobal($usuario)) {
            return $consulta;
        }

        // Un `whereIn` con cero elementos devuelve cero filas, que es
        // exactamente lo que manda R-ALC-01 para un revisor sin asignación.
        return $consulta->whereIn($columna, $this->ids($usuario));
    }

    /**
     * Identificadores de los establecimientos del alcance.
     *
     * Se devuelve una consulta y no una colección cuando se puede: así el
     * `whereIn` se resuelve en la base y no viaja una lista de 86 enteros.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany|\Illuminate\Support\Collection<int, int>
     */
    public function ids(User $usuario)
    {
        return $usuario->establecimientos()->select('establecimientos.id')->pluck('establecimientos.id');
    }

    /**
     * Establecimientos del alcance, ordenados por nombre, con filtros de
     * comuna y vigencia. Lo usan las pantallas que arman un desplegable.
     *
     * @return Collection<int, Establecimiento>
     */
    public function establecimientos(User $usuario, bool $soloActivos = true): Collection
    {
        $consulta = Establecimiento::query()->orderBy('nombre');

        if ($soloActivos) {
            $consulta->where('activo', true);
        }

        return $this->aplicar($consulta, $usuario)->get();
    }

    /**
     * ¿El usuario tiene alguna asignación? Sirve para distinguir «no hay
     * envíos» de «no me asignaron nada», que son cosas distintas.
     */
    public function sinAsignacion(User $usuario): bool
    {
        return ! $this->esGlobal($usuario) && ! $usuario->establecimientos()->exists();
    }

    /** Solo los roles que se reparten por establecimiento: revisores y encargados. */
    public function requiereAsignacion(User $usuario): bool
    {
        return $usuario->hasAnyRole(['revisor', 'encargado']);
    }
}
