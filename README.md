# Sistema de Gestión de Banco de Sangre

API REST en PHP y MySQL, con interfaz web, para gestionar donantes, evaluaciones, donaciones, inventario por tipo de sangre, hospitales, pacientes y solicitudes.

## Arquitectura

```
Navegador → index.php (vistas) → public/js → api/index.php (router) → Controller → Model → MySQL
```

- `index.php`: vistas de la interfaz, con control de sesión y de rol.
- `api/index.php`: router único, autoload de clases y manejo global de errores. Exige sesión en todo excepto `POST /auth/login`.
- `src/Modulo<Nombre>/Controllers`: validación de entrada y respuestas HTTP.
- `src/Modulo<Nombre>/Models`: lógica de negocio y consultas (mysqli con prepared statements).
- `src/Modulo<Nombre>/Views`: vistas del módulo.
- `src/Shared`: `helpers` (`respuesta`, `validarId`, `leerJson`, `registrarActividad`...), `contracts` (interfaz `Controlador`) y `Views` compartidas.
- `public/`: CSS y JavaScript de la interfaz.
- `app/config/database.php`: conexión a la base de datos (lee `.env`).
- `database/schema.sql`: estructura de la base de datos.
- `database/seeders/`: seeders PHP (punto de entrada: `DatabaseSeeder.php`).

## Prerrequisitos

| Software | Versión |
|---|---|
| PHP | 8.2 o superior, con extensión `mysqli` habilitada |
| MySQL / MariaDB | MariaDB 10.4+ o MySQL 8.0+ |
| Git | 2.30+ |
| Node.js | 18+ (solo para ejecutar las pruebas) |
| XAMPP 8.2.x | Opcional: incluye PHP, MariaDB y Apache (con `mod_rewrite`) |

Hay dos formas de ejecutar el proyecto:

- **Con XAMPP:** Apache sirve el proyecto en `http://localhost/proyecto`. El `.htaccess` redirige a `index.php` las rutas que no son archivos, por eso requiere `mod_rewrite`.
- **Sin XAMPP:** se usa el servidor integrado de PHP en `http://localhost:8080`. No usa `.htaccess` y no necesita configuración adicional. Requiere PHP y MySQL/MariaDB instalados por separado.

Las tablas deben usar el motor **InnoDB**: la asignación de unidades y la entrega de solicitudes usan transacciones.

## Instalación y ejecución local

### Pasos comunes

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/Ovidio-Aguirre/proyecto-banco-sangre-sistema-gestion-AIS0.git proyecto
   ```
   Con XAMPP, clónalo dentro de `C:\xampp\htdocs`.
2. Crear el archivo de entorno a partir del ejemplo:
   ```bash
   cp .env.example .env
   ```
   En PowerShell: `Copy-Item .env.example .env`. Ajusta los valores si tu MySQL tiene otras credenciales.
3. Tener MySQL/MariaDB en ejecución y cargar la base de datos y los datos de prueba (sección *Base de datos y datos de prueba*).

### Opción A: con XAMPP

1. Iniciar Apache y MySQL desde el panel de XAMPP.
2. Abrir `http://localhost/proyecto/index.php`.
3. URL base de la API: `http://localhost/proyecto/api`.

### Opción B: sin XAMPP (servidor integrado de PHP)

1. Verificar que PHP tiene `mysqli`:
   ```bash
   php -m | grep mysqli
   ```
   En PowerShell: `php -m | findstr mysqli`. Si no aparece, habilita `extension=mysqli` en `php.ini`.
2. Desde la carpeta raíz del proyecto, iniciar el servidor:
   ```bash
   php -S localhost:8080 -t .
   ```
3. Abrir `http://localhost:8080/index.php`.
4. URL base de la API: `http://localhost:8080/api`.

Para usar otro puerto, cámbialo en el comando y en el environment de pruebas.

### Verificar la API

La API exige sesión, por eso primero se inicia sesión y se guarda la cookie. Cambia `BASE` según tu opción (`http://localhost/proyecto` o `http://localhost:8080`):

```bash
curl -i -c cookies.txt -X POST BASE/api/auth/login -H "Content-Type: application/json" -d '{"usuario":"admin","contrasena":"Admin123*"}'
curl -i -b cookies.txt BASE/api/inventario
```

Sin sesión, `GET BASE/api/auth/perfil` responde `401` en JSON.

En PowerShell usa `curl.exe` (no el alias `curl`) y escapa las comillas del JSON, o usa Postman.

El Dashboard carga Chart.js desde un CDN, por lo que necesita conexión a internet.

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

