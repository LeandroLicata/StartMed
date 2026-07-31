<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_manda_a_login_a_los_visitantes(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
