<?php

use App\Actions\Categorias\CrearCategoria;
use App\Actions\Categorias\EliminarCategoria;
use App\Actions\Categorias\EliminarSubcategoria;
use App\Models\Categoria;
use App\Models\Receta;
use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

function adminCategorias(): User
{
    return User::factory()->create([
        'rol' => 'administrador',
        'activo' => true,
    ]);
}

test('exige autenticacion para acceder al modulo web de categorias', function () {
    $categoria = Categoria::factory()->create(['nombre' => ' Sopas ']);
    $subcategoria = Subcategoria::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Caldos',
    ]);

    $this->get('/categorias')->assertRedirect('/login');
    $this->post('/categorias', ['nombre' => 'Postres'])->assertRedirect('/login');
    $this->put("/categorias/{$categoria->id}", ['nombre' => 'Sopas y Caldos'])->assertRedirect('/login');
    $this->delete("/categorias/{$categoria->id}")->assertRedirect('/login');
    $this->post("/categorias/{$categoria->id}/subcategorias", ['nombre' => 'Cremas'])->assertRedirect('/login');
    $this->put("/categorias/{$categoria->id}/subcategorias/{$subcategoria->id}", ['nombre' => 'Cremas calientes'])->assertRedirect('/login');
    $this->delete("/categorias/{$categoria->id}/subcategorias/{$subcategoria->id}")->assertRedirect('/login');
});

test('rechaza el acceso web a categorias si la cuenta no es administradora activa', function (string $rol, bool $activo) {
    $categoria = Categoria::factory()->create(['nombre' => 'Entradas']);
    $subcategoria = Subcategoria::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Frías',
    ]);
    $usuario = User::factory()->create(['rol' => $rol, 'activo' => $activo]);

    $this->actingAs($usuario)->get('/categorias')->assertRedirect('/login');
    $this->actingAs($usuario)->post('/categorias', ['nombre' => 'Nueva'])->assertRedirect('/login');
    $this->actingAs($usuario)->put("/categorias/{$categoria->id}", ['nombre' => 'Modificada'])->assertRedirect('/login');
    $this->actingAs($usuario)->delete("/categorias/{$categoria->id}")->assertRedirect('/login');
    $this->actingAs($usuario)->post("/categorias/{$categoria->id}/subcategorias", ['nombre' => 'Calientes'])->assertRedirect('/login');
    $this->actingAs($usuario)->put("/categorias/{$categoria->id}/subcategorias/{$subcategoria->id}", ['nombre' => 'Templadas'])->assertRedirect('/login');
    $this->actingAs($usuario)->delete("/categorias/{$categoria->id}/subcategorias/{$subcategoria->id}")->assertRedirect('/login');

    $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'nombre' => 'Entradas']);
    $this->assertDatabaseHas('subcategorias', ['id' => $subcategoria->id, 'nombre' => 'Frías']);
})->with([
    'usuario normal activo' => ['usuario', true],
    'administrador deshabilitado' => ['administrador', false],
]);

test('revalida permisos del administrador en las acciones aunque el objeto en memoria siga activo', function () {
    $admin = adminCategorias();
    $categoria = Categoria::factory()->create(['nombre' => 'Bebidas']);
    $subcategoria = Subcategoria::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Jugos',
    ]);

    User::query()->whereKey($admin->id)->update(['activo' => false]);

    expect(fn () => app(CrearCategoria::class)->ejecutar($admin, ['nombre' => 'Infusiones']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(EliminarSubcategoria::class)->ejecutar($admin, $categoria, $subcategoria))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(EliminarCategoria::class)->ejecutar($admin, $categoria))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
    $this->assertDatabaseHas('subcategorias', ['id' => $subcategoria->id]);
});

test('permite crear categorias sin subcategorias, mostrandolas en administracion pero ocultandolas en filtros publicos', function () {
    $admin = adminCategorias();

    $this->actingAs($admin)
        ->post('/categorias', ['nombre' => '  Panadería Artesanal  '])
        ->assertRedirect('/categorias')
        ->assertSessionHas('exito');

    $categoria = Categoria::query()->where('nombre', 'Panadería Artesanal')->firstOrFail();

    $this->actingAs($admin)
        ->get('/categorias')
        ->assertOk()
        ->assertSee('Panadería Artesanal')
        ->assertSee('Sin subcategorías · Oculta en filtros públicos');

    $this->getJson('/api/v1/categorias')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/categorias')
        ->assertOk()
        ->assertJsonPath('data.0.id', $categoria->id)
        ->assertJsonPath('data.0.nombre', 'Panadería Artesanal');
});

