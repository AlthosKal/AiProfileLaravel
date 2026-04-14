# Sandbox Python

El sandbox es un contenedor Docker aislado que ejecuta scripts Python generados por el agente IA para producir documentos (PDF, Excel, CSV). Este documento describe la arquitectura del sandbox, el ciclo de vida de un job, las restricciones de seguridad y cómo extenderlo.

---

## Índice

- [Visión general](#visión-general)
- [Arquitectura de seguridad](#arquitectura-de-seguridad)
- [Ciclo de vida de un job](#ciclo-de-vida-de-un-job)
- [Contenedor sandbox](#contenedor-sandbox)
  - [Dockerfile](#dockerfile)
  - [Librerías disponibles](#librerías-disponibles)
  - [Restricciones](#restricciones)
- [Clases involucradas](#clases-involucradas)
  - [SandboxJobRunner](#sandboxjobrunner)
  - [SandboxJob (value object)](#sandboxjob-value-object)
  - [ExecuteSandboxJob (Laravel Job)](#executesandboxjob-laravel-job)
- [Estado del job en Redis](#estado-del-job-en-redis)
- [Almacenamiento en MinIO](#almacenamiento-en-minio)
- [Variables de entorno y constantes](#variables-de-entorno-y-constantes)
- [Flujo completo end-to-end](#flujo-completo-end-to-end)
- [Restricciones para scripts Python](#restricciones-para-scripts-python)
- [Extender el sandbox](#extender-el-sandbox)
  - [Agregar una librería Python](#agregar-una-librería-python)
  - [Cambiar el timeout](#cambiar-el-timeout)
  - [Agregar un nuevo formato de output](#agregar-un-nuevo-formato-de-output)

---

## Visión general

```
laravel-mcp (PHP)                  Docker
─────────────────────              ─────────────────────────────────────────
ExecuteSandboxJob                  mcp-sandbox-python (contenedor)
    │                              /sandbox/jobs/ (volumen compartido)
    ├─ SandboxJobRunner::run()
    │   ├─ crea /jobs/{jobId}/script.py    → escribe en volumen
    │   ├─ docker exec ... python script.py
    │   └─ lee /jobs/{jobId}/output/{file} ← lee del volumen
    │
    ├─ CloudObjectStorage::storeFromPath() → MinIO
    ├─ File::create(...)                   → PostgreSQL
    └─ Cache::put(...)                     → Redis (URL pre-firmada)
```

El PHP nunca ejecuta código Python directamente. Toda ejecución ocurre dentro del contenedor aislado via `docker exec`.

---

## Arquitectura de seguridad

El sandbox implementa múltiples capas de aislamiento:

| Restricción | Configuración | Propósito |
|---|---|---|
| Sin red | `network_mode: none` | El script Python no puede hacer llamadas HTTP salientes |
| Sistema de archivos read-only | `read_only: true` | El contenedor no puede modificar su propio sistema de archivos |
| tmpfs limitado | `/tmp: size=64m` | Espacio temporal estrictamente acotado |
| Usuario sin privilegios | `USER sandbox` (Dockerfile) | El script corre como usuario no-root |
| Timeout estricto | `timeout 60` (comando) | Un script colgado no puede bloquear el worker |
| Sin secretos | Variables de entorno solo `OUTPUT_DIR` | El script no tiene acceso a credenciales |
| Volumen de jobs | `mcp_sandbox_jobs` (named volume) | Los scripts y outputs van a un volumen dedicado |
| Sin acceso al host | Volumen solo en `/sandbox/jobs` | El script no puede leer el filesystem del host |
| Recursos limitados | `cpus: 1`, `memory: 512M` | No puede monopolizar recursos del host |

**El script Python recibe exactamente una variable de entorno:**
```python
import os
OUTPUT_DIR = os.environ["OUTPUT_DIR"]  # única variable disponible
```

Esto significa que el script no tiene acceso a credenciales de base de datos, claves API, ni ningún secreto del sistema.

---

## Ciclo de vida de un job

```
1. [MCP Tool] RequestDocumentGenerationTool::handle()
   ├─ jobId = Str::uuid()
   ├─ Cache::put("sandbox_job:{jobId}", {status: "pending"}, 600s)
   └─ ExecuteSandboxJob::dispatch(jobId, code, outputFileName, userEmail)

2. [Laravel Queue Worker] ExecuteSandboxJob::handle()
   ├─ updateStatus(Processing)
   ├─ SandboxJobRunner::run(data)
   │   ├─ Crea directorio: {SANDBOX_JOBS_PATH}/{jobId}/
   │   ├─ Crea directorio: {SANDBOX_JOBS_PATH}/{jobId}/output/
   │   ├─ Escribe: {SANDBOX_JOBS_PATH}/{jobId}/script.py ← código del agente
   │   ├─ Ejecuta:
   │   │   docker exec --user sandbox
   │   │              -e OUTPUT_DIR=/sandbox/jobs/{jobId}/output
   │   │              mcp-sandbox-python
   │   │              timeout 60 python /sandbox/jobs/{jobId}/script.py 2>&1
   │   └─ Lee: {SANDBOX_JOBS_PATH}/{jobId}/output/{outputFileName}
   │
   ├─ Si exitCode != 0 o no hay archivo output:
   │   └─ updateStatus(Failed, errorMessage: stdout)
   │
   └─ Si exitCode == 0 y archivo presente:
       ├─ storagePath = "sandbox/generated/{jobId}/{outputFileName}"
       ├─ CloudObjectStorage::storeFromPath(storagePath, localPath)  → MinIO
       ├─ File::create(user_email, name, path, type: GENERATED)      → PostgreSQL
       ├─ downloadUrl = CloudObjectStorage::temporaryUrl(storagePath, 10 min)
       └─ updateStatus(Completed, downloadUrl, fileName, expiredInMinutes: 10)

3. [MCP Tool] CheckDocumentStatusTool::handle()
   └─ Cache::get("sandbox_job:{jobId}") → retorna estado y URL si está listo
```

---

## Contenedor sandbox

### Dockerfile

**Archivo:** `docker/sandbox-python/Dockerfile`

```dockerfile
FROM python:3.12-slim

# Fuentes para reportlab (generación de PDFs con texto)
RUN apt-get update && apt-get install -y --no-install-recommends \
        fontconfig \
        fonts-liberation \
        fonts-crosextra-carlito \
        fonts-dejavu \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Librerías Python fijas (versiones pinadas para reproducibilidad)
RUN pip install --no-cache-dir \
    openpyxl==3.1.5 \
    reportlab==4.2.5 \
    matplotlib==3.9.4 \
    pandas==2.2.3 \
    Pillow==10.4.0

# Usuario sin privilegios
RUN useradd --no-create-home --shell /bin/false sandbox

WORKDIR /workspace
USER sandbox
```

### Librerías disponibles

| Librería | Versión | Uso típico |
|---|---|---|
| `reportlab` | 4.2.5 | Generación de PDFs (texto, tablas, gráficos vectoriales) |
| `openpyxl` | 3.1.5 | Generación y lectura de Excel (`.xlsx`) |
| `matplotlib` | 3.9.4 | Gráficos y plots (PNG incrustado en PDF o Excel) |
| `pandas` | 2.2.3 | Manipulación de datos antes del renderizado |
| `Pillow` | 10.4.0 | Manipulación de imágenes |

**Nota importante:** `matplotlib` debe inicializarse con el backend `Agg` en modo headless:
```python
import matplotlib
matplotlib.use('Agg')  # ← SIEMPRE incluir antes de import pyplot
import matplotlib.pyplot as plt
```

### Restricciones

- **Sin acceso a Internet** — `network_mode: none` en compose.yaml
- **Sin pip install en runtime** — las librerías están fijas en la imagen
- **Sin importaciones de red** — `urllib`, `requests`, `httpx`, `socket` fallarán (sin red)
- **Timeout de 60 segundos** — scripts que excedan este límite son terminados por `timeout`
- **Solo puede escribir en `OUTPUT_DIR`** — el sistema de archivos del contenedor es read-only salvo el volumen de jobs

---

## Clases involucradas

### SandboxJobRunner

**Archivo:** `Modules/Shared/app/Sandbox/SandboxJobRunner.php`

Responsable exclusivamente de ejecutar el script en el contenedor y retornar el resultado. No sabe nada de colas, Redis ni MinIO.

```php
readonly class SandboxJobRunner
{
    public function __construct(
        private string $hostJobsPath,  // inyectado desde config (SANDBOX_JOBS_PATH)
    ) {}

    public function run(ExecuteSandboxRequestData $data): SandboxJob
    {
        $jobId = uniqid('job_', more_entropy: true);
        // 1. Crear directorios en el volumen compartido
        // 2. Escribir el script Python
        // 3. Ejecutar: docker exec --user sandbox -e OUTPUT_DIR=... mcp-sandbox-python timeout 60 python script.py 2>&1
        // 4. Verificar si existe el archivo de output
        // 5. Retornar SandboxJob (value object con jobId, stdout, exitCode, outputPath)
    }
}
```

El comando `docker exec` usa:
- `--user sandbox` — corre como el usuario sin privilegios
- `-e OUTPUT_DIR={containerOutputDir}` — única variable de entorno del script
- `timeout 60` — mata el proceso tras 60 segundos
- `2>&1` — combina stderr con stdout para capturar todos los mensajes de error

### SandboxJob (value object)

**Archivo:** `Modules/Shared/app/Sandbox/SandboxJob.php`

Resultado inmutable de una ejecución del sandbox:

| Campo | Tipo | Descripción |
|---|---|---|
| `jobId` | string | ID único del job (prefijo de la ruta en MinIO) |
| `stdout` | string | Salida capturada del script (stdout + stderr) |
| `exitCode` | int | Código de salida del proceso (0 = éxito) |
| `outputPath` | string | Ruta absoluta al archivo generado, vacío si no hay output |

```php
$job->succeeded()   // exitCode === 0
$job->hasOutput()   // outputPath !== '' && file_exists(outputPath)
```

### ExecuteSandboxJob (Laravel Job)

**Archivo:** `Modules/Shared/app/Jobs/ExecuteSandboxJob.php`

Job de Laravel que orquesta el ciclo completo: ejecuta el sandbox, sube a MinIO, registra en DB y actualiza Redis.

**Configuración:**
- `$timeout = 90` segundos (debe superar el timeout del sandbox de 60s)
- `$tries = 1` — sin reintentos (scripts generados por IA no deben re-ejecutarse automáticamente)

**Clave Redis:** `sandbox_job:{jobId}` (TTL: 600 segundos)

---

## Estado del job en Redis

El estado de cada job se almacena en Redis con la clave `sandbox_job:{jobId}`:

```php
// Estado inicial (al despachar)
Cache::put("sandbox_job:{$jobId}", ['status' => 'pending'], 600);

// Estado en procesamiento
['status' => 'processing']

// Estado completado
[
    'status'           => 'completed',
    'error_type'       => 'no_error',
    'download_url'     => 'https://minio.../...',
    'file_name'        => 'report.pdf',
    'expired_in_minutes' => 10,
    'error_message'    => null,
]

// Estado fallido
[
    'status'        => 'failed',
    'error_type'    => 'execution_failed',
    'download_url'  => null,
    'file_name'     => null,
    'expired_in_minutes' => null,
    'error_message' => 'Traceback (most recent call last):...',
]
```

---

## Almacenamiento en MinIO

Los archivos generados se almacenan en MinIO bajo la ruta:

```
sandbox/generated/{jobId}/{outputFileName}
```

**Ejemplo:**
```
sandbox/generated/job_66a1b2c3d4/report.pdf
sandbox/generated/job_66a1b2c3d4e5/transactions.xlsx
```

El `jobId` viene del `uniqid()` generado en `SandboxJobRunner::run()`, que es diferente al UUID del job Laravel (`Str::uuid()`) generado en la tool.

Los archivos se almacenan como **privados** en MinIO. El acceso se da solo via URL pre-firmada con TTL de 10 minutos, generada por `CloudObjectStorage::temporaryUrl()`.

---

## Variables de entorno y constantes

| Clase | Constante/Variable | Valor | Descripción |
|---|---|---|---|
| `SandboxJobConstantsEnum` | `CONTAINER_NAME` | `mcp-sandbox-python` | Nombre del contenedor Docker |
| `SandboxJobConstantsEnum` | `CONTAINER_JOBS_PATH` | `/sandbox/jobs` | Ruta de jobs dentro del contenedor |
| `SandboxJobTimesEnum` | `TIMEOUT_SECONDS` | `60` | Timeout de ejecución Python |
| `ExecuteSandboxJob` | `RESULT_TTL_SECONDS` | `600` | TTL del resultado en Redis (10 min) |
| `.env` | `SANDBOX_JOBS_PATH` | ruta host | Ruta del volumen de jobs en el host |

---

## Flujo completo end-to-end

```
Usuario: "Genera un reporte PDF de mis gastos de enero"
    │
    ▼
Agente IA (laravel-api)
    ├─ GetTransactionsByPeriodTool(2026-01-01, 2026-01-31) → datos
    ├─ Lee skill://documents/pdf → instrucciones de reportlab
    ├─ RequestDocumentGenerationTool(code=<script Python>, output_file_name="gastos_enero.pdf")
    │   └─ retorna: {job_id: "uuid-123", status: "pending"}
    │
    ├─ CheckDocumentStatusTool(job_id="uuid-123") → {status: "processing"}
    ├─ CheckDocumentStatusTool(job_id="uuid-123") → {status: "processing"}
    └─ CheckDocumentStatusTool(job_id="uuid-123") → {status: "completed", download_url: "https://..."}

Agente al usuario: "Tu reporte está listo: [Descargar PDF](https://...)"
```

---

## Restricciones para scripts Python

Los scripts generados por el agente **deben** seguir estas reglas:

1. **Escribir el output en `OUTPUT_DIR`:**
   ```python
   import os
   output_path = os.path.join(os.environ["OUTPUT_DIR"], "nombre.pdf")
   ```

2. **El nombre del archivo debe coincidir** con `output_file_name` enviado a `RequestDocumentGenerationTool`

3. **No intentar acceder a la red** — cualquier llamada de red fallará silenciosamente o lanzará una excepción

4. **No usar `sys.exit()` con código distinto de 0** a menos que sea un error — el sandbox interpreta exit code != 0 como fallo

5. **Usar `matplotlib.use('Agg')`** antes de importar pyplot — no hay display en el contenedor

6. **No hardcodear datos de negocio** — siempre usar los datos obtenidos de las tools MCP

---

## Extender el sandbox

### Agregar una librería Python

1. Editar `docker/sandbox-python/Dockerfile` para agregar la librería:
   ```dockerfile
   RUN pip install --no-cache-dir \
       openpyxl==3.1.5 \
       reportlab==4.2.5 \
       matplotlib==3.9.4 \
       pandas==2.2.3 \
       Pillow==10.4.0 \
       seaborn==0.13.2    # ← nueva librería
   ```

2. Reconstruir la imagen:
   ```bash
   docker compose build mcp-sandbox-python
   docker compose up -d mcp-sandbox-python
   ```

3. Actualizar el resource MCP correspondiente (`PdfDocumentSkillResource` o `ExcelDocumentSkillResource`) para documentar la nueva librería disponible.

### Cambiar el timeout

Modificar `SandboxJobTimesEnum::TIMEOUT_SECONDS`:

```php
enum SandboxJobTimesEnum: int
{
    case TIMEOUT_SECONDS = 90;  // cambiar de 60 a 90
}
```

También ajustar `ExecuteSandboxJob::$timeout` para que sea mayor al nuevo timeout del sandbox:

```php
public int $timeout = 120;  // debe superar TIMEOUT_SECONDS
```

### Agregar un nuevo formato de output

1. Agregar el formato en `Modules/Shared/app/Enums/ExportFormat.php`
2. Crear el resource MCP con instrucciones para el nuevo formato
3. Registrar el resource en `AiAssistantServer::$resources`
