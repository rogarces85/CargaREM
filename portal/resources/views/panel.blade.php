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
        <div class="col-lg-4 col-12">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>—</h3>
                    <p>Envíos del período</p>
                </div>
                <div class="icon"><i class="fas fa-file-upload"></i></div>
            </div>
        </div>
        <div class="col-lg-4 col-12">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>—</h3>
                    <p>En revisión</p>
                </div>
                <div class="icon"><i class="fas fa-hourglass-half"></i></div>
            </div>
        </div>
        <div class="col-lg-4 col-12">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>—</h3>
                    <p>Aprobados</p>
                </div>
                <div class="icon"><i class="fas fa-check"></i></div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <p class="mb-0">
                F1 en curso: autenticación y permisos. Los indicadores se conectarán en F2, cuando existan envíos.
            </p>
        </div>
    </div>
@endsection
