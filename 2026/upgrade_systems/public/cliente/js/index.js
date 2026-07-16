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
let mapaNombresEmpresas = {};
let listadoCategoriasDinamicas = [];
let ordenColumnaActual = 'cod';
let ordenDireccionActual = 'asc';

document.addEventListener("DOMContentLoaded", () => {
    if (document.getElementById('nombreEmpresa')) document.getElementById('nombreEmpresa').innerText = empresaCod;
    if (document.getElementById('containerMetaUsuario')) document.getElementById('containerMetaUsuario').innerHTML = `Usuario: <strong>${userName}</strong> | Rango: <span class="badge role">${rolActualSesion}</span>`;
    aplicarRestrictionsMatriz(); cargarUsuariosCliente(); cargarLogoEmpresa();
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
    const msgBloqueo = document.getElementById('msgBloqueoRol');
    if(selectRol) selectRol.innerHTML = ""; const rAct = rolActualSesion.toLowerCase();
    if (rAct === 'administrador' || rAct === 'consultor') { if(boxForm) boxForm.style.display = "block"; if(msgBloqueo) msgBloqueo.style.display = "none"; if(selectRol) selectRol.innerHTML += `<option value="Responsable Nacional" selected>Responsable Nacional</option><option value="Tipo 1">Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`; } 
    else if (rAct === 'responsable_nacional' || rAct === 'responsable nacional') { if(boxForm) boxForm.style.display = "block"; if(msgBloqueo) msgBloqueo.style.display = "none"; if(selectRol) selectRol.innerHTML += `<option value="Tipo 1" selected>Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`; }
    else if (rAct === 'tipo 1') { if(boxForm) boxForm.style.display = "block"; if(msgBloqueo) msgBloqueo.style.display = "none"; if(selectRol) selectRol.innerHTML += `<option value="Tipo 1" selected>Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`; }
    else { if(msgBloqueo) msgBloqueo.innerHTML = `<i class="fas fa-ban"></i> Tu rango operativo NO tiene privilegios para crear nodos.`; if(msgBloqueo) msgBloqueo.style.display = "block"; if(boxForm) boxForm.style.display = "none"; }
    
    // Mostrar/ocultar avisos según rango operativo de documentos
    const msgAvisoTipo2 = document.getElementById('msgAvisoTipo2');
    const msgAvisoTipo3 = document.getElementById('msgAvisoTipo3');
    const wrapperFormReal = document.getElementById('wrapperFormRealSubida');
    const containerForm = document.getElementById('containerFormSubida');
    
    if (msgAvisoTipo2) msgAvisoTipo2.style.display = 'none';
    if (msgAvisoTipo3) msgAvisoTipo3.style.display = 'none';
    if (wrapperFormReal) wrapperFormReal.style.display = 'block';
    
    const menuCargaDoc = document.getElementById('menu-carga-doc');
    if (menuCargaDoc) menuCargaDoc.style.display = 'block';

    if (rAct === 'tipo 2') {
        if (msgAvisoTipo2) msgAvisoTipo2.style.display = 'block';
        if (wrapperFormReal) wrapperFormReal.style.display = 'none';
        if (menuCargaDoc) menuCargaDoc.style.display = 'none';
    }
    if (rAct === 'tipo 3') {
        if (msgAvisoTipo3) msgAvisoTipo3.style.display = 'block';
        if (wrapperFormReal) wrapperFormReal.style.display = 'none';
        if (menuCargaDoc) menuCargaDoc.style.display = 'none';
    }

    // 🚀 Regla: Para el administrador / Tipo 1 NO debe estar lo de agregar correos ajenos
    const wrapperCorreosAjenos = document.getElementById('wrapperCorreosAjenosSection');
    if (wrapperCorreosAjenos) {
        if (rAct === 'administrador' || rAct === 'tipo 1' || rAct === 'admin') {
            wrapperCorreosAjenos.style.display = 'none';
        } else {
            wrapperCorreosAjenos.style.display = 'block';
        }
    }

    // Asegurar compactación de actualización al inicio
    const containerActualizar = document.getElementById('containerFormActualizar');
    if (containerActualizar) {
        containerActualizar.style.display = 'none';
        containerActualizar.classList.remove('expanded');
    }

    // Mostrar u ocultar menú sucursales y columnas específicas de Consultor
    const menuSucursales = document.getElementById('menu-sucursales');
    const displayConsultor = (rAct === 'consultor') ? 'table-cell' : 'none';
    document.querySelectorAll('.col-empresa-consultor').forEach(el => {
        el.style.display = displayConsultor;
    });

    if (menuSucursales) {
        if (rAct === 'consultor') {
            menuSucursales.style.display = 'block';
            cargarSucursalesConsultor();
        } else {
            menuSucursales.style.display = 'none';
        }
    }
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
    const rAct = rolActualSesion.toLowerCase();
    const displayConsultor = (rAct === 'consultor') ? 'table-cell' : 'none';

    arregloDatos.forEach((d, index) => {
        let color = d.color_calculado; let txt = d.estatus_texto;
        
        let btnVerODescarga = `<a href="${base_url}/controllers/documento_descargar.php?archivo=${d.nombre_archivo_fisico}&token_seguro=${empresaCod}" target="_blank" class="btn-action btn-view" onclick="registrarAuditoriaLectura(${d.id})"><i class="fas fa-eye"></i> Ver</a>`;
        let botonActualizar = `<button class="btn-action btn-update" id="btn-up-${index}"><i class="fas fa-sync-alt"></i> Actualizar</button>`;
        let botonSuspender = parseInt(d.estatus) === 0 ? `<button class="btn-action btn-activate" onclick="suspenderArchivo(${d.id})">Desarchivar</button>` : `<button class="btn-action btn-suspend" onclick="suspenderArchivo(${d.id})">Archivar</button>`;
        let botonHistorial = `<button class="btn-action btn-history" onclick="abrirHistorialDocumento(${d.id}, '${d.tipo_doc}')"><i class="fas fa-clock-rotate-left"></i> Historial</button>`;
        let badgeColorClass = color === 'green' ? 'green' : (color === 'yellow' ? 'orange' : (color === 'orange-strong' ? 'orange-strong' : (color === 'gray' ? 'neutral' : 'red')));

        // Trazabilidad y accesos
        const subidoText = d.subido_por ? d.subido_por : 'Sistema';
        const vistoText = d.visto_por ? d.visto_por : 'Nadie aún';
        const trazabilidadHtml = `
            <div style="font-size:0.8rem; color:#64748b; line-height: 1.4;">
                <div>Vence: <code>${d.fecha_vencimiento}</code> (<strong>${d.porcentaje_vida}</strong>)</div>
                <div>Subido por: <strong>${subidoText}</strong></div>
                <div><i class="fas fa-eye" style="color:#94a3b8;"></i> Visto por: <strong>${vistoText}</strong></div>
            </div>
        `;

        // Resolver nombre comercial de empresa/sucursal asociada al archivo
        const nombreEmpresa = mapaNombresEmpresas[d.empresa_cod] || d.empresa_cod;

        b.innerHTML += `<tr>
            <td><span class="semaphore ${color}"></span></td>
            <td><strong>${d.tipo_doc}</strong></td>
            <td>${d.nombre_limpio}</td>
            <td class="col-empresa-consultor" style="display: ${displayConsultor};"><strong>${nombreEmpresa}</strong></td>
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
                activarFlujoActualizar(d.tipo_doc || '', d.nombre_limpio || '', d.fecha_vencimiento || '', d.correos_adicionales || '');
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

function activarFlujoActualizar(tipoDoc, nombreActual, fVenc, correosAdicionales) {
    const containerSubida = document.getElementById('containerFormSubida');
    const containerActualizar = document.getElementById('containerFormActualizar');
    
    // Ocultar (compactar) formulario de subida normal
    if (containerSubida) containerSubida.classList.remove('expanded');
    
    // Rellenar datos en formulario de actualización
    document.getElementById('docTipo_edit').value = tipoDoc;
    document.getElementById('docNombre_edit').value = nombreActual;
    document.getElementById('docVencimiento_edit').value = fVenc && fVenc !== '0000-00-00' ? fVenc : '';
    document.getElementById('docMotivo_edit').value = '';

    // Cargar dinámicamente los correos adicionales registrados
    const containerEdit = document.getElementById('contenedorInputsCorreosAjenos_edit');
    if (containerEdit) {
        containerEdit.innerHTML = '';
        const arregloCorreos = correosAdicionales ? correosAdicionales.split(',') : [];
        if (arregloCorreos.length > 0) {
            arregloCorreos.forEach(email => {
                const div = document.createElement('div');
                div.className = 'correo-fila-edit';
                div.style.display = 'flex';
                div.style.gap = '10px';
                div.style.marginBottom = '8px';
                div.style.alignItems = 'center';
                div.innerHTML = `
                    <input type="email" class="input-correo-ajeno-edit" value="${email}" placeholder="ejemplo@correo.com" style="flex: 1; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; font-family: 'Outfit', sans-serif;">
                    <button type="button" class="btn-quitar-correo-fila-edit" style="padding: 10px 14px; border-radius: 8px; background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; cursor: pointer; font-weight: bold;" onclick="eliminarFilaCorreoEdit(this)"><i class="fas fa-trash-can"></i></button>
                `;
                containerEdit.appendChild(div);
            });
        } else {
            // Dejar una fila vacía
            containerEdit.innerHTML = `
                <div class="correo-fila-edit" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">
                    <input type="email" class="input-correo-ajeno-edit" placeholder="ejemplo@correo.com" style="flex: 1; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; font-family: 'Outfit', sans-serif;">
                    <button type="button" class="btn-quitar-correo-fila-edit" style="padding: 10px 14px; border-radius: 8px; background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; cursor: pointer; visibility: hidden; font-weight: bold;" onclick="eliminarFilaCorreoEdit(this)"><i class="fas fa-trash-can"></i></button>
                </div>
            `;
        }
        actualizarVisibilidadBotonesQuitarEdit();
    }

    // Ocultar sección de correos si es Administrador / Tipo 1
    const rAct = rolActualSesion.toLowerCase();
    const wrapperCorreosAjenosEdit = document.getElementById('wrapperCorreosAjenosSection_edit');
    if (wrapperCorreosAjenosEdit) {
        if (rAct === 'administrador' || rAct === 'tipo 1' || rAct === 'admin') {
            wrapperCorreosAjenosEdit.style.display = 'none';
        } else {
            wrapperCorreosAjenosEdit.style.display = 'block';
        }
    }

    // Mostrar (descompactar) formulario de actualización
    if (containerActualizar) {
        containerActualizar.style.display = 'block';
        // Forzar reflow para animación
        containerActualizar.offsetHeight;
        containerActualizar.classList.add('expanded');
        // Scroll automático
        containerActualizar.scrollIntoView({ behavior: 'smooth' });
    }
}

async function unificadoSubmitForm(e) {
    e.preventDefault(); 
    
    // 🚀 Serializar casillas de correos antes de submitir
    serializarCorreosAjenos();
    
    const formData = new FormData();
    formData.append('accion', 'subir_documento'); 
    formData.append('rol_ejecutor', rolActualSesion);
    formData.append('usuario_ejecutor', userName); 
    formData.append('empresa_cod', empresaCod);
    
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
    formData.append('es_actualizacion', 'no');
    formData.append('motivo', '');
    formData.append('archivo', document.getElementById('docFile').files[0]);
    formData.append('correos_adicionales', document.getElementById('docCorreosAdicionales').value);
    
    const r = await fetch(urlProcesador, { method: 'POST', body: formData }); 
    const res = await r.json(); 
    alert(res.message);
    
    if(res.status === 'success') { 
        limpiarFormularioCarga();
        cargarDocumentosCliente(); 
        cambiarVista('archivos');
    }
}

async function unificadoSubmitFormEdit(e) {
    e.preventDefault();
    
    // Serializar correos adicionales antes de enviar
    serializarCorreosAjenosEdit();
    
    const formData = new FormData();
    formData.append('accion', 'subir_documento');
    formData.append('rol_ejecutor', rolActualSesion);
    formData.append('usuario_ejecutor', userName);
    formData.append('empresa_cod', empresaCod);
    
    const ahora = new Date();
    const yyyy = ahora.getFullYear();
    const mm = String(ahora.getMonth() + 1).padStart(2, '0');
    const dd = String(ahora.getDate()).padStart(2, '0');
    const hh = String(ahora.getHours()).padStart(2, '0');
    const mi = String(ahora.getMinutes()).padStart(2, '0');
    const ss = String(ahora.getSeconds()).padStart(2, '0');
    const fechaLocal = `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
    
    formData.append('tipo_doc', document.getElementById('docTipo_edit').value.trim());
    formData.append('nombre_personalizado', document.getElementById('docNombre_edit').value.trim());
    formData.append('fecha_vencimiento', document.getElementById('docVencimiento_edit').value);
    formData.append('fecha_subida', fechaLocal);
    formData.append('es_actualizacion', 'si');
    formData.append('motivo', document.getElementById('docMotivo_edit').value.trim());
    formData.append('archivo', document.getElementById('docFile_edit').files[0]);
    formData.append('correos_adicionales', document.getElementById('docCorreosAdicionales_edit').value);
    
    const r = await fetch(urlProcesador, { method: 'POST', body: formData });
    const res = await r.json();
    alert(res.message);
    
    if(res.status === 'success') {
        cancelarActualizacionExpediente();
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
    const rAct = rolActualSesion.toLowerCase();
    const displayConsultor = (rAct === 'consultor') ? 'table-cell' : 'none';

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
        
        // Resolver nombre comercial de empresa/sucursal asociada
        const partes = u.cod.split('/');
        let codEmpresa = partes[0];
        if (partes.length >= 2 && (partes[1].startsWith('SUC-') || partes[1].startsWith('SU'))) {
            codEmpresa = partes[0] + '/' + partes[1];
        }
        const nombreEmpresa = mapaNombresEmpresas[codEmpresa] || codEmpresa;

        b.innerHTML += `<tr>
            <td><code>${u.cod}</code></td>
            <td><strong>${u.nombre}</strong></td>
            <td class="col-empresa-consultor" style="display: ${displayConsultor};"><strong>${nombreEmpresa}</strong></td>
            <td>${contactos}</td>
            <td><span class="badge role">${u.rol || 'No asignado'}</span></td>
            <td>${estatusBadge}</td>
        </tr>`; 
    }); 
}

async function cargarUsuariosCliente() { 
    const r = await fetch(urlProcesador, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'listar_usuarios', empresa_cod: empresaCod, rol_ejecutor: rolActualSesion }) }); 
    const res = await r.json();
    if(res.status === 'success') { 
        cacheUsuarios = res.data;
        
        // Poblar mapa de nombres de empresas/sucursales para resolución instantánea
        mapaNombresEmpresas = {};
        res.data.forEach(u => {
            mapaNombresEmpresas[u.cod] = u.nombre;
        });
        
        renderizarTablaUsuarios();
        
        // Sincronizar carga de documentos para asegurar resolución de nombres
        cargarDocumentosCliente();
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
if(document.getElementById('updateDocForm')) document.getElementById('updateDocForm').addEventListener('submit', unificadoSubmitFormEdit);

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
        window.scrollTo({ top: 0, behavior: 'smooth' }); // 🚀 Redireccionar pantalla suavemente hacia arriba
        
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

// =================================================================
// 🚀 GESTIÓN DINÁMICA DE CASILLAS DE CORREOS ADICIONALES (ILIMITADOS)
// =================================================================
function agregarCasillaCorreo() {
    const container = document.getElementById('contenedorInputsCorreosAjenos');
    if (!container) return;
    
    const div = document.createElement('div');
    div.className = 'correo-fila';
    div.style.display = 'flex';
    div.style.gap = '10px';
    div.style.marginBottom = '8px';
    div.style.alignItems = 'center';
    
    div.innerHTML = `
        <input type="email" class="input-correo-ajeno" placeholder="ejemplo@correo.com" style="flex: 1; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; font-family: 'Outfit', sans-serif;">
        <button type="button" class="btn-quitar-correo-fila" style="padding: 10px 14px; border-radius: 8px; background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; cursor: pointer; font-weight: bold;" onclick="eliminarFilaCorreo(this)"><i class="fas fa-trash-can"></i></button>
    `;
    container.appendChild(div);
    actualizarVisibilidadBotonesQuitar();
}

function eliminarFilaCorreo(btn) {
    const fila = btn.closest('.correo-fila');
    if (fila) {
        fila.remove();
        actualizarVisibilidadBotonesQuitar();
    }
}

function actualizarVisibilidadBotonesQuitar() {
    const filas = document.querySelectorAll('.correo-fila');
    filas.forEach(f => {
        const btn = f.querySelector('.btn-quitar-correo-fila');
        if (btn) {
            btn.style.visibility = (filas.length > 1) ? 'visible' : 'hidden';
        }
    });
}

function serializarCorreosAjenos() {
    const inputs = document.querySelectorAll('.input-correo-ajeno');
    const correosValidos = [];
    inputs.forEach(input => {
        const email = input.value.trim();
        if (email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (regex.test(email)) {
                correosValidos.push(email);
            }
        }
    });
    const inputOculto = document.getElementById('docCorreosAdicionales');
    if (inputOculto) {
        inputOculto.value = correosValidos.join(',');
    }
}

function limpiarFormularioCarga() {
    const form = document.getElementById('uploadDocForm');
    if (form) form.reset();
    
    const container = document.getElementById('contenedorInputsCorreosAjenos');
    if (container) {
        container.innerHTML = `
            <div class="correo-fila" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">
                <input type="email" class="input-correo-ajeno" placeholder="ejemplo@correo.com" style="flex: 1; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; font-family: 'Outfit', sans-serif;">
                <button type="button" class="btn-quitar-correo-fila" style="padding: 10px 14px; border-radius: 8px; background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; cursor: pointer; visibility: hidden; font-weight: bold;" onclick="eliminarFilaCorreo(this)"><i class="fas fa-trash-can"></i></button>
            </div>
        `;
    }
    const inputOculto = document.getElementById('docCorreosAdicionales');
    if (inputOculto) inputOculto.value = '';
    actualizarVisibilidadBotonesQuitar();
}

function restaurarFormularioSubidaCompleto() {
    cancelarActualizacionExpediente();
}

function cancelarActualizacionExpediente() {
    const containerActualizar = document.getElementById('containerFormActualizar');
    const formEdit = document.getElementById('updateDocForm');
    
    // 1. Ocultar (compactar) formulario de actualización
    if (containerActualizar) containerActualizar.classList.remove('expanded');
    
    // 2. Esperar 400ms para limpiar inputs y apagar visualización
    setTimeout(() => {
        if (containerActualizar) containerActualizar.style.display = 'none';
        if (formEdit) formEdit.reset();
    }, 400);
}

// =================================================================
// 🚀 GESTIÓN DE CASILLAS DE CORREOS PARA EL FORMULARIO DE EDICIÓN
// =================================================================
function agregarCasillaCorreoEdit() {
    const container = document.getElementById('contenedorInputsCorreosAjenos_edit');
    if (!container) return;
    
    const div = document.createElement('div');
    div.className = 'correo-fila-edit';
    div.style.display = 'flex';
    div.style.gap = '10px';
    div.style.marginBottom = '8px';
    div.style.alignItems = 'center';
    
    div.innerHTML = `
        <input type="email" class="input-correo-ajeno-edit" placeholder="ejemplo@correo.com" style="flex: 1; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; font-family: 'Outfit', sans-serif;">
        <button type="button" class="btn-quitar-correo-fila-edit" style="padding: 10px 14px; border-radius: 8px; background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; cursor: pointer; font-weight: bold;" onclick="eliminarFilaCorreoEdit(this)"><i class="fas fa-trash-can"></i></button>
    `;
    container.appendChild(div);
    actualizarVisibilidadBotonesQuitarEdit();
}

function eliminarFilaCorreoEdit(btn) {
    const fila = btn.closest('.correo-fila-edit');
    if (fila) {
        fila.remove();
        actualizarVisibilidadBotonesQuitarEdit();
    }
}

function actualizarVisibilidadBotonesQuitarEdit() {
    const filas = document.querySelectorAll('.correo-fila-edit');
    filas.forEach(f => {
        const btn = f.querySelector('.btn-quitar-correo-fila-edit');
        if (btn) {
            btn.style.visibility = (filas.length > 1) ? 'visible' : 'hidden';
        }
    });
}

function serializarCorreosAjenosEdit() {
    const inputs = document.querySelectorAll('.input-correo-ajeno-edit');
    const correosValidos = [];
    inputs.forEach(input => {
        const email = input.value.trim();
        if (email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (regex.test(email)) {
                correosValidos.push(email);
            }
        }
    });
    const inputOculto = document.getElementById('docCorreosAdicionales_edit');
    if (inputOculto) {
        inputOculto.value = correosValidos.join(',');
    }
}

// =================================================================
// 🚀 GESTIÓN DE SUB-EMPRESAS / SUCURSALES (CONSULTOR)
// =================================================================
async function cargarSucursalesConsultor() {
    const tableBody = document.getElementById('tablaSucursalesBody');
    if (!tableBody) return;

    try {
        const r = await fetch(urlProcesador, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'listar_sucursales_consultor', consultor_cod: empresaCod })
        });
        const res = await r.json();
        if (res.status === 'success') {
            renderizarTablaSucursales(res.data);
        }
    } catch (e) {
        console.error("Error al cargar sucursales:", e);
    }

    // Al recargar sucursales, también recargamos el catálogo de Responsables Nacionales
    cargarResponsablesNacionalesSelect();
}

