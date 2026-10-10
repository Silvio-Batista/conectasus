<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trava de segurança: a suíte só pode rodar em SQLite em memória.
     * Executa antes do RefreshDatabase, impedindo qualquer migrate:fresh
     * acidental sobre o banco de desenvolvimento (MySQL).
     */
    protected function setUpTraits()
    {
        $config = $this->app['config'];

        $connection = $config->get('database.default');
        $database = $config->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            $this->fail(sprintf(
                'Os testes devem usar SQLite em memória, mas a conexão atual é "%s" (%s). Verifique phpunit.xml e tests/bootstrap.php.',
                $connection,
                $database
            ));
        }

        return parent::setUpTraits();
    }
}
