# Refactoring Guide — Livewire SFC + Services + Blade Partials

Este documento captura los patrones y convenciones usados para refactorizar componentes monolíticos de Livewire en esta aplicación. Úsalo como guía para refactorizar cualquier otro componente similar.

---

## Arquitectura objetivo

```
┌─────────────────────────────────────────────────────┐
│  ⚡index.blade.php  (SFC Livewire — PHP + Blade)   │
│  Solo: wire: directives, @include, state props      │
├─────────────────────────────────────────────────────┤
│  Blade Partials (⚡nombre.blade.php)                │
│  Comparten scope del padre, usan $this-> directo    │
├─────────────────────────────────────────────────────┤
│  Services (app/Services/Academic/)                  │
│  Lógica de negocio pura, sin dependencias HTTP      │
├─────────────────────────────────────────────────────┤
│  Actions (app/Actions/Academic/)                    │
│  Operaciones de persistencia (upsert, sync)         │
├─────────────────────────────────────────────────────┤
│  Controllers (app/Http/Controllers/Web/System/)     │
│  Solo: validación, orquestación, descarga PDF       │
└─────────────────────────────────────────────────────┘
```

---

## Convenciones de naming

| Capa | Convención | Ejemplo |
|------|-----------|---------|
| SFC principal | `⚡{name}.blade.php` | `⚡index.blade.php` |
| Blade partials | `⚡{name}.blade.php` | `⚡gradebook-header.blade.php` |
| Services | `{Concept}Service.php` | `GradeCalculationService.php` |
| Actions | `Save{Entity}Action.php` | `SaveGradeAction.php` |
| Controllers | `{Module}Controller.php` | `GradebookPdfController.php` |
| Rutas | Prefijo `/system/{module}/` | `/system/summaries/gradebook/` |

---

## Patrón de refactorización paso a paso

### 1. Identificar las responsabilidades del monolito

Leer el componente completo y clasificar cada método/bloque en:

- **Cálculos** → `Service` (promedios, estados, porcentajes)
- **Persistencia** → `Action` (upsert, sync, delete)
- **Detección de tipo** → `Service` (detectar materia cualitativa vs numérica)
- **Carga de datos** → `Service` (loadIndicators, loadGrades)
- **Períodos/fechas** → `Service` (isGradingOpen, isSupletorioAvailable)
- **UI/HTML** → `Blade partial` (headers, tablas, modales)

### 2. Crear Services

```php
// app/Services/Academic/{Concept}Service.php
namespace App\Services\Academic;

class {Concept}Service
{
    // Métodos puros con tipo de retorno explícito
    public function calcularPromedio(array $notas): float { }
    
    // Sin inyectar Request, session, ni view
    // Solo modelos/DB si es necesario
}
```

**Reglas:**
- Constructor injection solo para dependencias de negocio (Model classes, other services)
- Nunca inyectar `Request`, `Session`, `View`, `Redirect`
- Métodos con return type declarations
- Type hints en todos los parámetros

### 3. Crear Actions

```php
// app/Actions/Academic/Save{Entity}Action.php
namespace App\Actions\Academic;

class Save{Entity}Action
{
    public function handle(array $data, int $userId): Model
    {
        return Model::updateOrCreate(
            ['unique_key' => $data['key']],
            $data
        );
    }
}
```

**Reglas:**
- Un solo responsibility (upsert de una entidad)
- Retornar la entidad creada/actualizada
- No manejar HTTP, solo persistencia

### 4. Crear Blade Partials

```blade
{{-- resources/views/pages/system/{module}/{submodule}/{component}/⚡{partial}.blade.php --}}
@php
    // Variables que el partial necesita del scope del padre
    $someVar = $this->someMethod();
@endphp

<div class="...">
    {{-- HTML con wire: directives que apuntan al padre --}}
    <select wire:change="saveSomething({{ $id }}, $event.target.value)">
</div>
```

**Reglas:**
- Usar `$this->` para acceder a propiedades y métodos del componente Livewire padre
- Los `wire:` directives funcionan directamente (el partial comparte el scope)
- No crear Livewire components hijos — solo `@include`
- Prefijo `⚡` en el nombre del archivo

### 5. Refactorizar el SFC principal

```blade
{{-- ⚡index.blade.php --}}
<?php
    // Todos los métodos de negocio REEMPLAZADOS por Services/Actions
    // Solo quedan: propiedades wire, lifecycle hooks, delegación

    public function saveGrade(int $studentId, int $indicatorId, ?string $value): void
    {
        $this->saveGradeAction->handle([...]);
    }
?>

{{-- Solo @include directives, sin HTML de negocio --}}
<x-layouts.app :title="__('Calificaciones')">
    @include('pages.system.{...}.⚡header')
    @include('pages.system.{...}.⚡table')
    @include('pages.system.{...}.⚡modals')
</x-layouts.app>
```

