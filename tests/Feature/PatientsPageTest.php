<?php

namespace Tests\Feature;

use Tests\TestCase;

class PatientsPageTest extends TestCase
{
    public function test_home_page_renders_the_patients_interface(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Gerenciamento de Pacientes')
            ->assertSee('Cadastre, consulte e gerencie pacientes vinculados às unidades de saúde.')
            ->assertSee('css/app.css')
            ->assertSee('js/patients.js');
    }
}
