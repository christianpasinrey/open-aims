# aims — Modular convention

Cada módulo de negocio vive en `app/Modules/{ModuleName}/` y es **autodescubierto** al arrancar la aplicación (`bootstrap/providers.php` → `App\Core\Support\ModuleDiscovery`). No hace falta editar `bootstrap/providers.php` para añadir un módulo.

## Estructura canónica

```
app/Modules/{ModuleName}/
├── {ModuleName}ServiceProvider.php   # OBLIGATORIO en el root. Extiende App\Core\Support\ModuleServiceProvider.
├── {ModuleName}ModuleManifest.php    # Implementa App\Core\Contracts\ModuleManifest. Se registra en ModuleRegistry.
├── routes.php                         # Opcional; auto-cargado por el base ServiceProvider.
├── config.php                         # Opcional; mergeado en config("modules.{slug}").
├── Models/                            # Eloquent models, con BelongsToWorkspace cuando proceda.
├── Enums/                             # Backed enums.
├── Actions/                           # Use-cases invokables (`final readonly`).
├── Services/                          # Orquestación I/O y cálculos complejos.
├── Controllers/
│   ├── Settings/                      # Inertia controllers (si aplica).
│   └── Api/                           # JSON controllers consumidos por Vue vía fetch.
├── Requests/                          # FormRequests si es útil.
├── Events/                            # Eventos de dominio (Dispatchable).
├── Listeners/                         # Listeners propios; los que reaccionan a otros módulos también van aquí.
├── Policies/
├── Providers/                         # Providers INTERNOS adicionales del módulo.
├── Console/                           # Comandos artisan.
├── Mcp/
│   └── Tools/                         # Tools expuestos vía MCP al exterior.
├── Database/
│   ├── Migrations/                    # Auto-cargadas por el base ServiceProvider.
│   └── Factories/
├── Tests/
│   ├── Feature/
│   └── Unit/
├── lang/                              # Auto-cargado con namespace = slug del módulo.
└── resources/                         # Vistas/emails específicas si fueran necesarias.
```

## Principio de desacoplamiento

Un módulo **JAMÁS** importa internals de otro módulo. Las comunicaciones cruzadas usan:

1. **Eventos de dominio** publicados por el módulo emisor y escuchados en listeners del receptor.
2. **Registries** definidos en `app/Core/Registries/` (plug-in pattern).
3. **Contratos** en `app/Core/Contracts/` implementados por el módulo y bindeados en su ServiceProvider.

Importar tipos de `App\Core\*` está permitido (y esperado).

Excepción tolerada: para foreign keys y tipos de relación Eloquent en columnas pertenecientes al módulo (`belongsTo`, etc.), se permite importar el modelo de otro módulo. Pero la **lógica de negocio** debe seguir pasando por contratos.

## Crear un nuevo módulo

Pasos mínimos para añadir, por ejemplo, `Views` (repo-style saved filters):

1. `app/Modules/Views/ViewsServiceProvider.php` extendiendo `App\Core\Support\ModuleServiceProvider`, con `slug()` = `"views"`.
2. `app/Modules/Views/ViewsModuleManifest.php` implementando `App\Core\Contracts\ModuleManifest`; registrado en `ModuleRegistry` desde el `boot()` del ServiceProvider.
3. `app/Modules/Views/routes.php` con middlewares `['auth', 'workspace', 'module:views']`.
4. Migraciones en `app/Modules/Views/Database/Migrations/`.

Al arrancar, el módulo queda registrado automáticamente — no hay que editar `bootstrap/providers.php`.

## Módulos actuales

| Slug | Módulo | Mandatorio | Depende de |
|---|---|---|---|
| `workspaces` | Workspaces | ✓ | — |
| `teams` | Teams | ✓ | workspaces |
| `issues` | Issues | ✓ | workspaces, teams |
| `projects` | Projects | — | workspaces, teams |
| `cycles` | Cycles | — | workspaces, teams, issues |
| `integrations` | Integrations | — | workspaces, teams, issues |
| `mcp` *(planned)* | MCP server + OAuth | — | workspaces, teams, issues |

## Workspace scoping

Todo modelo perteneciente a una workspace usa el trait `App\Core\Concerns\BelongsToWorkspace`. Esto:

- añade un `WorkspaceScope` global que filtra todas las queries por la workspace activa (resuelta por el middleware `workspace`),
- rellena automáticamente `workspace_id` al crear el modelo,
- añade la relación `workspace()`.

En contextos sin workspace (CLI, jobs landlord), el scope es no-op.
