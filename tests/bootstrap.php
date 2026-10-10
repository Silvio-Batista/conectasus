<?php

/*
|--------------------------------------------------------------------------
| Bootstrap da suíte de testes
|--------------------------------------------------------------------------
|
| O Laravel resolve env() consultando $_SERVER antes de $_ENV. Dentro do Docker,
| o compose injeta DB_CONNECTION=mysql e DB_HOST=db no processo, e as entradas
| <env> do phpunit.xml alteram apenas $_ENV/putenv — o que faria a suíte rodar
| (e apagar) o banco de desenvolvimento. Como o PHPUnit aplica as <env> antes
| deste arquivo, basta espelhar em $_SERVER os valores que ele definiu em $_ENV.
|
*/

require __DIR__.'/../vendor/autoload.php';

foreach ($_ENV as $name => $value) {
    if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== $value) {
        $_SERVER[$name] = $value;
    }
}
