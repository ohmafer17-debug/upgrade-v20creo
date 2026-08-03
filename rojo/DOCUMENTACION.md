# DOCUMENTACIÓN TÉCNICA Y OPERATIVA DEL PROYECTO
## SISTEMA DE GESTIÓN Y CONTROL DE VIGENCIA DE EXPEDIENTES CORPORATIVOS - XONEXKA

Este documento detalla la arquitectura, estructura de datos, mecanismos de seguridad, automatización de alertas y guías de administración para el sistema de gestión de expedientes **Xonexka** (versión roja).

---

## 1. DESCRIPCIÓN GENERAL
El sistema es una plataforma web multi-inquilino (*multi-tenant*) diseñada para automatizar la indexación, auditoría y control de vigencia de expedientes obligatorios para corporativos, consultores y sucursales subordinadas. Su principal objetivo es prevenir multas o interrupciones operativas mediante alertas visuales (semáforos de estado) y notificaciones automatizadas multicanal (Email y WhatsApp) ante el vencimiento de licencias, dictámenes o permisos.

---

## 2. ARQUITECTURA Y TECNOLOGÍAS
La aplicación está construida sobre una arquitectura clásica monolítica de alto rendimiento, optimizada para entornos locales (como XAMPP) y servidores de producción Linux/Apache:

*   **Frontend:**
    *   **HTML5 & CSS3 Vanilla:** Diseño responsivo modular hecho a medida, sin librerías pesadas como TailwindCSS para garantizar máxima velocidad. Incluye un preloader dinámico premium, paneles colapsables optimizados para smartphones (diseño móvil-primero) e imágenes en alta resolución con filtros avanzados de suavizado.
    *   **JavaScript (ES6+):** Programación asíncrona pura para la interactividad de la interfaz. Manejo de formularios mediante `FormData` para subida de logotipos y expedientes pesados.
    *   **Leaflet.js con Mosaicos de Google Maps:** Integración de mapas interactivos que cargan directamente la cartografía oficial de Google Maps, permitiendo posicionar marcadores y obtener coordenadas precisas en tiempo real de forma gratuita (sin requerir claves de API pagas).
*   **Backend:**
    *   **PHP 8.x:** Lenguaje del lado del servidor que procesa las solicitudes a través de un modelo controlador de API REST nativo.
*   **Base de Datos:**
    *   **MySQL / MariaDB:** Motor relacional estructurado.
*   **Seguridad:**
    *   **Sesiones seguras nativas de PHP (`PHPSESSID`)** y hashes criptográficos robustos mediante **BCrypt** para contraseñas de usuarios.

---

## 3. ESTRUCTURA DE CARPETAS DEL PROYECTO
El proyecto cuenta con una organización limpia y modular:

```text
├── config/
│   └── conexion.php           # Configuración de credenciales de la base de datos MySQL
├── controllers/
│   ├── alertas_automaticas.php # Cron / Script de alertas automáticas (vencimientos)
│   ├── cliente_procesar.php   # API Backend para operaciones del portal de clientes
│   ├── documento_descargar.php # Controlador seguro para descarga de archivos indexados
│   ├── login_procesar.php     # Validador de credenciales y creador de sesiones PHP
│   ├── logout.php             # Script de destrucción segura de sesiones
│   └── ups_procesar.php       # API Backend para la consola del Administrador UPS
├── public/
│   ├── admin/                 # Consola del Administrador Global
│   │   ├── css/
│   │   │   ├── index.css      # Estilos generales del panel administrativo
│   │   │   └── login.css      # Interfaz de inicio de sesión
│   │   ├── js/
│   │   │   └── index.js       # Lógica y mapas de la vista de administrador
│   │   ├── index.html         # Maqueta HTML del Dashboard de administración
│   │   └── login.html         # Pantalla de login de administración
│   ├── cliente/               # Portal de Clientes, Consultores y Sucursales
│   │   ├── css/
│   │   │   ├── index.css
│   │   │   └── login.css      # Login alineado a la derecha con fondo clásico
│   │   ├── js/
│   │   │   ├── index.js       # Lógica, tablas y mapas de la vista cliente
│   │   │   └── login.js       # Conexión al inicio de sesión de cliente
│   │   ├── index.html         # Maqueta HTML del Dashboard corporativo
│   │   └── login.html         # Pantalla de login corporativo
│   └── uploads/
│       └── logos/             # Directorio de almacenamiento físico de logotipos subidos
└── uploads_dictamenes/        # Almacenamiento seguro de archivos indexados (bloqueado por .htaccess)
```

---

## 4. DISEÑO DE BASE DE DATOS
El sistema utiliza 4 tablas principales en el esquema relacional:

