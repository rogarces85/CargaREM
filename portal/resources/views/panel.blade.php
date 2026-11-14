@extends('layouts.panel')

@section('titulo', 'Panel')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Panel</h1>
        <p class="text-muted mb-0">
            Servicio de Salud Osorno · {{ now()->translatedFormat('F Y') }}
        </p>
    </div>

    <div class="row mt-3">
        @canany(['envios.ver.propio', 'envios.ver.ss'])
            <div class="col-lg-4 col-12">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>Envíos</h3>
                        <p>Crear, subir y seguir el estado del período</p>
                    </div>
                    <div class="icon"><i class="fas fa-file-upload"></i></div>
                    <a href="{{ route('envios.index') }}" class="small-box-footer">
                        Abrir <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        @endcanany

        @can('envios.ver.ss')
            <div class="col-lg-4 col-12">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>Revisión</h3>
                        <p>Observar o aprobar la estadística</p>
                    </div>
                    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                    <a href="{{ route('revision.index') }}" class="small-box-footer">
                        Abrir <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        @endcan

        @can('completitud.ver')
            <div class="col-lg-4 col-12">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>Completitud</h3>
                        <p>Qué entregó cada establecimiento</p>
                    </div>
                    <div class="icon"><i class="fas fa-check"></i></div>
                    <a href="{{ route('completitud.index') }}" class="small-box-footer">
                        Abrir <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        @endcan
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <p class="mb-0">
                Suba un archivo por serie. El sistema lo valida antes de enviarlo a revisión.
                El revisor aprueba la estadística completa y el archivo queda guardado en el servidor.
            </p>
        </div>
    </div>
@endsection
