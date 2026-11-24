<?php

/*
|--------------------------------------------------------------------------
| SUITE DE CALIFICACIÓN — USO EXCLUSIVO DEL DOCENTE
|--------------------------------------------------------------------------
| Copie este archivo en tests/Feature/ del repositorio de cada estudiante y ejecute:
|
|     php artisan test --filter=Calificacion
|
| Cada prueba indica el criterio de la rúbrica al que aporta evidencia.
| Una prueba en rojo NO equivale automáticamente a cero en el criterio:
| la rúbrica se aplica leyendo el código; esta suite solo acelera la revisión.
*/

use App\Http\Controllers\Api\EquipoController;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    $this->categoria = Categoria::factory()->create();
});

$datosValidos = fn (array $cambios = []) => array_merge([
    'codigo'            => 'EQ-0500',
    'nombre'            => 'Laptop Dell Latitude 5440',
    'fecha_adquisicion' => '2026-01-15',
    'costo_interno'     => 650000,
], $cambios);

/* ---------------- B1 · Form Request ---------------- */

it('[B1] exige todos los campos con mensajes en español por campo', function () {
    $respuesta = $this->postJson('/api/equipos', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['codigo', 'nombre', 'categoria_id', 'fecha_adquisicion', 'costo_interno']);

    foreach ($respuesta->json('errors') as $campo => [$mensaje]) {
        expect($mensaje)->not->toMatch('/\b(field|required|must)\b/i', "Mensaje en inglés en {$campo}");
    }
});

it('[B1] valida el formato EQ-0000 del código', function () use ($datosValidos) {
    $this->postJson('/api/equipos', $datosValidos(['codigo' => 'A-1', 'categoria_id' => $this->categoria->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['codigo']);
});

it('[B1] no permite asignar el estado prestado al crear', function () use ($datosValidos) {
    $this->postJson('/api/equipos', $datosValidos(['estado' => 'prestado', 'categoria_id' => $this->categoria->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estado']);
});

it('[B1] la regla unique ignora al propio equipo al actualizar', function () use ($datosValidos) {
    $equipo = Equipo::factory()->for($this->categoria)->create(['codigo' => 'EQ-0500']);

    $this->putJson("/api/equipos/{$equipo->id}", $datosValidos([
        'nombre'       => 'Nombre corregido',
        'categoria_id' => $this->categoria->id,
    ]))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Nombre corregido');
});

/* ---------------- B2 · Servicio y reglas ---------------- */

it('[B2] R1 impide eliminar un equipo prestado (409)', function () {
    $equipo = Equipo::factory()->for($this->categoria)->prestado()->create();

    $this->deleteJson("/api/equipos/{$equipo->id}")->assertStatus(409);
    $this->assertModelExists($equipo);
});

it('[B2] elimina un equipo disponible (204)', function () {
    $equipo = Equipo::factory()->for($this->categoria)->create();

    $this->deleteJson("/api/equipos/{$equipo->id}")->assertNoContent();
    $this->assertModelMissing($equipo);
});

it('[B2] R2 impide prestar un equipo en mantenimiento (409)', function () {
    $equipo = Equipo::factory()->for($this->categoria)->enMantenimiento()->create();

    $this->postJson("/api/equipos/{$equipo->id}/prestamo")
        ->assertStatus(409)
        ->assertJsonPath('mensaje', 'No se puede prestar un equipo en mantenimiento.');
});

it('[B2] R2 impide prestar un equipo ya prestado (409)', function () {
    $equipo = Equipo::factory()->for($this->categoria)->prestado()->create();

    $this->postJson("/api/equipos/{$equipo->id}/prestamo")
        ->assertStatus(409)
        ->assertJsonPath('mensaje', 'El equipo ya se encuentra prestado.');
});

it('[B2] presta un equipo disponible (200)', function () {
    $equipo = Equipo::factory()->for($this->categoria)->create();

    $this->postJson("/api/equipos/{$equipo->id}/prestamo")
        ->assertOk()
        ->assertJsonPath('data.estado', 'prestado');
});

it('[B2] filtra por texto en código o nombre', function () {
    Equipo::factory()->for($this->categoria)->create(['codigo' => 'EQ-0001', 'nombre' => 'Raspberry Pi 5 8 GB']);
    Equipo::factory()->for($this->categoria)->create(['codigo' => 'EQ-0002', 'nombre' => 'Multímetro Fluke 117']);

    $this->getJson('/api/equipos?q=raspberry')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.codigo', 'EQ-0001');
});

it('[B2] el servicio no conoce HTTP', function () {
    $codigo = file_get_contents(app_path('Services/EquipoService.php'));

    expect($codigo)->not->toMatch('/\b(request|response|abort)\s*\(/')
        ->and($codigo)->not->toContain('Illuminate\\Http');
});

it('[B2] el listado no sufre N+1', function () {
    Equipo::factory()->count(10)->create();   // 10 equipos, cada uno con su propia categoría

    DB::enableQueryLog();
    $this->getJson('/api/equipos')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
});

/* ---------------- B3 · Controlador y códigos ---------------- */

it('[B3] crea con 201 y encabezado Location', function () use ($datosValidos) {
    $respuesta = $this->postJson('/api/equipos', $datosValidos(['categoria_id' => $this->categoria->id]))
        ->assertCreated()
        ->assertHeader('Location');

    expect($respuesta->headers->get('Location'))->toEndWith('/api/equipos/'.$respuesta->json('data.id'));
});

it('[B3] responde 404 en JSON para un equipo inexistente', function () {
    $this->getJson('/api/equipos/999999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
});

it('[B3] ningún método del controlador supera 15 líneas', function () {
    $clase = new ReflectionClass(EquipoController::class);

    foreach ($clase->getMethods() as $metodo) {
        if ($metodo->class !== EquipoController::class || $metodo->name === '__construct') {
            continue;
        }
        // firma + '{' ... '}' : se descuentan la línea de la firma y las dos llaves
        $lineas = $metodo->getEndLine() - $metodo->getStartLine() - 2;
        expect($lineas)->toBeLessThanOrEqual(15, "{$metodo->name} tiene {$lineas} líneas");
    }
});

/* ---------------- B4 · Resource y paginación ---------------- */

it('[B4] la respuesta tiene exactamente la forma del contrato', function () {
    $equipo = Equipo::factory()->for($this->categoria)->create();

    $datos = $this->getJson("/api/equipos/{$equipo->id}")->assertOk()->json('data');

    expect(array_keys($datos))->toEqualCanonicalizing(['id', 'codigo', 'nombre', 'estado', 'fecha_adquisicion', 'categoria'])
        ->and(array_keys($datos['categoria']))->toEqualCanonicalizing(['id', 'nombre'])
        ->and($datos['fecha_adquisicion'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('[B4] pagina con metadatos, 10 por omisión y tope de 50', function () {
    Equipo::factory()->count(60)->for($this->categoria)->create();

    $this->getJson('/api/equipos')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'last_page', 'total']]);

    $this->getJson('/api/equipos?per_page=999999')
        ->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.per_page', 50);
});

/* ---------------- B5 · Errores centralizados ---------------- */

it('[B5] traduce la regla de negocio a 409 con el cuerpo acordado', function () {
    $equipo = Equipo::factory()->for($this->categoria)->prestado()->create();

    $this->deleteJson("/api/equipos/{$equipo->id}")
        ->assertStatus(409)
        ->assertExactJson(['mensaje' => 'No se puede eliminar un equipo prestado.', 'codigo' => 'REGLA_NEGOCIO']);
});
