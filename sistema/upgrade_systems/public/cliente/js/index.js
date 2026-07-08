// 🚀 DETECCIÓN DINÁMICA DE ENTORNO (LOCAL VS PRODUCCIÓN)
const base_url = window.location.origin + (window.location.hostname === 'localhost' ? '/upgrade_systems' : '');
const urlProcesador  = `${base_url}/controllers/cliente_procesar.php`;
const empresaCod      = localStorage.getItem('cliente_sesion_id'); 
const userName        = localStorage.getItem('cliente_sesion_nombre');
const rolActualSesion = localStorage.getItem('cliente_sesion_rol'); 

if (!empresaCod || !rolActualSesion || !userName || empresaCod === 'UPS-STAFF') {
    localStorage.clear(); window.location.replace("login.html");
}

window.addEventListener('pageshow', function (event) {
    if (event.persisted || (typeof window.performance != "undefined" && window.performance.navigation.type === 2)) {
        if (!localStorage.getItem('cliente_sesion_id')) { window.location.replace("login.html"); }
    }
});

let cacheDocumentos = [];
let cacheUsuarios = []; // Cache local de colaboradores para ordenación
let listadoCategoriasDinamicas = [];
let ordenColumnaActual = 'cod';
let ordenDireccionActual = 'asc';

document.addEventListener("DOMContentLoaded", () => {
    if (document.getElementById('nombreEmpresa')) document.getElementById('nombreEmpresa').innerText = empresaCod;
    if (document.getElementById('containerMetaUsuario')) document.getElementById('containerMetaUsuario').innerHTML = `Usuario: <strong>${userName}</strong> | Rango: <span class="badge role">${rolActualSesion}</span>`;
    aplicarRestrictionsMatriz(); cargarUsuariosCliente(); cargarDocumentosCliente(); cargarLogoEmpresa();
});

async function cargarLogoEmpresa() {
    try {
        const r = await fetch(urlProcesador, { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ accion: 'obtener_logo_empresa', empresa_cod: empresaCod }) 
        });
        const res = await r.json();
        if (res.status === 'success' && res.logo) {
            const logoContainer = document.getElementById('sidebarLogoContainer');
            const logoImg = document.getElementById('sidebarLogo');
            if (logoContainer && logoImg) {
                logoImg.src = `${base_url}/public/uploads/logos/${res.logo}`;
                logoContainer.style.display = 'block';
            }
        }
    } catch (err) {
        console.error("Error al cargar el logo corporativo:", err);
    }
}

function aplicarRestrictionsMatriz() {
    const selectRol = document.getElementById('userRol'); const boxForm = document.getElementById('boxFormPersonal');
    const msgBloqueo = document.getElementById('msgBloqueoRol'); const formSubidaDocs = document.getElementById('formSubidaDocs');
    if(selectRol) selectRol.innerHTML = ""; const rAct = rolActualSesion.toLowerCase();
    if (rAct === 'administrador' || rAct === 'consultor') { if(boxForm) boxForm.style.display = "block"; if(msgBloqueo) msgBloqueo.style.display = "none"; if(selectRol) selectRol.innerHTML += `<option value="Responsable Nacional" selected>Responsable Nacional</option><option value="Tipo 1">Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`; } 
    else if (rAct === 'responsable_nacional' || rAct === 'responsable nacional') { if(boxForm) boxForm.style.display = "block"; if(msgBloqueo) msgBloqueo.style.display = "none"; if(selectRol) selectRol.innerHTML += `<option value="Tipo 1" selected>Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`; }
    else if (rAct === 'tipo 1') { if(boxForm) boxForm.style.display = "block"; if(msgBloqueo) msgBloqueo.style.display = "none"; if(selectRol) selectRol.innerHTML += `<option value="Tipo 1" selected>Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`; }
    else { if(msgBloqueo) msgBloqueo.innerHTML = `<i class="fas fa-ban"></i> Tu rango operativo NO tiene privilegios para crear nodos.`; if(msgBloqueo) msgBloqueo.style.display = "block"; if(boxForm) boxForm.style.display = "none"; }
    if (rAct === 'tipo 2') { if(formSubidaDocs) formSubidaDocs.innerHTML = `<div style="padding:20px; text-align:center; color:#1e3a8a; background:#eff6ff; border:1px dashed #3b82f6; border-radius:12px; font-weight:600;">Modo Modificación Activo: Use el botón "Actualizar" en el catálogo.</div>`; }
    if (rAct === 'tipo 3') { if(formSubidaDocs) formSubidaDocs.innerHTML = `<div style="padding:25px; text-align:center; color:#7c2d12; background:#fff7ed; border:1px dashed #fb923c; border-radius:12px; font-weight:600;">Modo de Solo Lectura habilitado para su rango administrativo.</div>`; }
}

