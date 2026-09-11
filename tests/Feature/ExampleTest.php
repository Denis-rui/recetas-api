<?php

test('la ruta raiz redirige hacia la plataforma administrativa', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
});