---

## Patrón: Materias cualitativas

### Detección del tipo

```php
private function detectQualitativeType(int $subjectId): ?string
{
    $subject = Subject::find($subjectId);
    $name = strtolower(Str::ascii($subject->name)); // SIEMPRE Str::ascii() para quitar acentos

    if (str_contains($name, 'orientacion vocacional') || str_contains($name, 'ovp')) {
        return 'career_guidance';
    }
    if (str_contains($name, 'acompanamiento integral')) {
        return 'classroom_support';
    }
    if (str_contains($name, 'animacion a la lectura')) {
        return 'reading_promotion';
    }
    return null; // numérica
}
```

**TRAMPA:** `strtolower()` NO quita acentos. Siempre usar `Str::ascii()` antes.

### Indicadores por tipo

| Tipo | Modelo | Campo grade_id | Filtrado por grado |
|------|--------|----------------|-------------------|
| `career_guidance` | `CareerGuidanceIndicator` | Sí | Por eje según grado (8°→Autoconocimiento, 9°→Información, 10°→Toma de decisiones) |
| `classroom_support` | `IntegralClassroomSupportIndicator` | No | Por eje (Hab. Cognitivas, Sociales, Emocionales) — global |
| `reading_promotion` | `ReadingPromotionIndicator` | No | Global, sin eje |

### Agrupación por eje en vistas

```php
// En el componente o service
$hasEjeGrouping = in_array($this->qualitativeType, ['career_guidance', 'classroom_support']);
$groupedByEje = $hasEjeGrouping 
    ? collect($indicators)->groupBy(fn ($i) => $i['eje'] ?? 'General') 
    : null;
```

```blade
@if($hasEjeGrouping && $groupedByEje)
    @foreach($groupedByEje as $ejeName => $ejeIndicators)
        <th colspan="{{ count($ejeIndicators) }}">{{ $ejeName }}</th>
    @endforeach
@else
    @foreach($indicators as $ind)
        <th>{{ $ind['name'] }}</th>
    @endforeach
@endif
```

### Helper `getEjeForGrade()`

```php
private function getEjeForGrade(?int $gradeId): ?string
{
    if (!$gradeId) return null;
    $grade = Grade::find($gradeId);
    $name = strtolower($grade->grade_name);
    
    if (str_contains($name, '8'))  return 'Autoconocimiento';
    if (str_contains($name, '9'))  return 'Informacion';
    if (str_contains($name, '10')) return 'Toma de decisiones';
    return null;
}
```

---

## Patrón: PDF con dompdf

### Estructura del controller

```php
public function qualitativeReport(Request $request)
{
    $validated = $request->validate([...]);
    // 1. Cargar datos (Students, Indicators, Grades)
    // 2. Detectar tipo cualitativo
    // 3. Filtrar indicadores por eje si aplica
    // 4. Pasar todo a la vista PDF
    // 5. Retornar $pdf->download()
}
```

### Vista PDF

- Estilos inline en `<style>` (dompdf no soporta Tailwind)
- Usar `asset('storage/...')` para logos
- La misma lógica de agrupación por eje que el gradebook web
- Helper functions dentro de `@php` blocks para cálculos

---

## Patrón: Periodos de calificación

```php
// GradingPeriodService
public function isGradingOpen(int $periodId): bool
{
    $period = GradingCalendar::find($periodId);
    return $period?->start_date <= now() && $period->end_date >= now();
}

public function isSupletorioAvailable(int $periodId): bool
{
    // Solo disponible después del cierre del trimestre
    return !$this->isGradingOpen($periodId);
}
```

---

## Checklist de refactorización

- [ ] Leer el monolito completo y mapear responsabilidades
- [ ] Crear Services para lógica de negocio
- [ ] Crear Actions para persistencia
- [ ] Crear Blade partials para UI
- [ ] Refactorizar SFC principal: solo wire: + @include
- [ ] Mantener todas las funcionalidades existentes
- [ ] Corregir bugs conocidos (Str::ascii, eje filtering)
- [ ] Ejecutar `vendor/bin/pint` en archivos PHP modificados
- [ ] Verificar que las rutas existen y funcionan
- [ ] Probar: selección de materia, calificación, PDF, períodos

---

## Bugs conocidos a revisar siempre

1. **Acentos en materias:** Siempre `Str::ascii(strtolower($name))` — nunca `strtolower()` solo
2. **Filtrado por eje de OVP:** Usar `getEjeForGrade()` + `where('eje', $eje)` + `orWhereNull('grade_id')`
3. **Indicadores sin grade_id:** Algunas tablas no tienen esa columna — verificar antes de filtrar
4. **Canvas en dompdf:** No soporta `canvas` HTML — usar tablas simples