async function cargarDocumentosCliente() {
    const rAct = rolActualSesion.toLowerCase(); if (rAct === 'administrador') return;
    const r = await fetch(urlProcesador, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'listar_documentos', empresa_cod: empresaCod, rol_ejecutor: rolActualSesion }) });
    const res = await r.json();
    if(res.status === 'success') {
        cacheDocumentos = res.data;
        listadoCategoriasDinamicas = [];
        res.data.forEach(d => { if(!listadoCategoriasDinamicas.includes(d.tipo_doc)) listadoCategoriasDinamicas.push(d.tipo_doc); });
        actualizarComponenteFiltroSelect(); renderizarTablaFiltrada(res.data);
    }
}

function actualizarComponenteFiltroSelect() {
    const selectFiltro = document.getElementById('filtroCategoriaSelect'); if(!selectFiltro) return;
    selectFiltro.innerHTML = `<option value="TODAS">-- Mostrar Todo el Expediente --</option>`;
    listadoCategoriasDinamicas.forEach(cat => { selectFiltro.innerHTML += `<option value="${cat}">${cat}</option>`; });
}

function filtrarTablaPorCategoria(categoriaElegida) {
    if(categoriaElegida === 'TODAS') renderizarTablaFiltrada(cacheDocumentos);
    else renderizarTablaFiltrada(cacheDocumentos.filter(d => d.tipo_doc === categoriaElegida));
}

function renderizarTablaFiltrada(arregloDatos) {
    const b = document.getElementById('tablaDocsBody'); if(!b) return; b.innerHTML = "";
    arregloDatos.forEach((d, index) => {
        let color = d.color_calculado; let txt = d.estatus_texto;
        
        let btnVerODescarga = `<a href="${base_url}/controllers/documento_descargar.php?archivo=${d.nombre_archivo_fisico}&token_seguro=${empresaCod}" target="_blank" class="btn-action btn-view" onclick="registrarAuditoriaLectura(${d.id})"><i class="fas fa-eye"></i> Ver</a>`;
        let botonActualizar = `<button class="btn-action btn-update" id="btn-up-${index}"><i class="fas fa-sync-alt"></i> Actualizar</button>`;
        let botonSuspender = parseInt(d.estatus) === 0 ? `<button class="btn-action btn-activate" onclick="suspenderArchivo(${d.id})">Desarchivar</button>` : `<button class="btn-action btn-suspend" onclick="suspenderArchivo(${d.id})">Archivar</button>`;
        let botonHistorial = `<button class="btn-action btn-history" onclick="abrirHistorialDocumento(${d.id}, '${d.tipo_doc}')"><i class="fas fa-clock-rotate-left"></i> Historial</button>`;
        let badgeColorClass = color === 'green' ? 'green' : (color === 'yellow' ? 'orange' : (color === 'gray' ? 'neutral' : 'red'));

        // Trazabilidad y accesos
        const subidoText = d.subido_por ? d.subido_por : 'Sistema';
        const vistoText = d.visto_por ? d.visto_por : 'Nadie aún';
        const trazabilidadHtml = `
            <div style="font-size:0.8rem; color:#64748b; line-height: 1.4;">
                <div>Vence: <code>${d.fecha_vencimiento}</code></div>
                <div>Subido por: <strong>${subidoText}</strong></div>
                <div><i class="fas fa-eye" style="color:#94a3b8;"></i> Visto por: <strong>${vistoText}</strong></div>
            </div>
        `;

        b.innerHTML += `<tr>
            <td><span class="semaphore ${color}"></span></td>
            <td><strong>${d.tipo_doc}</strong></td>
            <td>${d.nombre_limpio}</td>
            <td>${trazabilidadHtml}</td>
            <td><span class="badge ${badgeColorClass}">${txt}</span></td>
            <td class="actions-cell">
                <div class="btn-action-group">${btnVerODescarga}${botonActualizar}</div>
                <div class="btn-action-group">${botonSuspender}${botonHistorial}</div>
            </td>
        </tr>`;
        
        setTimeout(() => {
            const btn = document.getElementById(`btn-up-${index}`);
            if (btn) btn.onclick = () => {
                let mot = prompt("Escribe el motivo de la actualización del documento (Requerido):");
                if (mot === null) return;
                if (mot.trim() === "") {
                    alert("La justificación es obligatoria para actualizar el archivo.");
                    return;
                }
                activarFlujoActualizar(d.tipo_doc, d.nombre_limpio, d.fecha_vencimiento, mot.trim());
            };
        }, 10);
    });
}

