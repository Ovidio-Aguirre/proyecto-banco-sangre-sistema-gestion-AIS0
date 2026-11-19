# Sistema de Gestión de Banco de Sangre

API REST en PHP y MySQL para gestionar donantes, evaluaciones, donaciones, inventario por tipo de sangre, hospitales, pacientes y solicitudes.

## Arquitectura

```
Cliente → api/index.php (router) → Controller → Model → MySQL
```

- `api/index.php`: router único, autoload de clases y manejo global de errores.
- `src/Modulo<Nombre>/Controllers`: validación de entrada y respuestas HTTP.
- `src/Modulo<Nombre>/Models`: lógica de negocio y consultas (mysqli con prepared statements).
- `src/Modulo<Nombre>/Views`: vistas del módulo.
- `src/Shared`: `helpers` (`respuesta`, `validarId`, `leerJson`...), `contracts` (interfaz `Controlador`) y `Views` compartidas.
- `app/config/database.php`: conexión a la base de datos (lee `.env`).
- `database/schema.sql`: estructura de la base de datos.
- `database/seeders/`: seeders PHP (punto de entrada: `DatabaseSeeder.php`).

## Prerrequisitos

| Software | Versión |
|---|---|
| XAMPP (recomendado) | 8.2.x |
| PHP | 8.2 o superior, con extensión `mysqli` |
| MySQL / MariaDB | MariaDB 10.4+ (incluida en XAMPP) o MySQL 8.0+ |
| Apache | 2.4 con `mod_rewrite` |
| Git | 2.30+ |

## Instalación y ejecución local

1. Clonar el repositorio dentro de `C:\xampp\htdocs`:
   ```bash
   git clone https://github.com/Ovidio-Aguirre/proyecto-banco-sangre-sistema-gestion-AIS0.git proyecto
   ```
2. Crear el archivo de entorno a partir del ejemplo:
   ```bash
   cp .env.example .env
   ```
   En PowerShell: `Copy-Item .env.example .env`. Ajusta los valores si tu MySQL tiene otras credenciales.
3. Iniciar Apache y MySQL desde el panel de XAMPP.
4. Crear la base de datos y cargar los datos (sección siguiente).
5. Probar la API:
   ```bash
   curl -i http://localhost/nombre_proyecto/api/inventario
   ```

## Variables de entorno

Plantilla en `.env.example`:

| Variable | Descripción | Valor por defecto |
|---|---|---|
| `DB_HOST` | Servidor MySQL | `localhost` |
| `DB_PORT` | Puerto MySQL | `3306` |
| `DB_NAME` | Nombre de la base de datos | `banco_sangre` |
| `DB_USER` | Usuario MySQL | `root` |
| `DB_PASS` | Contraseña MySQL | *(vacía en XAMPP)* |

`.env` no se sube al repositorio; cada integrante crea el suyo.

## Base de datos y datos de prueba

1. Crear la base de datos y las tablas:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   PowerShell: `Get-Content database/schema.sql | mysql -u root -p`
   También puede importarse desde phpMyAdmin (pestaña *Importar*).
2. Cargar los datos iniciales (una sola transacción; si un seeder falla, se revierte todo):
   ```bash
   php database/seeders/DatabaseSeeder.php
   ```
   XAMPP en Windows: `C:\xampp\php\php.exe database\seeders\DatabaseSeeder.php`

Orden de carga: roles, tipos de sangre, departamentos, municipios, distritos, hospitales, usuarios, donantes, pacientes, evaluaciones, solicitudes y donaciones.

Nota: las evaluaciones de prueba dependen de la fecha en que se ejecuta el seeder. Una donación solo puede registrarse con una evaluación APTO del mismo día; si pasan días, registra una evaluación nueva.

### Roles

Administrador, Médico, Recepcionista, Auditor.

### Usuarios de prueba

| Usuario | Contraseña | Rol | Correo |
|---|---|---|---|
| `admin` | `Admin123*` | Administrador | admin@bancosangre.test |
| `medico` | `Medico123*` | Médico | medico@bancosangre.test |

Solo para desarrollo; no usar estas credenciales fuera de un entorno local.

## Endpoints

Base: `http://localhost/nombre_proyecto/api`

| Recurso | Rutas |
|---|---|
| Donantes | `GET/POST /donantes`, `GET/PUT/PATCH /donantes/{id}` |
| Evaluaciones | `/donantes/{id}/evaluaciones` |
| Donaciones | `GET/POST /donaciones`, `GET/PUT /donaciones/{id}`, `PATCH /donaciones/{id}/reasignar`, `PATCH /donaciones/{id}/solicitud`, `GET /donantes/{id}/donaciones` |
| Inventario | `GET /inventario`, `GET /inventario/{idTipoSangre}` |
| Hospitales | `GET/POST /hospitales`, `GET/PUT/DELETE /hospitales/{id}` |
| Pacientes | `GET/POST /pacientes`, `GET/PUT /pacientes/{id}` |
| Solicitudes | `GET/POST /solicitudes`, `GET/PUT/PATCH /solicitudes/{id}` (PATCH: entregar solicitud) |

Formato de respuesta: `{ "success": bool, "mensaje": "...", ... }`.
Códigos usados: 200, 201, 400, 404, 405, 409, 500.

## Estados de una donación

```
EN_EVALUACION → DISPONIBLE | DESCARTADA
DISPONIBLE ⇄ RESERVADA             (automático al vincular/desvincular solicitud)
DISPONIBLE | RESERVADA → VENCIDA   (automático al pasar fecha_caducidad)
RESERVADA → UTILIZADA
VENCIDA → DESCARTADA
```

## Estado del proyecto

| Funcionalidad | Estado |
|---|---|
| Donantes, evaluaciones, donaciones, inventario | Implementado |
| Hospitales, pacientes, solicitudes | Implementado |
| Seeders y esquema SQL | Implementado |
| Autenticación (login, sesiones o tokens) | Pendiente |
| Control de acceso por roles | Pendiente (roles y usuarios ya existen en BD) |
| Comunicación en tiempo real (WebSockets) | Pendiente |
| Módulo de soporte | Pendiente |

## Estrategia de ramas

- `main`: versión estable; solo recibe Pull Requests desde `develop`.
- `develop`: integración del trabajo del equipo.
- `feature/<nombre>`: una rama por funcionalidad (ej. `feature/api-donaciones`, `feature/database`).

Flujo: crear `feature/*` desde `develop`, hacer commits pequeños y frecuentes, abrir Pull Request a `develop` revisado por otro integrante, y hacer merge en cada entrega.

Convención de commits: `tipo: descripción` (`feat`, `fix`).

## Equipo

| Integrante | Entregable |
|---|---|
| Oscar Aguirre | Pruebas API |
| Rodrigo Amaya | API: Modulos Hospitales, Solicitud y Pacientes |
| Gabriela Barrientos | API: Modulos Donantes y Donaciones |
| Alfredo Reyes | Database y seeders |
| Pamela Orantes | Pantallas Frontend |
| Yuridia Argueta | Pantallas Frontend |