async function cargarResponsablesNacionalesSelect() {
    const select = document.getElementById('sucResponsableNacional');
    if (!select) return;

    try {
        const r = await fetch(urlProcesador, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'listar_responsables_nacionales_consultor', consultor_cod: empresaCod })
        });
        const res = await r.json();
        if (res.status === 'success') {
            const valorActual = select.value;
            select.innerHTML = '<option value="NINGUNO">-- Ninguno / Asignar más tarde --</option>';
            res.data.forEach(rn => {
                select.innerHTML += `<option value="${rn.email}">${rn.nombre} (${rn.email})</option>`;
            });
            select.value = valorActual;
        }
    } catch (e) {
        console.error("Error al cargar responsables nacionales:", e);
    }
}

function renderizarTablaSucursales(arreglo) {
    const tableBody = document.getElementById('tablaSucursalesBody');
    if (!tableBody) return;
    
    if (arreglo.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:20px; color:#64748b;">No existen sucursales registradas aún.</td></tr>`;
        return;
    }

    tableBody.innerHTML = '';
    arreglo.forEach(s => {
        const txtEstatus = parseInt(s.activo) === 1 ? 'Activa' : 'Suspendida';
        const classEstatus = parseInt(s.activo) === 1 ? 'green' : 'red';
        const labelBtnEstatus = parseInt(s.activo) === 1 ? 'Suspender' : 'Activar';
        const classBtnEstatus = parseInt(s.activo) === 1 ? 'btn-suspend' : 'btn-activate';
        
        tableBody.innerHTML += `
            <tr>
                <td style="font-family: monospace; font-weight:600;">${s.cod}</td>
                <td><strong>${s.nombre}</strong></td>
                <td>${s.email}</td>
                <td>${s.telefono_principal || 'N/A'}</td>
                <td>${s.direccion || 'N/A'}</td>
                <td><span class="badge ${classEstatus}">${txtEstatus}</span></td>
                <td>
                    <button class="btn-action ${classBtnEstatus}" onclick="alternarEstatusSucursal(${s.id}, ${s.activo})">${labelBtnEstatus}</button>
                </td>
            </tr>
        `;
    });
}