async function registrarAuditoriaLectura(idDocumento) {
    try {
        await fetch(urlProcesador, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'marcar_como_visto',
                id_documento: idDocumento,
                usuario_ejecutor: userName
            })
        });
        cargarDocumentosCliente();
    } catch (e) {
        console.error("Error al registrar auditoria de lectura:", e);
    }
}

async function suspenderArchivo(idDocumento) {
    let motivo = prompt("Escribe la razón o motivo de archivar/desarchivar este expediente (Requerido):");
    if (motivo === null) return;
    if (motivo.trim() === "") {
        alert("La justificación es obligatoria para cambiar el estatus del expediente.");
        return;
    }
    try {
        const r = await fetch(urlProcesador, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'suspender_documento',
                id_documento: idDocumento,
                rol_ejecutor: rolActualSesion,
                motivo: motivo.trim()
            })
        });
        const res = await r.json();
        alert(res.message);
        if (res.status === 'success') {
            cargarDocumentosCliente();
        }
    } catch (e) {
        console.error("Error al suspender/activar documento:", e);
        alert("Error de conexión al cambiar el estado del archivo.");
    }
}

function activarFlujoActualizar(tipoDoc, nombreActual, fVenc, mot) {
    document.getElementById('docTipo').value = tipoDoc;
    document.getElementById('docNombre').value = nombreActual + " (" + mot + ")";
    document.getElementById('docVencimiento').value = fVenc;
    document.getElementById('esActualizacion').value = "si";
    document.getElementById('docMotivo').value = mot;
    document.getElementById('containerFormSubida').scrollIntoView({ behavior: 'smooth' });
}

async function unificadoSubmitForm(e) {
    e.preventDefault(); const formData = new FormData();
    formData.append('accion', 'subir_documento'); formData.append('rol_ejecutor', rolActualSesion);
    formData.append('usuario_ejecutor', userName); formData.append('empresa_cod', empresaCod);
    
    // Capturar fecha y hora local del instante real sin desfases UTC
    const ahora = new Date();
    const yyyy = ahora.getFullYear();
    const mm = String(ahora.getMonth() + 1).padStart(2, '0');
    const dd = String(ahora.getDate()).padStart(2, '0');
    const hh = String(ahora.getHours()).padStart(2, '0');
    const mi = String(ahora.getMinutes()).padStart(2, '0');
    const ss = String(ahora.getSeconds()).padStart(2, '0');
    const fechaLocal = `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
    
    formData.append('tipo_doc', document.getElementById('docTipo').value.trim());
    formData.append('nombre_personalizado', document.getElementById('docNombre').value.trim());
    formData.append('fecha_vencimiento', document.getElementById('docVencimiento').value);
    formData.append('fecha_subida', fechaLocal);
    formData.append('es_actualizacion', document.getElementById('esActualizacion').value);
    formData.append('motivo', document.getElementById('docMotivo').value);
    formData.append('archivo', document.getElementById('docFile').files[0]);
    
    const r = await fetch(urlProcesador, { method: 'POST', body: formData }); const res = await r.json(); alert(res.message);
    if(res.status === 'success') { 
        document.getElementById('uploadDocForm').reset(); 
        document.getElementById('esActualizacion').value = "no";
        document.getElementById('docMotivo').value = "";
        cargarDocumentosCliente(); 
    }
}

// =================================================================
// 🚀 MODAL DEL HISTORIAL DE VERSIONES ANTERIORES
// =================================================================
async function abrirHistorialDocumento(idDocumento, categoriaDoc) {
    const b = document.getElementById('historialTableBody');
    if(!b) return;
    b.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Cargando historial...</td></tr>`;
    
    document.getElementById('modalHistorial').style.display = 'block';
    
    try {
        const r = await fetch(urlProcesador, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'ver_historial_documento', id_documento: idDocumento })
        });
        const res = await r.json();
        if(res.status === 'success' && res.data.length > 0) {
            b.innerHTML = "";
            res.data.forEach(h => {
                const desc_url = `${base_url}/controllers/documento_descargar.php?archivo=${h.nombre_archivo_fisico}&token_seguro=${empresaCod}`;
                const downloadBtn = `<a href="${desc_url}" target="_blank" class="btn-action btn-view" style="font-size:0.8rem; padding:6px 12px; display:inline-flex; align-items:center; gap:6px;"><i class="fas fa-download"></i> Descargar</a>`;
                const fechaVence = h.fecha_vencimiento && h.fecha_vencimiento !== '0000-00-00' ? h.fecha_vencimiento : 'Sin vencimiento';
                const autorText = h.subido_por ? h.subido_por : 'Sistema';
                
                b.innerHTML += `<tr>
                    <td style="padding:14px 16px; border-bottom:1px solid #e2e8f0; font-weight:600; color:#334155;">${h.nombre_personalizado}</td>
                    <td style="padding:14px 16px; border-bottom:1px solid #e2e8f0;"><code>${fechaVence}</code></td>
                    <td style="padding:14px 16px; border-bottom:1px solid #e2e8f0; color:#64748b;">${h.fecha_modificacion}</td>
                    <td style="padding:14px 16px; border-bottom:1px solid #e2e8f0; font-weight:600; color:#475569;">${autorText}</td>
                    <td style="padding:14px 16px; border-bottom:1px solid #e2e8f0; font-style:italic; color:#475569;">${h.motivo || 'Sin especificar'}</td>
                    <td style="padding:14px 16px; border-bottom:1px solid #e2e8f0; text-align:center;">${downloadBtn}</td>
                </tr>`;
            });
        } else {
            b.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:25px; color:#64748b; font-weight:500;">No existen versiones previas registradas para este documento.</td></tr>`;
        }
    } catch (err) {
        console.error(err);
        b.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:25px; color:#ef4444; font-weight:500;"><i class="fas fa-exclamation-triangle"></i> Error de conexión al cargar versiones.</td></tr>`;
    }
}