1. Crear la base de datos y las tablas. El nombre debe coincidir con `DB_NAME` de tu `.env`:
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS banco_sangre CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
   mysql -u root -p banco_sangre < database/schema.sql
   ```
   PowerShell: `Get-Content database/schema.sql | mysql -u root -p banco_sangre`
   Desde phpMyAdmin: crear la base `banco_sangre`, seleccionarla y usar la pestaña *Importar*.

   `schema.sql` solo se ejecuta sobre una base vacía. Para empezar de cero: `DROP DATABASE banco_sangre;` y repetir este paso.

2. Cargar los datos iniciales (una sola transacción; si un seeder falla, se revierte todo y el comando termina con código 1):
   ```bash
   php database/seeders/DatabaseSeeder.php
   ```
   XAMPP en Windows, si `php` no está en el PATH: `C:\xampp\php\php.exe database\seeders\DatabaseSeeder.php`

Orden de carga: roles, tipos de sangre, departamentos, municipios, distritos, hospitales, usuarios, actividades, donantes, pacientes, evaluaciones, solicitudes y donaciones.

Notas sobre los datos:

- Registrar una donación exige una evaluación `APTO` **del mismo día**. Si no hay una, registra una evaluación nueva desde la interfaz.
- Las donaciones caducan 42 días después de la recolección. Al consultar, las `DISPONIBLE` o `RESERVADA` con fecha vencida pasan a `VENCIDA` automáticamente.
- Las solicitudes y los pacientes son de **entrada externa**: la interfaz no tiene pantalla para crearlos. Se crean con `POST /pacientes` y `POST /solicitudes`, o con el seeder.
- Las fechas de los datos de prueba son **fijas**, no relativas a la fecha de ejecución. Las donaciones sembradas caducan entre el 20 de noviembre y el 28 de diciembre de 2026, y con el tiempo pasan a `VENCIDA`.
- Todos los seeders pueden ejecutarse varias veces: actualizan u omiten los registros existentes en lugar de duplicarlos. Al repetirlos, las donaciones sembradas vuelven a `DISPONIBLE` sin solicitud, las solicitudes sembradas a su estado inicial, y los usuarios `admin` y `medico` recuperan su contraseña de prueba y quedan activos.
- La interfaz asume los ids de `tipo_sangre` que crea el seeder (1 = A+, 2 = A-, 3 = B+, 4 = B-, 5 = AB+, 6 = AB-, 7 = O+, 8 = O-). Carga los seeders sobre una base vacía.

### Roles

Administrador y Médico.

### Usuarios de prueba

| Usuario | Contraseña | Rol | Correo |
|---|---|---|---|
| `admin` | `Admin123*` | Administrador | admin@bancosangre.test |
| `medico` | `Medico123*` | Médico | medico@bancosangre.test |

Solo para desarrollo; no usar estas credenciales fuera de un entorno local.

## Endpoints

Base: `http://localhost/proyecto/api` (XAMPP) o `http://localhost:8080/api` (servidor integrado). Todas las rutas exigen sesión, salvo `POST /auth/login`.

| Recurso | Rutas |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/perfil`, `POST /auth/cambiar-contrasena` |
| Usuarios (solo administrador) | `GET/POST /usuarios`, `PATCH /usuarios/{id}` |
| Roles (solo administrador) | `GET /roles` |
| Donantes | `GET/POST /donantes`, `GET/PUT/PATCH /donantes/{id}` |
| Evaluaciones | `GET/POST /donantes/{id}/evaluaciones` (`?historial=1` para el historial) |
| Donaciones | `GET/POST /donaciones`, `GET /donaciones/elegibles`, `GET/PUT /donaciones/{id}`, `PATCH /donaciones/{id}/reasignar`, `PATCH /donaciones/{id}/solicitud`, `GET /donantes/{id}/donaciones` |
| Inventario | `GET /inventario`, `GET /inventario/unidades`, `GET /inventario/{idTipoSangre}` |
| Hospitales | `GET/POST /hospitales`, `GET/PUT/DELETE /hospitales/{id}` (DELETE es un borrado lógico) |
| Pacientes | `GET/POST /pacientes`, `GET /pacientes/{id}` |
| Solicitudes | `GET/POST /solicitudes`, `GET/PUT/PATCH /solicitudes/{id}`, `GET /solicitudes/{id}/unidades-compatibles`, `POST /solicitudes/{id}/unidades` |
| Reportes | `GET /reportes/resumen`, `GET /reportes/donaciones-mensuales`, `GET /reportes/actividad` |

Detalles de comportamiento:

- `PUT /solicitudes/{id}` solo aprueba o rechaza una solicitud `PENDIENTE`. Body: `{ "estado": "APROBADA" | "RECHAZADA", "motivoRechazo": "..." }` (el motivo es obligatorio al rechazar).
- `PATCH /solicitudes/{id}` entrega una solicitud `APROBADA`. Exige que todas las unidades solicitadas estén asignadas.
- `POST /solicitudes/{id}/unidades` asigna unidades. Body: `{ "ids": [1, 2] }`. Solo acepta unidades `DISPONIBLE`, vigentes y de tipo compatible (ABO/Rh), sin superar las unidades solicitadas. Es todo o nada.
- `DELETE /hospitales/{id}` responde 409 si el hospital tiene solicitudes `PENDIENTE` o `APROBADA`. `POST /hospitales` con el nombre y distrito de un hospital desactivado lo reactiva.
- `GET /reportes/actividad?limite=5` devuelve las últimas acciones (cualquier usuario). Con `?pagina=1&por_pagina=20&busqueda=&desde=&hasta=` devuelve el registro completo paginado (solo administrador).

Formato de respuesta: `{ "success": bool, "mensaje": "...", ... }`.
Códigos usados: 200, 201, 400, 401 (sin sesión), 403 (sin permiso), 404, 405, 409, 500.

## Flujo de una solicitud

1. Llega una solicitud externa (`PENDIENTE`).
2. Un usuario la aprueba o la rechaza (con motivo).
3. Desde la solicitud `APROBADA` se asignan unidades compatibles, hasta completar las solicitadas.
4. Al entregar, la solicitud pasa a `ENTREGADA` y sus unidades a `UTILIZADA`.

## Estados de una donación

```
EN_EVALUACION → DISPONIBLE | DESCARTADA
DISPONIBLE → RESERVADA              (al asignar la unidad a una solicitud APROBADA)
RESERVADA → DISPONIBLE              (al desvincular la unidad)
RESERVADA → UTILIZADA               (al entregar la solicitud, con todas sus unidades)
DISPONIBLE | RESERVADA → VENCIDA    (automático al pasar fecha_caducidad)
VENCIDA → DESCARTADA
```

## Registro de actividad

Las operaciones de escritura (aprobar, rechazar, entregar, asignar, y altas o cambios de donantes, donaciones, evaluaciones, hospitales y usuarios) se guardan en `registro_actividad`. El Dashboard muestra las últimas 5 acciones y el administrador consulta el registro completo en `index.php?page=actividad`.

## Pruebas

La suite es una colección de Postman que se ejecuta desde la terminal con Newman. Archivos en `pruebas/postman/`:

- `BancoSangre_postman_collection.json`
- `BancoSangre_environment_xampp.json` (`base_url = http://localhost/proyecto`)
- `BancoSangre_environment_puerto.json` (`base_url = http://localhost:8080`)