### A. Tabla: `empresas_clientes`
Representa a los usuarios del portal cliente y define la jerarquía organizacional.
*   `id` (INT, Primary Key, Auto-increment)
*   `cod` (VARCHAR): Código identificador de la organización (ej: `Roto123` para raíz, o `Roto123/T1A` para sucursales).
*   `nombre` (VARCHAR): Nombre comercial del corporativo o sucursal.
*   `encargado` (VARCHAR): Nombre del responsable operativo del nodo.
*   `email` (VARCHAR, Unique): Correo principal (usado como usuario para iniciar sesión).
*   `director_email` (VARCHAR): Correo del director de la sucursal (para envío de alertas críticas de 24 horas).
*   `email_adicional` (VARCHAR): Correo electrónico secundario de contacto.
*   `telefono_principal` (VARCHAR): Teléfono primario (usado también para alertas de WhatsApp).
*   `telefono_adicional` (VARCHAR): Teléfono secundario.
*   `direccion` (TEXT): Ubicación física.
*   `coordenadas` (VARCHAR): Ubicación GPS en formato `latitud, longitud`.
*   `logo` (VARCHAR): Nombre del archivo físico del logotipo subido.
*   `pass` (VARCHAR): Contraseña encriptada en BCrypt.
*   `rol` (VARCHAR): Rango organizacional (`Consultor`, `Responsable Nacional`, `Tipo 1`, `Tipo 2`, `Tipo 3`).
*   `activo` (TINYINT): Estatus de la licencia (`1` = Activa, `0` = Suspendida).

### B. Tabla: `documentos_pc`
Almacena la información de vigencia y semáforos de los expedientes subidos.
*   `id` (INT, Primary Key, Auto-increment)
*   `empresa_cod` (VARCHAR): Vincula el archivo al código de la empresa dueña.
*   `tipo_doc` (VARCHAR): Categoría del documento (ej: Dictamen Estructural).
*   `nombre_personalizado` (VARCHAR): Nombre del archivo físico en el servidor.
*   `fecha_vencimiento` (DATE): Fecha en que expira el documento.
*   `notificar_correos` (TEXT): Lista separada por comas de hasta 5 correos adicionales para recibir alertas extras.
*   `activo` (TINYINT): Estatus del expediente (`1` = Vigente, `0` = Suspendido / Inactivo).

### C. Tabla: `historial_documentos`
Lleva la trazabilidad histórica de versiones anteriores de archivos reemplazados.
*   `id` (INT, Primary Key, Auto-increment)
*   `documento_id` (INT): Relación con el documento padre en `documentos_pc`.
*   `nombre_archivo_fisico` (VARCHAR): Nombre del archivo anterior respaldado.
*   `fecha_registro` (TIMESTAMP): Fecha y hora del cambio de versión.
*   `empresa_cod` (VARCHAR): Empresa que realizó la actualización.

### D. Tabla: `admin_ups`
Almacena el personal interno encargado del aprovisionamiento y administración máster.
*   `id` (INT, Primary Key, Auto-increment)
*   `nombre` (VARCHAR)
*   `email` (VARCHAR, Unique)
*   `pass` (VARCHAR)
*   `rol` (VARCHAR): Rango administrativo (`Administrador`, `Usuario Estándar`, `Invitado`).
*   `estatus` (VARCHAR): Estado de la cuenta (`Activo` o `Suspendido`).

---

## 5. ESQUEMA DE SEGURIDAD DE ARCHIVOS Y SESIÓN
Para cumplir con los más estrictos estándares de confidencialidad de la información corporativa, se implementó un sistema de seguridad de triple candado:

1.  **Bloqueo de Acceso Directo por Servidor (`.htaccess`):**
    *   La carpeta `/uploads_dictamenes/` contiene un archivo `.htaccess` con la regla `Deny from all`. Esto bloquea por completo cualquier intento de acceder a un PDF o imagen escribiendo su URL directa en el navegador, respondiendo con un error **403 Forbidden**.
