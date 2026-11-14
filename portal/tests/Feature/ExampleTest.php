<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La portada no tiene pantalla propia: redirige al login o al panel.
     */
    public function test_la_portada_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
