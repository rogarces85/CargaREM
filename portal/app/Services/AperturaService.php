<?php

namespace App\Services;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Apertura;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aperturas de fuera de plazo (RF-043, D-044).
 *
 * La regla completa, en una frase: **un período cerrado se abre solo para el
 * establecimiento autorizado y solo hasta la fecha que fija el administrador.**
 *
 * Lo que este servicio garantiza:
 *
 * - El encargado puede pedir, no aprobar. `resolver()` es solo del
 *   administrador y lo comprueba el controlador con la policy.
 * - El motivo de la solicitud y el de la resolución son obligatorios: una
 *   excepción sin motivo no es una excepción, es un agujero.
 * - Una solicitud pendiente del mismo establecimiento y período se reutiliza.
 *   Dos filas iguales obligarían al administrador a resolver dos veces lo mismo.
 * - La ventana vence sola. No hace falta un proceso que la cierre, y si el
 *   proceso no corre el sistema sigue correcto.
 */
class AperturaService
{
    public const MOTIVO_MINIMO = 15;

    /**
     * El encargado pide abrir un período.
     *
     * @return array{apertura: Apertura, creada: bool}
     */
    public function solicitar(
        User $solicitante,
        ?Establecimiento $establecimiento,
        int $anio,
        int $mes,
        string $motivo,
    ): array {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < self::MOTIVO_MINIMO) {
            throw EnvioInvalidoException::aperturaSinMotivo(self::MOTIVO_MINIMO);
        }

        $this->verificarPeriodoExistente($anio, $mes);

        // Una pendiente del mismo establecimiento y período se reutiliza: el
        // encargado que vuelve a pedir lo mismo no genera ruido en la bandeja
        // del administrador.
        $existente = Apertura::query()
            ->where('establecimiento_id', $establecimiento?->id)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('estado', Apertura::PENDIENTE)
            ->first();

        if ($existente !== null) {
            return ['apertura' => $existente, 'creada' => false];
        }

        $apertura = DB::transaction(function () use ($solicitante, $establecimiento, $anio, $mes, $motivo) {
            $apertura = Apertura::create([
                'establecimiento_id' => $establecimiento?->id,
                'anio'               => $anio,
                'mes'                => $mes,
                'origen'             => Apertura::ORIGEN_SOLICITUD,
                'motivo'             => $motivo,
                'solicitado_por'     => $solicitante->id,
                'estado'             => Apertura::PENDIENTE,
            ]);

            AuditoriaService::registrarSobre('apertura.solicitada', $apertura, [
                'establecimiento' => $establecimiento?->codigo ?? 'todos',
                'periodo'         => $apertura->periodoLegible(),
            ]);

            return $apertura;
        });

        app(Avisar::class)->enviar(
            User::role('admin')->where('activo', true)->get()->all(),
            'apertura_solicitada',
            [
                'usuario' => $solicitante->name,
                'establecimiento' => $establecimiento?->codigo ?? 'Todos los establecimientos',
                'periodo' => $apertura->periodoLegible(),
                'motivo' => $motivo,
                'url' => route('aperturas.index'),
            ]
        );