test('exige nombres unicos de categoria, permite nombres repetidos de subcategoria y conserva datos del formulario si falla la validacion', function () {
    $admin = adminCategorias();
    $postres = Categoria::factory()->create(['nombre' => 'Postres']);
    $bebidas = Categoria::factory()->create(['nombre' => 'Bebidas']);

    // Intento de categoría duplicada conserva old('nombre') y muestra error claro
    $this->actingAs($admin)
        ->from('/categorias')
        ->post('/categorias', ['nombre' => 'Postres'])
        ->assertRedirect('/categorias')
        ->assertSessionHasErrors('nombre')
        ->assertSessionHasInput('nombre', 'Postres');

    $this->actingAs($admin)
        ->followingRedirects()
        ->from('/categorias')
        ->post('/categorias', ['nombre' => 'Postres'])
        ->assertOk()
        ->assertSee('Ya existe una categoría con este nombre.')
        ->assertSee('value="Postres"', false);

    // Permite crear subcategorías con el mismo nombre en distintas categorías y dentro de la misma categoría
    $this->actingAs($admin)
        ->post("/categorias/{$postres->id}/subcategorias", ['nombre' => 'Tradicionales'])
        ->assertRedirect('/categorias')
        ->assertSessionHas('exito');

    $this->actingAs($admin)
        ->post("/categorias/{$bebidas->id}/subcategorias", ['nombre' => 'Tradicionales'])
        ->assertRedirect('/categorias')
        ->assertSessionHas('exito');

    expect(Subcategoria::query()->where('nombre', 'Tradicionales')->count())->toBe(2);

    // Si falla la validación de una subcategoría (excede 100 caracteres), conserva el valor ingresado
    $nombreDemasiadoLargo = str_repeat('B', 105);

    $this->actingAs($admin)
        ->from('/categorias')
        ->post("/categorias/{$postres->id}/subcategorias", ['nombre' => $nombreDemasiadoLargo])
        ->assertRedirect('/categorias')
        ->assertSessionHasErrors('nombre')
        ->assertSessionHasInput('nombre', $nombreDemasiadoLargo);

    $this->actingAs($admin)
        ->followingRedirects()
        ->from('/categorias')
        ->post("/categorias/{$postres->id}/subcategorias", ['nombre' => $nombreDemasiadoLargo])
        ->assertOk()
        ->assertSee('El nombre de la subcategoría no puede superar los 100 caracteres.')
        ->assertSee('value="'.$nombreDemasiadoLargo.'"', false);
});

test('permite renombrar una categoria y una subcategoria aunque tengan recetas asociadas', function () {
    $admin = adminCategorias();
    $categoria = Categoria::factory()->create(['nombre' => 'Platos de fondo']);
    $subcategoria = Subcategoria::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Criollos',
    ]);
    $receta = Receta::factory()->publicada()->create([
        'categoria_id' => $categoria->id,
        'subcategoria_id' => $subcategoria->id,
    ]);

    $this->actingAs($admin)
        ->put("/categorias/{$categoria->id}", ['nombre' => 'Platos principales'])
        ->assertRedirect('/categorias')
        ->assertSessionHas('exito');

    $this->actingAs($admin)
        ->put("/categorias/{$categoria->id}/subcategorias/{$subcategoria->id}", ['nombre' => 'Clásicos criollos'])
        ->assertRedirect('/categorias')
        ->assertSessionHas('exito');

    $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'nombre' => 'Platos principales']);
    $this->assertDatabaseHas('subcategorias', ['id' => $subcategoria->id, 'nombre' => 'Clásicos criollos']);
    $this->assertDatabaseHas('recetas', [
        'id' => $receta->id,
        'categoria_id' => $categoria->id,
        'subcategoria_id' => $subcategoria->id,
    ]);

    // Si al renombrar una categoría se usa el nombre de otra existente, falla y conserva old('nombre')
    Categoria::factory()->create(['nombre' => 'Ensaladas']);

    $this->actingAs($admin)
        ->followingRedirects()
        ->from('/categorias')
        ->put("/categorias/{$categoria->id}", ['nombre' => 'Ensaladas'])
        ->assertOk()
        ->assertSee('Ya existe una categoría con este nombre.')
        ->assertSee('value="Ensaladas"', false);

    $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'nombre' => 'Platos principales']);
});