Requisitos:

1. La aplicación en ejecución (Opción A o B) y la base de datos con los seeders cargados.
2. Node.js 18+.

Instalación y ejecución. Elige el environment según cómo ejecutes el proyecto:

```bash
npm install -g newman newman-reporter-htmlextra

# Con XAMPP
newman run tests/postman/BancoSangre_postman_collection.json -e tests/postman/BancoSangre_environment_xampp.json -r cli,htmlextra --reporter-htmlextra-export docs/evidencia/reporte.html

# Sin XAMPP (puerto 8080)
newman run tests/postman/BancoSangre_postman_collection.json -e tests/postman/BancoSangre_environment_puerto.json -r cli,htmlextra --reporter-htmlextra-export docs/evidencia/reporte.html
```

La colección debe ejecutarse completa y en orden. También puede ejecutarse desde Postman: *Import* de la colección y del environment, elegir el environment y *Run collection*.

### Evidencia

Tras ejecutar la suite, guardar en `pruebas/evidencia/`:

- La captura de la terminal con el resultado de Newman (`newman-terminal.png`).
- El reporte `reporte.html` generado por el comando anterior.

Resultado esperado: `failed: 0` en las tablas *iterations*, *requests*, *test-scripts* y *assertions*.

## Estado del proyecto

| Funcionalidad | Estado |
|---|---|
| Donantes, evaluaciones, donaciones, inventario | Implementado |
| Hospitales, pacientes, solicitudes | Implementado |
| Asignación de unidades desde Solicitudes (cupo y compatibilidad) | Implementado |
| Dashboard y registro de actividad | Implementado |
| Seeders y esquema SQL | Implementado |
| Autenticación (login y sesiones) | Implementado |
| Control de acceso por roles | Implementado |
| Pruebas de API (Postman/Newman) | Implementado |

## Estrategia de ramas

- `main`: versión estable; solo recibe Pull Requests desde `develop`.
- `develop`: integración del trabajo del equipo.
- `feature/<nombre>`: una rama por funcionalidad (ej. `feature/api-donaciones`, `feature/database`).
- `fix/<nombre>`: una rama por funcionalidad corregida (ej. `fix/api-donaciones`, `fix/database`).

Flujo: crear `feature/*` (nueva funcionalidad) o `fix/*` (corrección) desde `develop`, hacer commits pequeños y frecuentes, abrir Pull Request a `develop` y hacer merge solo después de la revisión de otro integrante. Este flujo se aplica en cada entrega.

Convención de commits: `tipo: descripción` (`feat`, `fix`).

## Equipo

| Integrante | Entregable |
|---|---|
| Oscar Aguirre | Integracion y pruebas API |
| Rodrigo Amaya | API: Modulos Hospitales, Solicitud y Pacientes |
| Gabriela Barrientos | API: Modulos Donantes, Evaluacion, Donaciones y Usuarios |
| Alfredo Reyes | Database y seeders |
| Pamela Orantes | Pantallas Frontend |
| Yuridia Argueta | Pantallas Frontend |