<?php

use App\Actions\Recetas\ActualizarRecetaPublicada;
use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\PasoReceta;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\Subcategoria;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function adminEditorRecetas(): User
{
    return User::factory()->create([
        'rol' => 'administrador',
        'activo' => true,
    ]);
}

function recetaPublicadaConRelaciones(): Receta
{
    Storage::fake('local');

    $categoria = Categoria::factory()->create(['nombre' => 'Platos de fondo']);
    $subcategoria = Subcategoria::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Criollos',
    ]);
    $ingrediente = Ingrediente::factory()->create(['nombre' => 'Arroz']);

    $receta = Receta::factory()->publicada()->create([
        'nombre' => 'Arroz con pato norteño',
        'descripcion' => 'Preparación tradicional con culantro y cerveza negra.',
        'porciones' => 4,
        'tiempo_preparacion' => 60,
        'tips' => 'Reposar 10 minutos antes de servir.',
        'categoria_id' => $categoria->id,
        'subcategoria_id' => $subcategoria->id,
    ]);

    $rutaImagen = "recetas/{$receta->id}/original.png";
    Storage::disk('local')->put(
        $rutaImagen,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII=')
    );
    $receta->update(['imagen' => $rutaImagen]);

    $receta->ingredientes()->attach($ingrediente->id, [
        'cantidad' => 2.5,
        'unidad' => 'tazas',
        'notas' => 'Graneado',
        'orden' => 1,
    ]);
    $receta->pasos()->create([
        'orden' => 1,
        'instruccion' => 'Dorar las presas y agregar el arroz.',
    ]);

    return $receta->fresh(['categoria', 'subcategoria', 'ingredientes', 'pasos']);
}

test('exige autenticacion y rol de administrador activo en todas las rutas del editor de recetas publicadas', function () {
    $receta = recetaPublicadaConRelaciones();

    $this->get('/recetas')->assertRedirect('/login');
    $this->get("/recetas/{$receta->id}")->assertRedirect('/login');
    $this->get("/recetas/{$receta->id}/editar")->assertRedirect('/login');
    $this->get("/recetas/{$receta->id}/imagen")->assertRedirect('/login');
    $this->put("/recetas/{$receta->id}", ['nombre' => 'Cambio no autorizado'])->assertRedirect('/login');

    foreach ([['usuario', true], ['administrador', false]] as [$rol, $activo]) {
        $cuenta = User::factory()->create(['rol' => $rol, 'activo' => $activo]);

        $this->actingAs($cuenta)->get('/recetas')->assertRedirect('/login');
        $this->actingAs($cuenta)->get("/recetas/{$receta->id}")->assertRedirect('/login');
        $this->actingAs($cuenta)->get("/recetas/{$receta->id}/editar")->assertRedirect('/login');
        $this->actingAs($cuenta)->get("/recetas/{$receta->id}/imagen")->assertRedirect('/login');
        $this->actingAs($cuenta)->put("/recetas/{$receta->id}", ['nombre' => 'Cambio no autorizado'])->assertRedirect('/login');
    }

    $this->assertDatabaseHas('recetas', [
        'id' => $receta->id,
        'nombre' => 'Arroz con pato norteño',
    ]);
});

