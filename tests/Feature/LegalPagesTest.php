<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_public_homepage_links_to_legal_information(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('legal.privacy'))
            ->assertSee(route('legal.terms'))
            ->assertSee(route('legal.data-processing'));
    }

    public function test_legal_pages_are_publicly_available(): void
    {
        $this->get(route('legal.terms'))->assertOk()->assertSee('Términos y condiciones');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Uso de datos de Google');
        $this->get(route('legal.data-processing'))->assertOk()->assertSee('Tratamiento de datos personales');
    }
}