2.  **Validación de Sesión PHP en el Servidor:**
    *   Al iniciar sesión exitosamente (`login_procesar.php`), el servidor guarda una sesión nativa y asocia `$_SESSION['sesion_activa'] = true` y el `$_SESSION['usuario_cod']`.
    *   El script de descarga [documento_descargar.php](file:///C:/xampp/htdocs/upgrade_systems/controllers/documento_descargar.php) verifica que la cookie de sesión coincida. Si copias la URL de un documento e intentas abrirla en otro navegador (o modo incógnito) donde no hayas iniciado sesión, el servidor denegará el acceso inmediatamente.
3.  **Prevención de Descargas Cruzadas (Cross-Tenant):**
    *   Cuando un usuario solicita un archivo, el sistema busca en la base de datos la empresa propietaria de ese expediente.
    *   Si el usuario logueado en la sesión posee una cuenta de cliente y su código base (ej: `Porsche`) no coincide con el código base del documento solicitado (ej: `Pemex`), se le niega la descarga automáticamente. El acceso total y libre de auditoría solo le es permitido al staff máster (`UPS-STAFF`).

---

## 6. MOTOR DE ALERTAS AUTOMÁTICAS (CRON)
El archivo [alertas_automaticas.php](file:///C:/xampp/htdocs/upgrade_systems/controllers/alertas_automaticas.php) actúa como un motor de fondo que calcula la vigencia de cada documento comparando la fecha de vencimiento con el tiempo de vida total del archivo:

*   **Lógica de Escalamiento:**
    *   **Verde / Seguro:** Tiempo consumido menor al 80%.
    *   **Amarillo 1 (Próximo a Vencer):** Tiempo consumido entre el 80% y 89.99%. Se envía un correo de advertencia únicamente al correo principal de la sucursal.
    *   **Amarillo 2 / Rojo (Fases Críticas):** Tiempo consumido mayor o igual al 90%. El sistema envía las notificaciones por correo electrónico al correo principal y también a la lista de **Correos Extras** (hasta 5 correos adicionales guardados en `notificar_correos`).
    *   **Aviso de 24 Horas (Última Alerta):** Exactamente 1 día antes de vencer, el sistema envía un correo crítico con formato de párrafos directamente al contacto de la empresa y, en copia, al **Correo del Director** registrado en la base de datos.
    *   **Escalamiento a Staff Máster:** Si el documento vence y sigue sin actualizarse, la alerta escala automáticamente a los correos del personal de administración del sistema para su auditoría física.

---

## 7. FLUJOS DE USUARIO Y OPERATIVIDAD
El sistema se compone de dos interfaces independientes:

### A. Consola del Administrador (Staff)
*   **Registrar Consultores:** Permite dar de alta corporativos raíz. Requiere definir un Nombre de Organización, un **ID único** manual (que servirá como base jerárquica) y contraseña. El rango asignado es siempre `Consultor`.
*   **Registrar Sucursales:** Permite dar de alta empresas hijas. No solicita ID manual (se autogenera jerárquicamente a partir de la organización superior). Es obligatorio seleccionar un **Consultor Superior** de la lista para vincular la sucursal. El rango asignado es siempre `Tipo 1`.
*   **Auditoría de Licencias:** Tabla de visualización y filtrado rápido por empresa raíz para suspender o activar licencias, editar contraseñas, logotipos, encargados y direcciones. Al guardar cambios de nombre de una empresa, el selector superior se actualiza de manera inmediata.
*   **Administradores UPS:** Gestión de cuentas internas de staff de soporte.

### B. Portal Corporativo (Clientes)
*   **Inicio:** Vista general rápida de estadísticas de expedientes (vigentes, por vencer, vencidos).
*   **Control de Expedientes:** Permite al Consultor o Colaborador indexar nuevos archivos PDF/imágenes, registrar un Nombre de Versión y fecha de vencimiento, definir un **Correo de Alerta Principal** y agregar dinámicamente campos de texto para enviar alertas a correos adicionales (máximo 5).
*   **Asignación Directa a Sucursales:** Si el usuario logueado es un Consultor, al cargar un expediente puede seleccionar a cuál de sus empresas o sucursales subordinadas (ej: *Porsche Santa Fe*) pertenecerá el documento directamente, mostrándose ordenado en la tabla de control operativo.
*   **Estructura Interna:** Apartado para crear y gestionar cuentas de personal operativo (`Tipo 1`, `Tipo 2`, `Tipo 3`) permitiendo definir a qué sucursal asignarlos para delegar la subida y visualización de sus correspondientes expedientes corporativos.

---

## 8. MANUAL DE INSTALACIÓN Y DESPLIEGUE LOCAL (XONEXKA EN XAMPP)

1.  **Requisitos Previos:**
    *   Instalar XAMPP con soporte para PHP 8.0 o superior.
2.  **Ubicación de Archivos:**
    *   Colocar la carpeta del proyecto en `C:\xampp\htdocs\upgrade_systems_xonexka` (para la versión roja).
3.  **Configuración de Base de Datos:**
    *   Acceder a `http://localhost/phpmyadmin`.
    *   Crear una base de datos llamada `upgrade_systems` (o la configurada en `config/conexion.php`).
    *   Importar el archivo estructurado SQL de respaldo `upgrade_systems_db.sql` provisto en la raíz.
4.  **Ejecutar la Aplicación:**
    *   Iniciar los servicios Apache y MySQL en el Panel de Control de XAMPP.
    *   Abrir en el navegador:
        *   **Portal Cliente (Xonexka):** `http://localhost:8080/upgrade_systems_xonexka/public/cliente/login.html`
        *   **Administración (Xonexka):** `http://localhost:8080/upgrade_systems_xonexka/public/admin/login.html`
5.  **Configurar Tareas Programadas (Alertas en Producción):**
    *   En Linux, configurar un CronJob para ejecutar el motor de alertas diariamente:
        `0 8 * * * /usr/bin/php /var/www/html/controllers/alertas_automaticas.php`
    *   En Windows local, configurar una Tarea Programada que llame al ejecutable de PHP:
        `C:\xampp\php\php.exe -f C:\xampp\htdocs\upgrade_systems_xonexka\controllers\alertas_automaticas.php`