test('localiza y muestra solo recetas publicadas y vigentes e impide acceder o editar recetas privadas o eliminadas incluso manipulando la url', function () {
    $admin = adminEditorRecetas();
    $publicada = recetaPublicadaConRelaciones();

    $privada = Receta::factory()->create([
        'nombre' => 'Borrador Privado Secreto',
        'publicada_en' => null,
    ]);

    $eliminada = Receta::factory()->publicada()->create([
        'nombre' => 'Receta Publicada Eliminada',
    ]);
    $eliminada->delete();

    // El listado solo incluye la receta publicada vigente
    $this->actingAs($admin)
        ->get('/recetas')
        ->assertOk()
        ->assertSee('Arroz con pato norteño')
        ->assertDontSee('Borrador Privado Secreto')
        ->assertDontSee('Receta Publicada Eliminada');

    // Filtro por búsqueda localiza la receta publicada
    $this->actingAs($admin)
        ->get('/recetas?buscar=norteño')
        ->assertOk()
        ->assertSee('Arroz con pato norteño');

    // Ver detalle, formulario de edición e imagen de la receta publicada vigente
    $this->actingAs($admin)
        ->get("/recetas/{$publicada->id}")
        ->assertOk()
        ->assertSee('Arroz con pato norteño')
        ->assertSee('Platos de fondo')
        ->assertSee('Criollos');

    $this->actingAs($admin)
        ->get("/recetas/{$publicada->id}/editar")
        ->assertOk()
        ->assertSee('Editar receta publicada #'.$publicada->id)
        ->assertSee('Arroz con pato norteño');

    $this->actingAs($admin)
        ->get("/recetas/{$publicada->id}/imagen")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private');

    // Manipular la URL con una receta privada o una receta eliminada devuelve 404 en todas las rutas
    foreach ([$privada, $eliminada] as $noEditable) {
        $this->actingAs($admin)->get("/recetas/{$noEditable->id}")->assertNotFound();
        $this->actingAs($admin)->get("/recetas/{$noEditable->id}/editar")->assertNotFound();
        $this->actingAs($admin)->get("/recetas/{$noEditable->id}/imagen")->assertNotFound();
        $this->actingAs($admin)->put("/recetas/{$noEditable->id}", [
            'nombre' => 'Intento de edición prohibido',
            'descripcion' => 'Descripción',
            'porciones' => 2,
            'tiempo_preparacion' => 20,
            'categoria_id' => $publicada->categoria_id,
            'subcategoria_id' => $publicada->subcategoria_id,
            'ingredientes' => [
                ['ingrediente_id' => $publicada->ingredientes->first()->id, 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => null, 'orden' => 1],
            ],
            'pasos' => [
                ['orden' => 1, 'instruccion' => 'Paso 1'],
            ],
        ])->assertNotFound();

        expect(fn () => app(ActualizarRecetaPublicada::class)->ejecutar($admin, $noEditable, []))
            ->toThrow(AuthorizationException::class);
    }

    $this->assertDatabaseHas('recetas', [
        'id' => $privada->id,
        'nombre' => 'Borrador Privado Secreto',
    ]);
    $this->assertDatabaseHas('recetas', [
        'id' => $eliminada->id,
        'nombre' => 'Receta Publicada Eliminada',
    ]);
});

test('edita directamente todos los campos de una receta publicada incluso ante cambios sustanciales, sin crear solicitud de revision y conservando id, valoraciones y favoritos', function () {
    $admin = adminEditorRecetas();
    $receta = recetaPublicadaConRelaciones();
    $idOriginal = $receta->id;
    $publicadaEnOriginal = $receta->publicada_en;

    $valoracion1 = Valoracion::factory()->create(['receta_id' => $receta->id, 'puntuacion' => 5]);
    $valoracion2 = Valoracion::factory()->create(['receta_id' => $receta->id, 'puntuacion' => 4]);
    $receta->usuariosQueLaGuardaron()->attach($valoracion1->usuario_id);

    $nuevaCategoria = Categoria::factory()->create(['nombre' => 'Marinos']);
    $nuevaSubcategoria = Subcategoria::factory()->create([
        'categoria_id' => $nuevaCategoria->id,
        'nombre' => 'Ceviches',
    ]);
    $ingPescado = Ingrediente::factory()->create(['nombre' => 'Pescado blanco']);
    $ingLimon = Ingrediente::factory()->create(['nombre' => 'Limón sutil']);

    $nuevaImagen = UploadedFile::fake()->createWithContent(
        'ceviche.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII=')
    );

    $solicitudesAntes = SolicitudRevision::query()->count();

    $this->actingAs($admin)
        ->put("/recetas/{$receta->id}", [
            'nombre' => '  Ceviche Clásico de Pescado  ',
            'descripcion' => '  Pescado fresco marinado al instante en jugo de limón sutil.  ',
            'imagen' => $nuevaImagen,
            'porciones' => 2,
            'tiempo_preparacion' => 15,
            'tips' => '  Usar pescado del día bien frío.  ',
            'categoria_id' => $nuevaCategoria->id,
            'subcategoria_id' => $nuevaSubcategoria->id,
            'ingredientes' => [
                [
                    'ingrediente_id' => $ingPescado->id,
                    'cantidad' => 500,
                    'unidad' => 'gramos',
                    'notas' => 'Cortado en cubos',
                    'orden' => 1,
                ],
                [
                    'ingrediente_id' => $ingLimon->id,
                    'cantidad' => 10,
                    'unidad' => 'unidades',
                    'notas' => 'Recién exprimidos',
                    'orden' => 2,
                ],
            ],
            'pasos' => [
                [
                    'orden' => 1,
                    'instruccion' => 'Salpimentar los cubos de pescado en un bol frío.',
                ],
                [
                    'orden' => 2,
                    'instruccion' => 'Verter el jugo de limón, mezclar brevemente y servir de inmediato.',
                ],
            ],
        ])
        ->assertRedirect("/recetas/{$receta->id}/editar")
        ->assertSessionHas('exito');

    // No se creó ninguna solicitud de revisión
    expect(SolicitudRevision::query()->count())->toBe($solicitudesAntes);

    $actualizada = $receta->fresh(['categoria', 'subcategoria', 'ingredientes', 'pasos', 'valoraciones']);
    expect($actualizada->id)->toBe($idOriginal);
    expect($actualizada->nombre)->toBe('Ceviche Clásico de Pescado');
    expect($actualizada->descripcion)->toBe('Pescado fresco marinado al instante en jugo de limón sutil.');
    expect($actualizada->porciones)->toBe(2);
    expect($actualizada->tiempo_preparacion)->toBe(15);
    expect($actualizada->tips)->toBe('Usar pescado del día bien frío.');
    expect($actualizada->categoria_id)->toBe($nuevaCategoria->id);
    expect($actualizada->subcategoria_id)->toBe($nuevaSubcategoria->id);
    expect($actualizada->actualizado_por)->toBe($admin->id);
    expect($actualizada->version)->toBe(2);
    expect($actualizada->publicada_en->equalTo($publicadaEnOriginal))->toBeTrue();
    expect($actualizada->imagen)->not->toBe("recetas/{$receta->id}/original.png");
    expect(Storage::disk('local')->exists($actualizada->imagen))->toBeTrue();

    // Ingredientes y pasos reemplazados coherentemente
    expect($actualizada->ingredientes->pluck('id')->all())->toBe([$ingPescado->id, $ingLimon->id]);
    expect((float) $actualizada->ingredientes[0]->pivot->cantidad)->toBe(500.0);
    expect($actualizada->ingredientes[0]->pivot->unidad)->toBe('gramos');
    expect($actualizada->ingredientes[0]->pivot->notas)->toBe('Cortado en cubos');
    expect($actualizada->pasos->pluck('instruccion')->all())->toBe([
        'Salpimentar los cubos de pescado en un bol frío.',
        'Verter el jugo de limón, mezclar brevemente y servir de inmediato.',
    ]);

    // Conserva intactas las valoraciones y favoritos
    expect($actualizada->valoraciones->pluck('id')->sort()->values()->all())
        ->toBe([$valoracion1->id, $valoracion2->id]);
    $this->assertDatabaseHas('favoritos', [
        'receta_id' => $receta->id,
        'usuario_id' => $valoracion1->usuario_id,
    ]);
});

