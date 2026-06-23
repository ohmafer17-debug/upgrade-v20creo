<?php
// Si Ángel usa sesiones para pintar el nombre y el rango, dejamos el bloque listo aquí arriba
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulamos los datos de tu captura en caso de que no vengan de la base de datos aún
$organizacion = isset($_SESSION['empresa_cod']) ? $_SESSION['empresa_cod'] : 'PEMEX-01';
$usuario_nombre = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Pemex EDO.Mex';
$rango_asignado = isset($_SESSION['rol']) ? $_SESSION['rol'] : 'consultor'; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>UPS CLIENTES | Plataforma Corporativa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        :root { 
            --ups-sidebar-bg: #1e3a8a; 
            --ups-sidebar-hover: #1e40af;
            --ups-accent: #2563eb; 
            --ups-bg: #f8fafc; 
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            background: var(--ups-bg); 
            color: #334155; 
            display: flex; 
            height: 100vh; 
            overflow: hidden; 
        }
        
        /* BARRA LATERAL AZUL (COMO TU CAPTURA) */
        .sidebar { 
            width: 280px; 
            background: var(--ups-sidebar-bg); 
            color: white; 
            display: flex; 
            flex-direction: column; 
            padding: 30px 20px; 
            box-sizing: border-box; 
            justify-content: space-between;
        }
        .sidebar .brand { 
            font-size: 1.3rem; 
            font-weight: 700; 
            margin-bottom: 40px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            letter-spacing: 0.5px;
        }
        
        .menu-items { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
        .menu-items li a { 
            color: #bfdbfe; 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            padding: 14px 18px; 
            border-radius: 12px; 
            font-size: 0.95rem; 
            font-weight: 500; 
            transition: 0.3s;
        }
        .menu-items li a:hover { background: var(--ups-sidebar-hover); color: white; }
        .menu-items li.active a { background: var(--ups-accent); color: white; font-weight: 600; }
        
        .btn-logout { 
            color: #fca5a5; 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 12px 18px; 
            font-size: 0.95rem; 
            font-weight: 600;
        }

        /* ÁREA DE CONTENIDO PRINCIPAL */
        .main-content { flex: 1; padding: 40px; box-sizing: border-box; overflow-y: auto; }
        
        .header-dashboard { 
            margin-bottom: 35px; 
            border-bottom: 2px solid #e2e8f0; 
            padding-bottom: 25px; 
        }
        .header-dashboard h1 { 
            margin: 0; 
            font-size: 1.8rem; 
            font-weight: 700; 
            color: var(--text-dark); 
        }
        .header-dashboard p { 
            margin: 8px 0 0 0; 
            color: var(--text-muted); 
            font-size: 0.95rem; 
        }

        /* CAJA DEL FORMULARIO DE ALTA BLANCO */
        .form-box { 
            background: white; 
            padding: 40px; 
            border-radius: 24px; 
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.03); 
            border: 1px solid #e2e8f0; 
            max-width: 750px; 
            margin-top: 20px;
        }
        .form-box h2 { 
            margin-top: 0; 
            font-size: 1.4rem; 
            margin-bottom: 30px; 
            color: var(--text-dark); 
            font-weight: 700; 
        }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: span 2; }
        
        .form-box label { 
            font-size: 0.85rem; 
            font-weight: 600; 
            margin-bottom: 10px; 
            color: #475569; 
        }
        .form-box input, .form-box select { 
            width: 100%; 
            padding: 14px 18px; 
            border: 1px solid #cbd5e1; 
            border-radius: 12px; 
            box-sizing: border-box; 
            font-size: 0.95rem; 
            background: white; 
            color: var(--text-dark);
            outline: none;
            transition: 0.2s;
        }
        .form-box input:focus, .form-box select:focus {
            border-color: var(--ups-accent);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        /* BOTÓN DE ENVIAR AZUL REIDSEÑADO */
        .btn-submit { 
            background: #1e3a8a; 
            color: white; 
            border: none; 
            padding: 16px; 
            width: 100%; 
            border-radius: 12px; 
            font-size: 1rem; 
            font-weight: 600; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; 
            margin-top: 15px;
            transition: 0.2s;
        }
        .btn-submit:hover { background: #1e40af; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <div class="brand"><i class="fas fa-shield-halved"></i> UPS CLIENTES</div>
            <ul class="menu-items">
                <li><a href="#"><i class="fas fa-sitemap"></i> Estructura Orgánica</a></li>
                <li><a href="#"><i class="fas fa-folder"></i> Expediente PC</a></li>
                <li class="active"><a href="#"><i class="fas fa-user-plus"></i> Gestión de Rangos</a></li>
            </ul>
        </div>
        <div>
            <a href="logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i> Cerrar Sesión</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header-dashboard">
            <h1>Organización: <span id="lblOrganizacion"><?php echo $organizacion; ?></span></h1>
            <p>Usuario: <strong><?php echo $usuario_nombre; ?></strong> | Rango Asignado: <span id="rangoAsignado" style="font-weight: 600;"><?php echo $rango_asignado; ?></span></p>
        </div>

        <div id="contenedorModulo" class="form-box">
            <h2>Dar de Alta Nodo en la Cadena</h2>
            <form id="altaUsuarioForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre del Nodo / Empleado</label>
                        <input type="text" id="usrNombre" placeholder="Ej: Walmart Tienda Toluca" required>
                    </div>
                    <div class="form-group">
                        <label>Asignación de Rango</label>
                        <select id="usrRol" required></select>
                    </div>
                    <div class="form-group full">
                        <label>Correo de Acceso Colectivo / Personal</label>
                        <input type="email" id="usrEmail" placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="form-group full">
                        <label>Contraseña de Seguridad</label>
                        <input type="password" id="usrPass" required>
                    </div>
                </div>
                <br>
                <button type="submit" class="btn-submit"><i class="fas fa-network-wired"></i> Autorizar Alta</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // 1. Capturamos el texto exacto que está pintado arriba en el HTML ("consultor")
            const rolDetectado = document.getElementById('rangoAsignado').innerText.trim().toLowerCase();
            const selectRol = document.getElementById('usrRol');
            const contenedorModulo = document.getElementById('contenedorModulo');

            // Limpiamos cualquier opción previa estática heredada o guardada en caché
            selectRol.options.length = 0;

            // 2. Aplicamos la jerarquía piramidal de creación estricta
            if (rolDetectado === 'administrador') {
                selectRol.innerHTML = `
                    <option value="Consultor">Consultor</option>
                    <option value="Responsable Nacional">Responsable Nacional</option>
                    <option value="Tipo 1">Tipo 1</option>
                    <option value="Tipo 2">Tipo 2</option>
                    <option value="Tipo 3">Tipo 3</option>
                `;
            } 
            else if (rolDetectado === 'consultor') {
                // 🔥 SINTONÍA COMPLETA: Si es consultor, fuerza el renderizado exclusivo de estos 4 roles
                selectRol.innerHTML = `
                    <option value="Responsable Nacional">Responsable Nacional</option>
                    <option value="Tipo 1">Tipo 1</option>
                    <option value="Tipo 2">Tipo 2</option>
                    <option value="Tipo 3">Tipo 3</option>
                `;
            } 
            else if (rolDetectado === 'tipo 1') {
                selectRol.innerHTML = `
                    <option value="Tipo 1">Tipo 1</option>
                    <option value="Tipo 2">Tipo 2</option>
                    <option value="Tipo 3">Tipo 3</option>
                `;
            } 
            else if (rolDetectado === 'tipo 2' || rolDetectado === 'tipo 3') {
                // Denegación de acceso inmediata para Tipo 2 y Tipo 3
                contenedorModulo.innerHTML = `
                    <div style="color:var(--ups-red); font-weight:600; text-align:center; padding:30px 10px;">
                        <i class="fas fa-lock-open fa-2x" style="margin-bottom:15px;"></i><br>
                        Tu rango actual de [${rolDetectado.toUpperCase()}] no posee los privilegios requeridos para autorizar nodos o altas en la cadena.
                    </div>`;
            }
        });

        // ENVÍO SEGURO DE LA INFORMACIÓN AL BACKEND DE ÁNGEL
        document.getElementById('altaUsuarioForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const rolCreador = document.getElementById('rangoAsignado').innerText.trim();

            const payload = {
                accion: 'registrar_usuario_rol',
                rol_creador: rolCreador,
                nombre: document.getElementById('usrNombre').value.trim(),
                email: document.getElementById('usrEmail').value.trim(),
                rol: document.getElementById('usrRol').value,
                pass: document.getElementById('usrPass').value.trim()
            };

            try {
                const r = await fetch('usuarios_procesar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const res = await r.json();
                if (res.status === 'success') {
                    alert("¡Nodo autorizado e inyectado en la cadena corporativa con éxito!");
                    document.getElementById('altaUsuarioForm').reset();
                } else {
                    alert("Error del Sistema: " + res.message);
                }
            } catch(e) {
                alert("Error de comunicación: El backend no devolvió una respuesta válida.");
            }
        });
    </script>
</body>
</html>