function cerrarModalHistorial() {
    document.getElementById('modalHistorial').style.display = 'none';
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('modalHistorial');
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});


// =================================================================
// 🚀 ORDENACIÓN INTERACTIVA DE DOCUMENTOS
// =================================================================
let ordenDocColumnaActual = 'id';
let ordenDocDireccionActual = 'desc';

function ordenarDocumentos(columna) {
    if (ordenDocColumnaActual === columna) {
        ordenDocDireccionActual = ordenDocDireccionActual === 'asc' ? 'desc' : 'asc';
    } else {
        ordenDocColumnaActual = columna;
        ordenDocDireccionActual = 'asc';
    }
    
    // Resetear iconos de ordenamiento específicos de la tabla de documentos
    document.querySelectorAll('#tablaDocsPrincipal .sortable-headers th i').forEach(icon => {
        icon.className = 'fas fa-sort';
        icon.style.opacity = '0.4';
    });
    
    const thIndex = { 'color': 0, 'categoria': 1, 'nombre': 2, 'vencimiento': 3, 'estatus': 4 }[columna];
    const headers = document.querySelectorAll('#tablaDocsPrincipal .sortable-headers th');
    if (headers && headers[thIndex]) {
        const icon = headers[thIndex].querySelector('i');
        if (icon) {
            icon.className = ordenDocDireccionActual === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
            icon.style.opacity = '0.9';
        }
    }
    
    cacheDocumentos.sort((a, b) => {
        let valA = '';
        let valB = '';
        
        if (columna === 'color') {
            const pesoColor = { 'red': 4, 'orange': 3, 'yellow': 2, 'green': 1, 'gray': 0, 'neutral': 0 };
            valA = pesoColor[a.color_calculado] !== undefined ? pesoColor[a.color_calculado] : -1;
            valB = pesoColor[b.color_calculado] !== undefined ? pesoColor[b.color_calculado] : -1;
        } else if (columna === 'categoria') {
            valA = (a.tipo_doc || '').toString().toLowerCase();
            valB = (b.tipo_doc || '').toString().toLowerCase();
        } else if (columna === 'nombre') {
            valA = (a.nombre_limpio || '').toString().toLowerCase();
            valB = (b.nombre_limpio || '').toString().toLowerCase();
        } else if (columna === 'vencimiento') {
            valA = a.fecha_vencimiento && a.fecha_vencimiento !== '0000-00-00' ? a.fecha_vencimiento : '9999-12-31';
            valB = b.fecha_vencimiento && b.fecha_vencimiento !== '0000-00-00' ? b.fecha_vencimiento : '9999-12-31';
        } else if (columna === 'estatus') {
            valA = (a.estatus_texto || '').toString().toLowerCase();
            valB = (b.estatus_texto || '').toString().toLowerCase();
        }
        
        if (valA < valB) return ordenDocDireccionActual === 'asc' ? -1 : 1;
        if (valA > valB) return ordenDocDireccionActual === 'asc' ? 1 : -1;
        return 0;
    });
    
    // Filtrar y renderizar según la categoría seleccionada actualmente
    const filtro = document.getElementById('filtroCategoriaSelect').value;
    filtrarTablaPorCategoria(filtro);
}