async function alternarEstatusSucursal(id, activoActual) {
    const nuevoActivo = parseInt(activoActual) === 1 ? 0 : 1;
    const confirmMsg = nuevoActivo === 0 
        ? "¿Estás seguro de que deseas suspender temporalmente la licencia de esta sucursal? Sus colaboradores no podrán acceder." 
        : "¿Deseas reactivar la licencia de esta sucursal?";

    if (!confirm(confirmMsg)) return;

    try {
        const r = await fetch(urlProcesador, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'estatus_sucursal_consultor', id: id, activo: nuevoActivo })
        });
        const res = await r.json();
        alert(res.message);
        if (res.status === 'success') {
            cargarSucursalesConsultor();
        }
    } catch (e) {
        console.error("Error al cambiar estatus de sucursal:", e);
    }
}

// Event listener para registrar sucursal
if (document.getElementById('sucursalClienteForm')) {
    document.getElementById('sucursalClienteForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const pass = document.getElementById('sucPass').value.trim();
        const passErr = validarPasswordComplejidad(pass);
        if (passErr) {
            alert("Seguridad de Contraseña: " + passErr);
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'crear_sucursal_consultor');
        formData.append('rol_ejecutor', rolActualSesion);
        formData.append('consultor_cod', empresaCod);
        formData.append('nombre', document.getElementById('sucNombre').value.trim());
        formData.append('email', document.getElementById('sucEmail').value.trim());
        formData.append('telefono_principal', document.getElementById('sucTelPrincipal').value.trim());
        formData.append('telefono_adicional', document.getElementById('sucTelAdicional').value.trim());
        formData.append('coordenadas', document.getElementById('sucCoordenadas').value.trim());
        formData.append('direccion', document.getElementById('sucDireccion').value.trim());
        formData.append('pass', pass);
        formData.append('rn_email', document.getElementById('sucResponsableNacional').value);

        try {
            const r = await fetch(urlProcesador, { method: 'POST', body: formData });
            const res = await r.json();
            alert(res.message);
            if (res.status === 'success') {
                document.getElementById('sucursalClienteForm').reset();
                cargarSucursalesConsultor();
            }
        } catch (err) {
            console.error("Error al registrar sucursal:", err);
            alert("Ocurrió un error en el servidor al registrar la sucursal.");
        }
    });
}

