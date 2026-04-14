# Módulo Transaction

El módulo `Transaction` gestiona las transacciones financieras de los usuarios. Expone una API REST para operaciones CRUD, importación y exportación, y un servidor MCP para el agente IA. Todos los endpoints requieren autenticación via JWT interno (guard `jwt-gateway`).

---

## Índice

- [Estructura del módulo](#estructura-del-módulo)
- [Modelos y base de datos](#modelos-y-base-de-datos)
  - [Transaction](#transaction)
  - [File](#file)
- [API REST](#api-rest)
  - [Rutas](#rutas)
  - [Controlador](#controlador)
  - [DTOs](#dtos)
- [Actions](#actions)
  - [GetTransactionsAction](#gettransactionsaction)
  - [AddTransactionAction](#addtransactionaction)
  - [UpdateTransactionAction](#updatetransactionaction)
  - [DeleteTransactionAction](#deletetransactionaction)
  - [ExportTransactionAction](#exporttransactionaction)
  - [ImportTransactionAction](#importtransactionaction)
- [Export e Import](#export-e-import)
  - [Formatos soportados](#formatos-soportados)
  - [Flujo de exportación](#flujo-de-exportación)
  - [Flujo de importación](#flujo-de-importación)
- [Aislamiento de datos por usuario](#aislamiento-de-datos-por-usuario)
- [Tipos de transacción](#tipos-de-transacción)
- [Tipos de archivo](#tipos-de-archivo)

---

## Estructura del módulo

```
Modules/Transaction/
├── app/
│   ├── Actions/
│   │   ├── AddTransactionAction.php
│   │   ├── DeleteTransactionAction.php
│   │   ├── ExportTransactionAction.php
│   │   ├── GetTransactionsAction.php
│   │   ├── ImportTransactionAction.php
│   │   └── UpdateTransactionAction.php
│   ├── Builders/
│   │   └── TransactionPathBuilder.php      # Construye rutas MinIO para transacciones
│   ├── Enums/
│   │   ├── FileType.php                    # GENERATED, EXPORT, IMPORT
│   │   ├── TransactionErrorCode.php        # Códigos de error tipados
│   │   ├── TransactionSuccessCode.php      # Códigos de éxito tipados
│   │   └── TransactionType.php             # income, expense
│   ├── Exports/
│   │   ├── Sheets/TransactionExportSheet.php
│   │   └── TransactionExporter.php
│   ├── Http/
│   │   ├── Controllers/TransactionController.php
│   │   ├── Data/
│   │   │   ├── AddOrUpdateTransactionData.php
│   │   │   ├── Request/GetTransactions*RequestData.php  (4 DTOs)
│   │   │   ├── Response/GetTransaction*Data.php         (3 DTOs)
│   │   │   └── TransactionData.php
│   │   └── Requests/
│   │       ├── ExportTransactionRequest.php
│   │       └── ImportTransactionRequest.php
│   ├── Imports/
│   │   ├── Sheets/TransactionImportSheet.php
│   │   └── TransactionImporter.php
│   ├── Mcp/
│   │   ├── Resources/
│   │   │   ├── ExcelDocumentSkillResource.php
│   │   │   └── PdfDocumentSkillResource.php
│   │   ├── Servers/AiAssistantServer.php
│   │   └── Tools/                          # 6 tools MCP
│   ├── Models/
│   │   ├── File.php
│   │   └── Transaction.php
│   └── Rules/
│       └── TransactionTypeRule.php
├── database/
│   ├── migrations/
│   │   ├── 2026_04_02_211728_create_transactions_table.php
│   │   └── 2026_04_10_174621_create_files_table.php
│   └── seeders/TransactionDatabaseSeeder.php
└── routes/
    ├── ai.php      # Rutas del servidor MCP
    └── api.php     # Rutas REST protegidas por jwt-gateway
```

---

## Modelos y base de datos

### Transaction

**Archivo:** `app/Models/Transaction.php`
**Tabla:** `transactions`

```
transactions
├── id          bigint PK auto_increment
├── user_email  string    — email del propietario (no FK hacia tabla users)
├── name        string    — nombre descriptivo de la transacción
├── amount      float     — monto (positivo, el tipo determina ingreso/egreso)
├── description text      — descripción detallada
├── type        enum      — 'income' | 'expense'
├── created_at  timestamp
└── updated_at  timestamp
```

**Scope `forUser`:**

```php
public function scopeForUser(Builder $query, GatewayUser $user): void
{
    $query->select(['name', 'amount', 'description', 'type', 'created_at', 'updated_at'])
          ->where('user_email', $user->email);
}
```

El scope selecciona solo los campos relevantes para el cliente (excluye `id` y `user_email`), y filtra por `user_email`. Todas las queries en `GetTransactionsAction` usan este scope como punto de partida.

**Cast de `type`:**
```php
protected function casts(): array
{
    return ['type' => TransactionType::class];
}
```

El campo `type` se castea automáticamente al enum `TransactionType` (`income`/`expense`).

---

### File

**Archivo:** `app/Models/File.php`
**Tabla:** `files`

```
files
├── id          bigint PK auto_increment
├── user_email  string    — email del propietario
├── name        string    — nombre del archivo (original para imports, generado para el resto)
├── path        string    — ruta en MinIO (relativa al bucket)
├── type        enum      — 'generated' | 'export' | 'import'
├── created_at  timestamp
└── updated_at  timestamp
```

Registra todos los archivos que el sistema genera o procesa para cada usuario:
- `GENERATED` — documentos creados por el sandbox Python (PDF, Excel, CSV via IA)
- `EXPORT` — archivos de exportación generados por `ExportTransactionAction`
- `IMPORT` — archivos importados por `ImportTransactionAction`

---

## API REST

### Rutas

**Archivo:** `routes/api.php`

Todas las rutas tienen prefijo `/api/v1` y requieren `auth:jwt-gateway`.

| Método | Ruta | Acción | Descripción |
|---|---|---|---|
| `GET` | `/v1/transactions` | `index` | Lista paginada de transacciones |
| `POST` | `/v1/transactions` | `store` | Crear transacción |
| `GET` | `/v1/transactions/{id}` | `show` | Ver una transacción |
| `PUT` | `/v1/transactions/{id}` | `update` | Actualizar transacción |
| `DELETE` | `/v1/transactions/{id}` | `destroy` | Eliminar transacción |
| `GET` | `/v1/transactions/export` | `export` | Exportar a Excel/CSV |
| `POST` | `/v1/transactions/import` | `import` | Importar desde Excel/CSV |

### Controlador

**Archivo:** `app/Http/Controllers/TransactionController.php`

El controlador es un delegador puro. Inyecta las 6 acciones correspondientes e invoca la correcta según el endpoint. El usuario autenticado (`GatewayUser`) se extrae de `$request->user()` y se pasa a cada acción.

### DTOs

| DTO | Uso |
|---|---|
| `AddOrUpdateTransactionData` | Body de `store` y `update` |
| `GetTransactionsByMcpRequestData` | Parámetros de paginación para `index` (vía MCP) |
| `GetTransactionsByPeriodRequestData` | Filtros `date_from`, `date_to` |
| `GetTransactionsByTypeRequestData` | Filtro `type` + paginación |
| `GetTransactionsByAmountRangeRequestData` | Filtros `amount_from`, `amount_to` |
| `GetTransactionResponseData` | Respuesta de una transacción individual |
| `GetTransactionsPaginatedData` | Respuesta paginada (data + metadata) |
| `GetTransactionsByConditionResponseData` | Respuesta de queries por condición (sin paginación) |
| `TransactionData` | DTO simple de transacción (usado en listas) |

---

## Actions

### GetTransactionsAction

**Archivo:** `app/Actions/GetTransactionsAction.php`

Centraliza todas las consultas de transacciones. Todas están filtradas por el `GatewayUser` via el scope `forUser`.

| Método | Descripción | Retorno |
|---|---|---|
| `getAll(user, perPage, page)` | Todas las transacciones paginadas | `GetTransactionsPaginatedData` |
| `getAllByMcp(data, user)` | Igual que `getAll` pero recibe el DTO MCP | `GetTransactionsPaginatedData` |
| `getById(id, user)` | Una transacción por ID | `GetTransactionResponseData[]` |
| `getByPeriod(data, user)` | Por rango de fechas | `GetTransactionsByConditionResponseData` |
| `getByAmountRange(data, user)` | Por rango de montos | `GetTransactionsByConditionResponseData` |
| `getByType(data, user)` | Por tipo con paginación | `GetTransactionsPaginatedData` |

**Estructura de respuesta paginada:**
```json
{
    "data": [...],
    "total": 150,
    "total_amount": 45230.50,
    "per_page": 15,
    "current_page": 1,
    "last_page": 10
}
```

**Estructura de respuesta por condición:**
```json
{
    "data": [...],
    "transaction_count": 23,
    "total_amount": 8750.00
}
```

### AddTransactionAction

```php
public function add(AddOrUpdateTransactionData $data, GatewayUser $user): void
{
    Transaction::create([
        'user_email' => $user->email,
        ...$data->toArray(),
    ]);
}
```

El email del usuario se inyecta automáticamente desde el JWT — el cliente no puede especificar un `user_email` diferente al suyo.

### UpdateTransactionAction

Busca la transacción filtrando por `id` Y `user_email` para garantizar que el usuario no pueda modificar transacciones de otro usuario:

```php
Transaction::where('id', $id)->where('user_email', $user->email)->update($data->toArray());
```

### DeleteTransactionAction

Mismo patrón que `UpdateTransactionAction`: filtra por `id` y `user_email`.

### ExportTransactionAction

**Archivo:** `app/Actions/ExportTransactionAction.php`

Genera un archivo de exportación, lo almacena en MinIO y retorna una descarga directa (`BinaryFileResponse`).

```
ExportTransactionAction::export(format, dateFrom, dateTo, user)
    │
    ├─ TransactionExportSheet(dateFrom, dateTo)   — query Eloquent filtrada
    ├─ format->resolveExportStrategy($sheet)       — ExcelExportStrategy o CsvExportStrategy
    ├─ TransactionExporter($strategy)              — envuelve la estrategia
    ├─ path = TransactionPathBuilder::buildForExport(filename, extension)
    ├─ File::create([user_email, name, path, type: EXPORT])   — registra el archivo
    ├─ $exporter->store($path, disk)               — sube a MinIO
    └─ $exporter->export($filename)               — retorna BinaryFileResponse
```

### ImportTransactionAction

**Archivo:** `app/Actions/ImportTransactionAction.php`

Importa transacciones desde un archivo Excel o CSV. El path en MinIO se construye desde el hash SHA-256 del contenido del archivo — nunca desde el nombre provisto por el cliente.

```
ImportTransactionAction::import(format, file, user)
    │
    ├─ TransactionImportSheet($user->email)        — inyecta email en cada fila
    ├─ format->resolveImportStrategy($sheet)
    ├─ TransactionImporter($strategy)
    ├─ path = TransactionPathBuilder::buildFromFile($file)  — hash SHA-256
    ├─ $importer->import($file)                    — procesa el archivo
    ├─ File::create([user_email, name, path, type: IMPORT])
    └─ CloudObjectStorage::store($path, $file)     — sube a MinIO
```

---

## Export e Import

### Formatos soportados

**Enum:** `Modules/Shared/app/Enums/ExportFormat.php`

| Value | Extension | Export Strategy | Import Strategy |
|---|---|---|---|
| `excel` | `.xlsx` | `ExcelExportStrategy` | `ExcelImportStrategy` |
| `csv` | `.csv` | `CsvExportStrategy` | `CsvImportStrategy` |

### Flujo de exportación

1. El cliente llama `GET /api/v1/transactions/export?format=xlsx&date_from=2026-01-01&date_to=2026-03-31`
2. `ExportTransactionRequest` valida los parámetros (formato, fechas)
3. `ExportTransactionAction` genera el archivo, lo registra en `files` y lo sube a MinIO
4. Se retorna el archivo como descarga directa (`BinaryFileResponse`)

El archivo también queda disponible en MinIO para descarga posterior, pero el endpoint solo retorna la descarga directa (no una URL pre-firmada).

### Flujo de importación

1. El cliente envía `POST /api/v1/transactions/import` con `multipart/form-data`:
   - `file`: el archivo Excel/CSV
   - `format`: `excel` o `csv`
2. `ImportTransactionRequest` valida el archivo (MIME type, tamaño)
3. `ImportTransactionAction` importa cada fila inyectando el `user_email` del JWT
4. El archivo original se almacena en MinIO como referencia

**Estructura esperada del archivo (columnas):**

| Columna | Tipo | Requerido |
|---|---|---|
| `name` | string | Sí |
| `amount` | float | Sí |
| `description` | text | Sí |
| `type` | `income`/`expense` | Sí |

---

## Aislamiento de datos por usuario

Todas las queries están filtradas por el email del `GatewayUser` extraído del JWT. Es imposible que un usuario vea o modifique datos de otro usuario:

1. **Scope `forUser`**: Todas las queries de `GetTransactionsAction` pasan por este scope
2. **`user_email` en create**: `AddTransactionAction` y `ImportTransactionAction` inyectan el email del JWT, no del body
3. **Doble filtro en mutations**: `update` y `delete` filtran por `id AND user_email`

```php
// Imposible modificar datos de otro usuario:
Transaction::where('id', $id)
           ->where('user_email', $user->email)  // ← garantía de aislamiento
           ->update($data->toArray());
```

---

## Tipos de transacción

**Enum:** `Modules/Transaction/app/Enums/TransactionType.php`

| Case | Value | Descripción |
|---|---|---|
| `INCOME` | `income` | Ingreso de dinero |
| `EXPENSE` | `expense` | Egreso de dinero |

---

## Tipos de archivo

**Enum:** `Modules/Transaction/app/Enums/FileType.php`

| Case | Value | Descripción |
|---|---|---|
| `GENERATED` | `generated` | Documento generado por el sandbox Python via IA |
| `EXPORT` | `export` | Archivo generado por exportación de transacciones |
| `IMPORT` | `import` | Archivo importado por el usuario |