// =================================================================
// 🚀 ORDENACIÓN INTERACTIVA DE COLABORADORES
// =================================================================
function ordenarUsuarios(columna) {
    if (ordenColumnaActual === columna) {
        ordenDireccionActual = ordenDireccionActual === 'asc' ? 'desc' : 'asc';
    } else {
        ordenColumnaActual = columna;
        ordenDireccionActual = 'asc';
    }
    
    // Resetear iconos de ordenamiento
    document.querySelectorAll('.sortable-headers th i').forEach(icon => {
        icon.className = 'fas fa-sort';
        icon.style.opacity = '0.4';
    });
    
    const thIndex = { 'cod': 0, 'nombre': 1, 'email': 2, 'rol': 3, 'activo': 4 }[columna];
    const headers = document.querySelectorAll('.sortable-headers th');
    if (headers && headers[thIndex]) {
        const icon = headers[thIndex].querySelector('i');
        if (icon) {
            icon.className = ordenDireccionActual === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
            icon.style.opacity = '0.9';
        }
    }
    
    cacheUsuarios.sort((a, b) => {
        let valA = a[columna] || '';
        let valB = b[columna] || '';
        
        if (columna === 'activo') {
            valA = parseInt(valA);
            valB = parseInt(valB);
        } else {
            valA = valA.toString().toLowerCase();
            valB = valB.toString().toLowerCase();
        }
        
        if (valA < valB) return ordenDireccionActual === 'asc' ? -1 : 1;
        if (valA > valB) return ordenDireccionActual === 'asc' ? 1 : -1;
        return 0;
    });
    
    renderizarTablaUsuarios();
}

function renderizarTablaUsuarios() {
    const b = document.getElementById('tablaUsuariosBody'); if(!b) return; b.innerHTML = ""; 
    cacheUsuarios.forEach(u => { 
        let contactos = `<div><span style="font-weight:600;"><i class="fas fa-envelope"></i> Principal:</span> ${u.email}</div>`;
        if (u.email_adicional) {
            contactos += `<div><span style="font-weight:600;"><i class="fas fa-envelope"></i> Adicional:</span> ${u.email_adicional}</div>`;
        }
        if (u.telefono_principal) {
            contactos += `<div><span style="font-weight:600;"><i class="fas fa-phone"></i> Principal:</span> ${u.telefono_principal}</div>`;
        }
        if (u.telefono_adicional) {
            contactos += `<div><span style="font-weight:600;"><i class="fas fa-phone"></i> Adicional:</span> ${u.telefono_adicional}</div>`;
        }
        
        const esActivo = parseInt(u.activo) === 1;
        const estatusBadge = `<span class="badge ${esActivo ? 'green' : 'red'}">${esActivo ? 'Activa' : 'Suspendida'}</span>`;
        
        b.innerHTML += `<tr>
            <td><code>${u.cod}</code></td>
            <td><strong>${u.nombre}</strong></td>
            <td>${contactos}</td>
            <td><span class="badge role">${u.rol || 'No asignado'}</span></td>
            <td>${estatusBadge}</td>
        </tr>`; 
    }); 
}

async function cargarUsuariosCliente() { 
    const r = await fetch(urlProcesador, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'listar_usuarios', empresa_cod: empresaCod }) }); 
    const res = await r.json();
    if(res.status === 'success') { 
        cacheUsuarios = res.data;
        renderizarTablaUsuarios();
    } 
}