test('devuelve 404 si se intenta renombrar o eliminar una subcategoria bajo otra categoria distinta a la suya', function () {
    $admin = adminCategorias();
    $catA = Categoria::factory()->create(['nombre' => 'Carnes']);
    $catB = Categoria::factory()->create(['nombre' => 'Pastas']);
    $subDeB = Subcategoria::factory()->create([
        'categoria_id' => $catB->id,
        'nombre' => 'Largas',
    ]);

    $this->actingAs($admin)
        ->put("/categorias/{$catA->id}/subcategorias/{$subDeB->id}", ['nombre' => 'Cortas'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete("/categorias/{$catA->id}/subcategorias/{$subDeB->id}")
        ->assertNotFound();

    $this->actingAs($admin)
        ->put('/categorias/0', ['nombre' => 'Invalida'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete("/categorias/{$catB->id}/subcategorias/0")
        ->assertNotFound();

    $this->assertDatabaseHas('subcategorias', ['id' => $subDeB->id, 'nombre' => 'Largas']);
});

test('bloquea la eliminacion de una subcategoria o categoria si alguna receta la usa en cualquier estado y muestra errores claros', function (string $estado) {
    $admin = adminCategorias();
    $categoria = Categoria::factory()->create(['nombre' => 'Marinos']);
    $subcategoriaConReceta = Subcategoria::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Ceviches',
    ]);
    $subcategoriaLibre = Subcategoria::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Sudados',
    ]);

    $receta = Receta::factory()->create([
        'categoria_id' => $categoria->id,
        'subcategoria_id' => $subcategoriaConReceta->id,
        'publicada_en' => $estado === 'privada' ? null : now(),
    ]);

    if ($estado === 'eliminada') {
        $receta->delete();
    }

    // 1. Bloquea eliminar la subcategoría en uso
    $this->actingAs($admin)
        ->from('/categorias')
        ->delete("/categorias/{$categoria->id}/subcategorias/{$subcategoriaConReceta->id}")
        ->assertRedirect('/categorias')
        ->assertSessionHasErrors('eliminacion')
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->get('/categorias')
        ->assertOk()
        ->assertSee('No se puede eliminar la subcategoría «Ceviches» porque tiene recetas asociadas.');

    $this->assertDatabaseHas('subcategorias', ['id' => $subcategoriaConReceta->id]);

    // 2. Bloquea eliminar la categoría padre porque tiene recetas asociadas a ella y a su subcategoría
    $this->actingAs($admin)
        ->from('/categorias')
        ->delete("/categorias/{$categoria->id}")
        ->assertRedirect('/categorias')
        ->assertSessionHasErrors('eliminacion')
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->get('/categorias')
        ->assertOk()
        ->assertSee('No se puede eliminar la categoría «Marinos» porque tiene recetas asociadas.');

    $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);

    // 3. Permite eliminar otra subcategoría de esa misma categoría que no tiene recetas asociadas
    $this->actingAs($admin)
        ->delete("/categorias/{$categoria->id}/subcategorias/{$subcategoriaLibre->id}")
        ->assertRedirect('/categorias')
        ->assertSessionHas('exito');

    $this->assertDatabaseMissing('subcategorias', ['id' => $subcategoriaLibre->id]);
})->with([
    'receta publicada' => ['publicada'],
    'receta privada' => ['privada'],
    'receta eliminada (soft delete)' => ['eliminada'],
]);

test('elimina una categoria sin recetas asociadas junto con todas sus subcategorias', function () {
    $admin = adminCategorias();
    $categoria = Categoria::factory()->create(['nombre' => 'Ensaladas']);
    $sub1 = Subcategoria::factory()->create(['categoria_id' => $categoria->id, 'nombre' => 'Frescas']);
    $sub2 = Subcategoria::factory()->create(['categoria_id' => $categoria->id, 'nombre' => 'Cocidas']);

    $this->actingAs($admin)
        ->delete("/categorias/{$categoria->id}")
        ->assertRedirect('/categorias')
        ->assertSessionHas('exito');

    $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    $this->assertDatabaseMissing('subcategorias', ['id' => $sub1->id]);
    $this->assertDatabaseMissing('subcategorias', ['id' => $sub2->id]);
});