// 🚀 Exponer funciones al objeto global window para asegurar compatibilidad inline
window.agregarCasillaCorreo = agregarCasillaCorreo;
window.eliminarFilaCorreo = eliminarFilaCorreo;
window.agregarCasillaCorreoEdit = agregarCasillaCorreoEdit;
window.eliminarFilaCorreoEdit = eliminarFilaCorreoEdit;
window.limpiarFormularioCarga = limpiarFormularioCarga;
window.cancelarActualizacionExpediente = cancelarActualizacionExpediente;
window.alternarEstatusSucursal = alternarEstatusSucursal;

// Inicializar visibilidad de filas únicas al cargar
actualizarVisibilidadBotonesQuitar();

// Lógica del menú hamburguesa responsivo para dispositivos móviles
document.addEventListener("DOMContentLoaded", () => {
    const btnToggle = document.getElementById('btnMenuToggle');
    const btnClose = document.getElementById('btnSidebarClose');
    const sidebar = document.querySelector('.sidebar');
    
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    if (btnToggle && sidebar && overlay) {
        btnToggle.addEventListener('click', () => {
            sidebar.classList.add('open');
            overlay.style.display = 'block';
            setTimeout(() => overlay.classList.add('active'), 10);
        });
    }

    const cerrarMenu = () => {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => overlay.style.display = 'none', 300);
        }
    };

    if (btnClose) btnClose.addEventListener('click', cerrarMenu);
    if (overlay) overlay.addEventListener('click', cerrarMenu);

    // Cerrar menú al seleccionar pestaña en móvil
    document.querySelectorAll('.menu-items li').forEach(item => {
        item.addEventListener('click', cerrarMenu);
    });
});

function cerrarSesion() { localStorage.clear(); window.location.replace('login.html'); }