test('exige una categoria y una subcategoria perteneciente a ella al guardar y permite clasificar recetas publicadas historicas sin clasificacion', function () {
    Storage::fake('local');
    $admin = adminEditorRecetas();

    $catPostres = Categoria::factory()->create(['nombre' => 'Postres']);
    $subFrios = Subcategoria::factory()->create([
        'categoria_id' => $catPostres->id,
        'nombre' => 'Fríos',
    ]);
    $catBebidas = Categoria::factory()->create(['nombre' => 'Bebidas']);
    $subCalientes = Subcategoria::factory()->create([
        'categoria_id' => $catBebidas->id,
        'nombre' => 'Calientes',
    ]);
    $ingLeche = Ingrediente::factory()->create(['nombre' => 'Leche condensada']);

    // Receta histórica publicada sin categoría ni subcategoría
    $historica = (new Receta)->permitirSinClasificacionHistorica()->forceFill([
        'nombre' => 'Suspiro a la Limeña Histórico',
        'descripcion' => 'Receta publicada antes de la transición de categorías.',
        'imagen' => 'recetas/1/suspiro.png',
        'porciones' => 6,
        'tiempo_preparacion' => 45,
        'tips' => 'Servir con merengue al oporto.',
        'categoria_id' => null,
        'subcategoria_id' => null,
        'creado_por' => $admin->id,
        'publicada_en' => now()->subDays(5),
        'version' => 1,
    ]);
    $historica->save();
    Storage::disk('local')->put(
        "recetas/{$historica->id}/suspiro.png",
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII=')
    );
    $historica->permitirSinClasificacionHistorica()->update(['imagen' => "recetas/{$historica->id}/suspiro.png"]);
    $historica->ingredientes()->attach($ingLeche->id, [
        'cantidad' => 1,
        'unidad' => 'lata',
        'notas' => null,
        'orden' => 1,
    ]);
    $historica->pasos()->create([
        'orden' => 1,
        'instruccion' => 'Preparar el manjar de yemas.',
    ]);

    // Se localiza con el filtro de históricas sin clasificar y muestra el aviso en el editor
    $this->actingAs($admin)
        ->get('/recetas?clasificacion=sin_clasificar')
        ->assertOk()
        ->assertSee('Suspiro a la Limeña Histórico');

    $this->actingAs($admin)
        ->get("/recetas/{$historica->id}/editar")
        ->assertOk()
        ->assertSee('Receta histórica sin clasificación.');

    $datosBase = [
        'nombre' => 'Suspiro a la Limeña Clasificado',
        'descripcion' => 'Receta histórica ahora clasificada.',
        'porciones' => 6,
        'tiempo_preparacion' => 45,
        'tips' => 'Espolvorear canela.',
        'ingredientes' => [
            [
                'ingrediente_id' => $ingLeche->id,
                'cantidad' => 1,
                'unidad' => 'lata',
                'notas' => null,
                'orden' => 1,
            ],
        ],
        'pasos' => [
            [
                'orden' => 1,
                'instruccion' => 'Preparar el manjar de yemas y coronar con merengue.',
            ],
        ],
    ];

    // 1. Rechaza guardar sin categoría y subcategoría
    $this->actingAs($admin)
        ->from("/recetas/{$historica->id}/editar")
        ->put("/recetas/{$historica->id}", array_merge($datosBase, [
            'categoria_id' => null,
            'subcategoria_id' => null,
        ]))
        ->assertRedirect("/recetas/{$historica->id}/editar")
        ->assertSessionHasErrors(['categoria_id', 'subcategoria_id']);

    // 2. Rechaza guardar si la subcategoría pertenece a otra categoría
    $this->actingAs($admin)
        ->from("/recetas/{$historica->id}/editar")
        ->put("/recetas/{$historica->id}", array_merge($datosBase, [
            'categoria_id' => $catPostres->id,
            'subcategoria_id' => $subCalientes->id,
        ]))
        ->assertRedirect("/recetas/{$historica->id}/editar")
        ->assertSessionHasErrors('subcategoria_id');

    expect($historica->fresh()->categoria_id)->toBeNull();
    expect($historica->fresh()->subcategoria_id)->toBeNull();

    // 3. Permite asignar una categoría y subcategoría válidas y guardar los cambios
    $this->actingAs($admin)
        ->put("/recetas/{$historica->id}", array_merge($datosBase, [
            'categoria_id' => $catPostres->id,
            'subcategoria_id' => $subFrios->id,
        ]))
        ->assertRedirect("/recetas/{$historica->id}/editar")
        ->assertSessionHas('exito');

    $this->assertDatabaseHas('recetas', [
        'id' => $historica->id,
        'nombre' => 'Suspiro a la Limeña Clasificado',
        'categoria_id' => $catPostres->id,
        'subcategoria_id' => $subFrios->id,
    ]);
});