        return ['apertura' => $apertura, 'creada' => true];
    }

    /**
     * El administrador abre una ventana por su cuenta, con motivo.
     */
    public function otorgar(
        User $admin,
        ?Establecimiento $establecimiento,
        int $anio,
        int $mes,
        string $motivo,
        ?string $vigenteHasta = null,
    ): Apertura {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < self::MOTIVO_MINIMO) {
            throw EnvioInvalidoException::aperturaSinMotivo(self::MOTIVO_MINIMO);
        }

        $this->verificarPeriodoExistente($anio, $mes);

        $apertura = DB::transaction(function () use ($admin, $establecimiento, $anio, $mes, $motivo, $vigenteHasta) {
            $apertura = Apertura::create([
                'establecimiento_id' => $establecimiento?->id,
                'anio'               => $anio,
                'mes'                => $mes,
                'origen'             => Apertura::ORIGEN_ADMIN,
                'motivo'             => $motivo,
                'solicitado_por'     => $admin->id,
                'estado'             => Apertura::APROBADA,
                'resuelto_por'       => $admin->id,
                'resuelto_en'        => now(),
                'motivo_resolucion'  => $motivo,
                'vigente_hasta'      => $vigenteHasta,
            ]);

            AuditoriaService::registrarSobre('apertura.abierta', $apertura, [
                'establecimiento' => $establecimiento?->codigo ?? 'todos',
                'periodo'         => $apertura->periodoLegible(),
                'vigente_hasta'   => $vigenteHasta,
            ]);

            return $apertura;
        });

        $this->avisarApertura($apertura, 'apertura_abierta', $motivo);

        return $apertura;
    }

    /**
     * El administrador resuelve una solicitud.
     */
    public function resolver(
        Apertura $apertura,
        User $admin,
        bool $aprobar,
        string $motivoResolucion,
        ?string $vigenteHasta = null,
    ): Apertura {
        $motivoResolucion = trim($motivoResolucion);

        if (mb_strlen($motivoResolucion) < 10) {
            throw EnvioInvalidoException::aperturaSinMotivo(10);
        }

        if (! in_array($apertura->estado, [Apertura::PENDIENTE], true)) {
            throw EnvioInvalidoException::aperturaYaResuelta(Apertura::ESTADOS[$apertura->estado] ?? $apertura->estado);
        }

        $apertura = DB::transaction(function () use ($apertura, $admin, $aprobar, $motivoResolucion, $vigenteHasta) {
            $apertura->update([
                'estado'            => $aprobar ? Apertura::APROBADA : Apertura::RECHAZADA,
                'resuelto_por'      => $admin->id,
                'resuelto_en'       => now(),
                'motivo_resolucion' => $motivoResolucion,
                'vigente_hasta'     => $aprobar ? $vigenteHasta : null,
            ]);

            AuditoriaService::registrarSobre(
                $aprobar ? 'apertura.aprobada' : 'apertura.rechazada',
                $apertura,
                [
                    'periodo' => $apertura->periodoLegible(),
                    'motivo'  => $motivoResolucion,
                ]
            );

            return $apertura;
        });

        $this->avisarApertura($apertura, $aprobar ? 'apertura_aprobada' : 'apertura_rechazada', $motivoResolucion);

        return $apertura;
    }

    /** El administrador anula una ventana que ya no corresponde. */
    public function anular(Apertura $apertura, User $admin, string $motivo): Apertura
    {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < 10) {
            throw EnvioInvalidoException::aperturaSinMotivo(10);
        }

        $apertura->update([
            'estado'            => Apertura::ANULADA,
            'resuelto_por'      => $admin->id,
            'resuelto_en'       => now(),
            'motivo_resolucion' => $motivo,
        ]);

        AuditoriaService::registrarSobre('apertura.anulada', $apertura, [
            'periodo' => $apertura->periodoLegible(),
            'motivo'  => $motivo,
        ]);

        return $apertura;
    }

    /**
     * La apertura que habilita a cargar ese establecimiento en ese período.
     *
     * Devuelve la más específica: primero la del establecimiento y después la
     * que vale para todos. Null cuando no hay ninguna.
     */
    public function vigentePara(Establecimiento|int|null $establecimiento, int $anio, int $mes): ?Apertura
    {
        $id = $establecimiento instanceof Establecimiento ? $establecimiento->getKey() : $establecimiento;

        if ($id === null) {
            return null;
        }

        return Apertura::query()
            ->vigentes()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where(function ($q) use ($id) {
                $q->where('establecimiento_id', $id)->orWhereNull('establecimiento_id');
            })
            ->orderByRaw('establecimiento_id IS NULL') // primero la del establecimiento
            ->orderByDesc('id')
            ->first();
    }

    /** ¿Puede este establecimiento cargar este período? */
    public function permite(Establecimiento|int|null $establecimiento, int $anio, int $mes): bool
    {
        return $this->vigentePara($establecimiento, $anio, $mes) !== null;
    }

    /**
     * Solicitudes y aperturas para la bandeja del administrador.
     *
     * @return Collection<int, Apertura>
     */
    public function bandeja(?string $estado = null): Collection
    {
        return Apertura::query()
            ->with(['establecimiento', 'solicitante', 'resolvedor'])
            ->when($estado !== null && $estado !== '', fn ($q) => $q->where('estado', $estado))
            ->orderByRaw("FIELD(estado, 'pendiente', 'aprobada', 'rechazada', 'anulada')")
            ->orderByDesc('created_at')
            ->get();
    }

    /** Cierra las ventanas vencidas dejando el estado explícito. */
    public function cerrarVencidas(): int
    {
        return Apertura::query()
            ->where('estado', Apertura::APROBADA)
            ->whereNotNull('vigente_hasta')
            ->where('vigente_hasta', '<', now()->toDateString())
            ->update(['estado' => Apertura::ANULADA]);
    }

    private function verificarPeriodoExistente(int $anio, int $mes): void
    {
        if (! Periodo::where('anio', $anio)->where('mes', $mes)->exists()) {
            throw EnvioInvalidoException::periodoNoExiste($anio, $mes);
        }
    }

    private function avisarApertura(Apertura $apertura, string $clave, string $motivo): void
    {
        $establecimientos = $apertura->esParaTodos()
            ? Establecimiento::where('activo', true)->get()
            : Establecimiento::whereKey($apertura->establecimiento_id)->get();

        $destinatarios = $establecimientos
            ->flatMap(fn (Establecimiento $e) => $e->usuarios()->where('activo', true)->get())
            ->unique('id')
            ->values()
            ->all();

        app(Avisar::class)->enviar($destinatarios, $clave, [
            'usuario' => $apertura->resolvedor?->name ?? 'El administrador',
            'establecimiento' => $apertura->esParaTodos()
                ? 'todos los establecimientos'
                : ($apertura->establecimiento?->codigo ?? 'el establecimiento'),
            'periodo' => $apertura->periodoLegible(),
            'motivo' => $motivo,
            'url' => route('envios.index'),
        ]);
    }
}