// =================================================================
// FORMULARIOS Y VISTAS GENERALES
// =================================================================
if(document.getElementById('usuarioClienteForm')) {
    document.getElementById('usuarioClienteForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const pass = document.getElementById('userPass').value.trim();
        const passErr = validarPasswordComplejidad(pass);
        if (passErr) {
            alert("Seguridad de Contraseña: " + passErr);
            return;
        }

        const payload = {
            accion: 'crear_usuario_operativo',
            rol_ejecutor: rolActualSesion,
            nombre: document.getElementById('userNombre').value.trim(),
            rol: document.getElementById('userRol').value,
            email: document.getElementById('userEmail').value.trim(),
            email_adicional: document.getElementById('userEmailPersonal').value.trim(),
            telefono_principal: document.getElementById('userTelEmpresa').value.trim(),
            telefono_adicional: document.getElementById('userTelPersonal').value.trim(),
            pass: pass,
            empresa_cod: empresaCod
        };

        const r = await fetch(urlProcesador, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const res = await r.json();
        if(res.status === 'success') {
            alert("¡Colaborador registrado exitosamente!");
            document.getElementById('usuarioClienteForm').reset();
            cargarUsuariosCliente();
        } else {
            alert("Error: " + res.message);
        }
    });
}

function validarPasswordComplejidad(password) {
    if (password.length < 10) return "La contraseña debe tener al menos 10 caracteres.";
    if (!/[A-Z]/.test(password)) return "La contraseña debe tener al menos una letra mayúscula.";
    if (!/[a-z]/.test(password)) return "La contraseña debe tener al menos una letra minúscula.";
    if (!/[0-9]/.test(password)) return "La contraseña debe tener al menos un número.";
    if (!/[^a-zA-Z0-9]/.test(password)) return "La contraseña debe tener al menos un carácter especial (ej. #, $, @, etc.).";
    
    for (let i = 0; i < password.length - 3; i++) {
        if (password[i] === password[i+1] && password[i] === password[i+2] && password[i] === password[i+3]) {
            return "La contraseña no puede contener más de 3 caracteres idénticos consecutivos.";
        }
    }
    
    for (let i = 0; i < password.length - 3; i++) {
        let c1 = password.charCodeAt(i);
        let c2 = password.charCodeAt(i+1);
        let c3 = password.charCodeAt(i+2);
        let c4 = password.charCodeAt(i+3);
        
        if (c2 === c1 + 1 && c3 === c2 + 1 && c4 === c3 + 1) {
            return "La contraseña no puede contener más de 3 letras o números consecutivos en orden ascendente (ej. '1234' o 'abcd').";
        }
        if (c2 === c1 - 1 && c3 === c2 - 1 && c4 === c3 - 1) {
            return "La contraseña no puede contener más de 3 letras o números consecutivos en orden descendente (ej. '4321' o 'dcba').";
        }
    }
    return null;
}

if(document.getElementById('uploadDocForm')) document.getElementById('uploadDocForm').addEventListener('submit', unificadoSubmitForm);

function cambiarVista(vista) { 
    document.querySelectorAll('.vista-seccion').forEach(s => s.classList.remove('activa')); 
    document.getElementById('vista-' + vista).classList.add('activa'); 
    
    document.querySelectorAll('.sidebar ul.menu-items li').forEach(li => li.classList.remove('active'));
    const activeLi = document.getElementById('menu-' + vista);
    if (activeLi) activeLi.classList.add('active');
}

if(document.getElementById('cambioPassForm')) {
    document.getElementById('cambioPassForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const passActual = document.getElementById('passActual').value.trim();
        const passNueva = document.getElementById('passNueva').value.trim();
        const passNuevaConf = document.getElementById('passNuevaConf').value.trim();

        if (passNueva !== passNuevaConf) {
            alert("La nueva contraseña y su confirmación no coinciden.");
            return;
        }

        const passErr = validarPasswordComplejidad(passNueva);
        if (passErr) {
            alert("Nueva Contraseña: " + passErr);
            return;
        }

        const payload = {
            accion: 'cambiar_contrasena_propia',
            empresa_cod: empresaCod,
            pass_actual: passActual,
            pass_nueva: passNueva
        };

        try {
            const r = await fetch(urlProcesador, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await r.json();
            alert(res.message);
            if (res.status === 'success') {
                document.getElementById('cambioPassForm').reset();
                cambiarVista('resumen');
            }
        } catch (err) {
            console.error(err);
            alert("Error de conexión al intentar cambiar la contraseña.");
        }
    });
}

function cerrarSesion() { localStorage.clear(); window.location.replace('login.html'); }