test('revierte todos los cambios si ocurre un fallo al guardar los pasos y revalida al administrador en la accion', function () {
    $admin = adminEditorRecetas();
    $receta = recetaPublicadaConRelaciones();
    $antes = $receta->load('ingredientes', 'categoria', 'subcategoria', 'pasos')->toArray();

    PasoReceta::creating(function (): void {
        throw new RuntimeException('Error simulado al insertar pasos');
    });

    try {
        $this->actingAs($admin)
            ->from("/recetas/{$receta->id}/editar")
            ->put("/recetas/{$receta->id}", [
                'nombre' => 'Cambio que debe revertirse',
                'descripcion' => 'Descripción temporal',
                'porciones' => 8,
                'tiempo_preparacion' => 90,
                'tips' => null,
                'categoria_id' => $receta->categoria_id,
                'subcategoria_id' => $receta->subcategoria_id,
                'ingredientes' => [
                    [
                        'ingrediente_id' => $receta->ingredientes->first()->id,
                        'cantidad' => 5,
                        'unidad' => 'tazas',
                        'notas' => null,
                        'orden' => 1,
                    ],
                ],
                'pasos' => [
                    [
                        'orden' => 1,
                        'instruccion' => 'Paso que fallará',
                    ],
                ],
            ])
            ->assertRedirect("/recetas/{$receta->id}/editar")
            ->assertSessionHas('error');
    } finally {
        PasoReceta::flushEventListeners();
    }

    expect($receta->fresh()->load('ingredientes', 'categoria', 'subcategoria', 'pasos')->toArray())
        ->toEqual($antes);

    // Si el administrador es deshabilitado en BD mientras su instancia en memoria sigue activa, la acción rechaza
    User::query()->whereKey($admin->id)->update(['activo' => false]);

    expect(fn () => app(ActualizarRecetaPublicada::class)->ejecutar($admin, $receta, []))
        ->toThrow(AuthorizationException::class);
});
