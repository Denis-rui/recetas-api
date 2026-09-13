<?php

use App\Rules\IdentificadorEntero;

test('identificador admite enteros positivos y cadenas canonicas hasta el limite nativo', function (mixed $id) {
    expect(IdentificadorEntero::esValido($id))->toBeTrue();
})->with([1, '1', 32, '32', PHP_INT_MAX, (string) PHP_INT_MAX]);

test('identificador rechaza tipos que una conversion numerica podria ocultar', function (mixed $id) {
    expect(IdentificadorEntero::esValido($id))->toBeFalse();
})->with([[null], [true], [false], [1.0], [INF], [NAN], [[]], ['١'], ["1\n"]]);
