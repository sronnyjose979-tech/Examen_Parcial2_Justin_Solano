<?php

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
| SOLUCIÓN [B6]
*/

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('crea un equipo y responde 201 con el encabezado Location', function () {
    $categoria = Categoria::factory()->create();

    $respuesta = $this->postJson('/api/equipos', [
        'codigo'            => 'EQ-0100',
        'nombre'            => 'Proyector Epson PowerLite X49',
        'categoria_id'      => $categoria->id,
        'fecha_adquisicion' => '2026-03-10',
        'costo_interno'     => 425000,
    ]);

    $respuesta->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.codigo', 'EQ-0100')
        ->assertJsonPath('data.estado', 'disponible')
        ->assertJsonPath('data.categoria.id', $categoria->id)
        ->assertJsonMissingPath('data.costo_interno');

    expect($respuesta->headers->get('Location'))
        ->toEndWith('/api/equipos/'.$respuesta->json('data.id'));

    $this->assertDatabaseHas('equipos', ['codigo' => 'EQ-0100', 'estado' => 'disponible']);
});

it('rechaza un equipo con datos inválidos y responde 422 con errores por campo', function () {
    Equipo::factory()->create(['codigo' => 'EQ-0001']);

    $this->postJson('/api/equipos', [
        'codigo'            => 'EQ-0001',                       // duplicado
        'nombre'            => 'X',                             // muy corto
        'fecha_adquisicion' => now()->addDay()->toDateString(), // futura
        'costo_interno'     => -5,                              // negativo
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['codigo', 'nombre', 'categoria_id', 'fecha_adquisicion', 'costo_interno'])
        ->assertJsonPath('errors.codigo.0', 'Ya existe un equipo con ese código.');

    expect(Equipo::count())->toBe(1);
});

it('impide eliminar un equipo prestado y responde 409', function () {
    $equipo = Equipo::factory()->prestado()->create();

    $this->deleteJson("/api/equipos/{$equipo->id}")
        ->assertStatus(409)
        ->assertExactJson([
            'mensaje' => 'No se puede eliminar un equipo prestado.',
            'codigo'  => 'REGLA_NEGOCIO',
        ]);

    $this->assertModelExists($equipo);
});
