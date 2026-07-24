// 🚀 DETECCIÓN DINÁMICA DE ENTORNO (LOCAL VS PRODUCCIÓN)
const base_url = (() => {
    let subFolder = '';
    const pathParts = window.location.pathname.split('/');
    const publicIndex = pathParts.indexOf('public');
    if (publicIndex > 0) {
        subFolder = '/' + pathParts.slice(1, publicIndex).join('/');
    } else {
        const isLocal = ['localhost', '127.0.0.1'].includes(window.location.hostname) || window.location.hostname.startsWith('192.168.');
        if (isLocal && pathParts.length > 1 && pathParts[1] !== '') {
            subFolder = '/' + pathParts[1];
        }
    }
    return window.location.origin + subFolder;
})();
const urlProcesador  = `${base_url}/controllers/cliente_procesar.php`;
const empresaCod      = localStorage.getItem('cliente_sesion_id'); 
const userName        = localStorage.getItem('cliente_sesion_nombre');
const rolActualSesion = localStorage.getItem('cliente_sesion_rol'); 
let clienteMap = null;
let clienteMarker = null;
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
    aplicarRestrictionsMatriz(); cargarUsuariosCliente(); cargarDocumentosCliente(); cargarLogoEmpresa(); cargarMisNodosSelect();

    // Inicializar listener para agregar correos de alerta dinámicos
    const btnAdd = document.getElementById('btnAddEmailAlerta');
    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            agregarInputEmail('');
        });
    }

    // Vincular input de correo principal
    const inputPrincipal = document.getElementById('docEmailPrincipal');
    if (inputPrincipal) {
        inputPrincipal.addEventListener('input', renderizarEmailsDinamicos);
    }
});

function renderizarEmailsDinamicos() {
    const principal = document.getElementById('docEmailPrincipal') ? document.getElementById('docEmailPrincipal').value.trim() : '';
    const emails = [];
    if (principal) emails.push(principal);
    
    const container = document.getElementById('containerEmailsAlerta');
    if (container) {
        const inputs = container.querySelectorAll('.email-adicional-input');
        inputs.forEach(input => {
            const val = input.value.trim();
            if (val) emails.push(val);
        });
    }
    
    document.getElementById('docCorreosAlerta').value = emails.join(',');
}

function agregarInputEmail(valor = '') {
    const container = document.getElementById('containerEmailsAlerta');
    if (!container) return;
    
    const count = container.querySelectorAll('.email-group-wrapper').length;
    if (count >= 5) {
        alert("Límite alcanzado: Máximo 5 correos de alerta extras.");
        return;
    }
    
    const wrapper = document.createElement('div');
    wrapper.className = 'email-group-wrapper';
    wrapper.style.display = 'flex';
    wrapper.style.alignItems = 'center';
    wrapper.style.gap = '10px';
    wrapper.style.width = '100%';
    
    const input = document.createElement('input');
    input.type = 'email';
    input.placeholder = 'correo_extra' + (count + 1) + '@ejemplo.com';
    input.className = 'email-adicional-input';
    input.value = valor;
    input.style.flex = '1';
    input.style.padding = '10px';
    input.style.border = '1px solid #cbd5e1';
    input.style.borderRadius = '8px';
    input.style.background = '#f8fafc';
    input.style.outline = 'none';
    
    input.addEventListener('input', renderizarEmailsDinamicos);
    
    const btnDel = document.createElement('button');
    btnDel.type = 'button';
    btnDel.className = 'btn-action suspend';
    btnDel.style.padding = '10px';
    btnDel.style.borderRadius = '8px';
    btnDel.style.display = 'flex';
    btnDel.style.alignItems = 'center';
    btnDel.style.justifyContent = 'center';
    btnDel.style.width = 'auto';
    btnDel.style.minWidth = 'auto';
    btnDel.style.margin = '0';
    btnDel.innerHTML = '<i class="fas fa-trash-can"></i>';
    btnDel.addEventListener('click', () => {
        wrapper.remove();
        renderizarEmailsDinamicos();
    });
    
    wrapper.appendChild(input);
    wrapper.appendChild(btnDel);
    container.appendChild(wrapper);
    
    renderizarEmailsDinamicos();
}

function refrescarInputsCorreosDinamicos(correosStr = '') {
    const principalInput = document.getElementById('docEmailPrincipal');
    const container = document.getElementById('containerEmailsAlerta');
    if (container) container.innerHTML = '';
    
    const emails = correosStr ? correosStr.split(',') : [];
    
    if (emails.length > 0) {
        if (principalInput) principalInput.value = emails[0].trim();
        for (let i = 1; i < emails.length; i++) {
            const emailExtra = emails[i].trim();
            if (emailExtra) {
                agregarInputEmail(emailExtra);
            }
        }
    } else {
        if (principalInput) principalInput.value = '';
    }
    
    renderizarEmailsDinamicos();
}

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

    // Personalizar textos de creación según rol (Consultor vs Nodos comunes)
    const tituloSeccion = document.getElementById('tituloSeccionPersonal');
    const menuPersonal = document.getElementById('menu-personal');
    const menuColaboradores = document.getElementById('menu-colaboradores');
    
    const wrapperUserRol = document.getElementById('wrapperUserRol');
    const wrapperUserEncargado = document.getElementById('wrapperUserEncargado');
    const lblUserNombre = document.getElementById('lblUserNombre');
    const userNombreInput = document.getElementById('userNombre');
    
    if (rAct === 'consultor') {
        if (menuPersonal) menuPersonal.style.display = 'block';
        if (menuColaboradores) menuColaboradores.style.display = 'block';
        
        if (tituloSeccion) tituloSeccion.innerHTML = `<i class="fas fa-building-circle-check" style="color:var(--sidebar-active);"></i> Registrar Nueva Empresa / Sucursal`;
        if (lblUserNombre) lblUserNombre.innerText = "Nombre de la Empresa / Sucursal";
        if (userNombreInput) userNombreInput.placeholder = "Ej: Porsche Santa Fe";
        if (wrapperUserEncargado) wrapperUserEncargado.style.display = 'block';
        if (wrapperUserRol) wrapperUserRol.style.display = 'none';
        
        cargarColabEmpresasSelect();
    } else {
        if (menuPersonal) menuPersonal.style.display = 'none';
        if (menuColaboradores) menuColaboradores.style.display = 'block';
        
        if (tituloSeccion) tituloSeccion.innerHTML = `<i class="fas fa-id-card-clip" style="color:var(--sidebar-active);"></i> Registrar Nuevo Nodo Operativo`;
        if (lblUserNombre) lblUserNombre.innerText = "Nombre Completo del Encargado";
        if (userNombreInput) userNombreInput.placeholder = "Ej: Ing. Carlos Pérez";
        if (wrapperUserEncargado) wrapperUserEncargado.style.display = 'none';
        if (wrapperUserRol) wrapperUserRol.style.display = 'block';
    }

    // Mostrar campos de empresa si es Consultor
    const wrapperEmpresa = document.getElementById('wrapperCamposEmpresa');
    if (rAct === 'consultor' && wrapperEmpresa) {
        wrapperEmpresa.style.display = 'block';
        initClienteMap();
    } else if (wrapperEmpresa) {
        wrapperEmpresa.style.display = 'none';
    }
}

function initClienteMap() {
    const mapDiv = document.getElementById('clienteMap');
    if (mapDiv && !clienteMap) {
        const defaultCoords = [19.432608, -99.133208];
        clienteMap = L.map('clienteMap').setView(defaultCoords, 13);
        L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 19,
            attribution: '© Google Maps'
        }).addTo(clienteMap);
        
        clienteMarker = L.marker(defaultCoords, { draggable: true }).addTo(clienteMap);
        
        clienteMarker.on('dragend', function() {
            const pos = clienteMarker.getLatLng();
            document.getElementById('empresaCoordenadas').value = `${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}`;
        });
        
        clienteMap.on('click', function(e) {
            clienteMarker.setLatLng(e.latlng);
            document.getElementById('empresaCoordenadas').value = `${e.latlng.lat.toFixed(6)}, ${e.latlng.lng.toFixed(6)}`;
        });
        
        document.getElementById('empresaCoordenadas').addEventListener('input', function() {
            const val = this.value.split(',');
            if (val.length === 2) {
                const lat = parseFloat(val[0].trim());
                const lng = parseFloat(val[1].trim());
                if (!isNaN(lat) && !isNaN(lng)) {
                    const latlng = [lat, lng];
                    clienteMarker.setLatLng(latlng);
                    clienteMap.setView(latlng, 15);
                }
            }
        });
        
        setTimeout(() => {
            clienteMap.invalidateSize();
        }, 300);
    }
}

async function cargarMisNodosSelect() {
    const rAct = rolActualSesion.toLowerCase();
    const selectEmpresa = document.getElementById('docEmpresaSelect');
    const wrapper = document.getElementById('wrapperSeleccionarEmpresaDoc');
    
    if (rAct === 'consultor' || rAct === 'responsable nacional' || rAct === 'responsable_nacional') {
        if (wrapper) wrapper.style.display = 'block';
        if (!selectEmpresa) return;
        
        try {
            const r = await fetch(urlProcesador, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'listar_mis_nodos', empresa_cod: empresaCod })
            });
            const res = await r.json();
            if (res.status === 'success') {
                selectEmpresa.innerHTML = '';
                res.data.forEach(nodo => {
                    const selectedAttr = nodo.cod === empresaCod ? 'selected' : '';
                    selectEmpresa.innerHTML += `<option value="${nodo.cod}" ${selectedAttr}>${nodo.nombre} (${nodo.cod})</option>`;
                });
            }
        } catch (e) {
            console.error("Error al cargar la lista de nodos:", e);
        }
    } else {
        if (wrapper) wrapper.style.display = 'none';
    }
}

async function cargarColabEmpresasSelect() {
    const rAct = rolActualSesion.toLowerCase();
    const selectEmpresa = document.getElementById('colabEmpresaSelect');
    const wrapper = document.getElementById('wrapperAsignarEmpresaColab');
    if (rAct === 'consultor') {
        if (wrapper) wrapper.style.display = 'block';
        if (!selectEmpresa) return;
        try {
            const r = await fetch(urlProcesador, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'listar_mis_nodos', empresa_cod: empresaCod })
            });
            const res = await r.json();
            if (res.status === 'success') {
                selectEmpresa.innerHTML = '';
                res.data.forEach(nodo => {
                    selectEmpresa.innerHTML += `<option value="${nodo.cod}">${nodo.nombre} (${nodo.cod})</option>`;
                });
            }
        } catch (e) {
            console.error("Error al cargar la lista de empresas para colaboradores:", e);
        }
    } else {
        if (wrapper) wrapper.style.display = 'none';
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
            <td><code>${d.empresa_cod}</code></td>
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
                activarFlujoActualizar(d.tipo_doc, d.nombre_limpio, d.fecha_vencimiento, mot.trim(), d.notificar_correos || '', d.empresa_cod);
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

function activarFlujoActualizar(tipoDoc, nombreActual, fVenc, mot, correos, targetEmpresa) {
    document.getElementById('docTipo').value = tipoDoc;
    document.getElementById('docNombre').value = nombreActual + " (" + mot + ")";
    document.getElementById('docVencimiento').value = fVenc;
    document.getElementById('docCorreosAlerta').value = correos || '';
    refrescarInputsCorreosDinamicos(correos || '');
    document.getElementById('esActualizacion').value = "si";
    document.getElementById('docMotivo').value = mot;
    
    const selectEmpresa = document.getElementById('docEmpresaSelect');
    if (selectEmpresa && targetEmpresa) {
        selectEmpresa.value = targetEmpresa;
    }
    
    document.getElementById('containerFormSubida').scrollIntoView({ behavior: 'smooth' });
}

async function unificadoSubmitForm(e) {
    e.preventDefault(); const formData = new FormData();
    formData.append('accion', 'subir_documento'); formData.append('rol_ejecutor', rolActualSesion);
    formData.append('usuario_ejecutor', userName);
    
    let targetEmpresaCod = empresaCod;
    const selectEmpresa = document.getElementById('docEmpresaSelect');
    if (selectEmpresa && selectEmpresa.value) {
        targetEmpresaCod = selectEmpresa.value;
    }
    formData.append('empresa_cod', targetEmpresaCod);
    
    // Capturar fecha y hora local del instante real sin desfases UTC
    const ahora = new Date();
    const yyyy = ahora.getFullYear();
    const mm = String(ahora.getMonth() + 1).padStart(2, '0');
    const dd = String(ahora.getDate()).padStart(2, '0');
    const hh = String(ahora.getHours()).padStart(2, '0');
    const mi = String(ahora.getMinutes()).padStart(2, '0');
    const ss = String(ahora.getSeconds()).padStart(2, '0');
    const fechaLocal = `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
    
    // Forzar renderización final para asegurar que docCorreosAlerta hidden input contenga los correos correctos
    renderizarEmailsDinamicos();
    
    formData.append('tipo_doc', document.getElementById('docTipo').value.trim());
    formData.append('nombre_personalizado', document.getElementById('docNombre').value.trim());
    formData.append('fecha_vencimiento', document.getElementById('docVencimiento').value);
    formData.append('fecha_subida', fechaLocal);
    formData.append('es_actualizacion', document.getElementById('esActualizacion').value);
    formData.append('motivo', document.getElementById('docMotivo').value);
    formData.append('notificar_correos', document.getElementById('docCorreosAlerta').value.trim());
    formData.append('archivo', document.getElementById('docFile').files[0]);
    
    const r = await fetch(urlProcesador, { method: 'POST', body: formData }); const res = await r.json(); alert(res.message);
    if(res.status === 'success') { 
        document.getElementById('uploadDocForm').reset(); 
        document.getElementById('esActualizacion').value = "no";
        document.getElementById('docMotivo').value = "";
        refrescarInputsCorreosDinamicos('');
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
    
    const thIndex = { 'color': 0, 'categoria': 1, 'empresa': 2, 'nombre': 3, 'vencimiento': 4, 'estatus': 5 }[columna];
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
        } else if (columna === 'empresa') {
            valA = (a.empresa_cod || '').toString().toLowerCase();
            valB = (b.empresa_cod || '').toString().toLowerCase();
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
        if (u.director_email) {
            contactos += `<div><span style="font-weight:600;"><i class="fas fa-user-tie"></i> Director:</span> ${u.director_email}</div>`;
        }
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
        
        let nombreMostrado = `<strong>${u.nombre}</strong>`;
        if (u.encargado) {
            nombreMostrado += `<div style="font-size:0.85rem; color:#64748b; margin-top:2px;"><i class="fas fa-user-tie" style="color:#94a3b8;"></i> Encargado: <strong>${u.encargado}</strong></div>`;
        }

        b.innerHTML += `<tr>
            <td><code>${u.cod}</code></td>
            <td>${nombreMostrado}</td>
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

        const formData = new FormData();
        formData.append('accion', 'crear_usuario_operativo');
        formData.append('rol_ejecutor', rolActualSesion);
        formData.append('nombre', document.getElementById('userNombre').value.trim());
        formData.append('rol', (rolActualSesion.toLowerCase() === 'consultor') ? 'Tipo 1' : document.getElementById('userRol').value);
        formData.append('email', document.getElementById('userEmail').value.trim());
        formData.append('email_adicional', document.getElementById('userEmailPersonal').value.trim());
        formData.append('telefono_principal', document.getElementById('userTelEmpresa').value.trim());
        formData.append('telefono_adicional', document.getElementById('userTelPersonal').value.trim());
        formData.append('pass', pass);
        formData.append('empresa_cod', empresaCod);
        formData.append('encargado', (rolActualSesion.toLowerCase() === 'consultor') ? document.getElementById('userEncargado').value.trim() : '');

        if (rolActualSesion.toLowerCase() === 'consultor') {
            formData.append('direccion', document.getElementById('empresaDireccion').value.trim());
            formData.append('coordenadas', document.getElementById('empresaCoordenadas').value.trim());
            formData.append('director_email', document.getElementById('empresaDirectorEmail').value.trim());
            const logoFile = document.getElementById('empresaLogo').files[0];
            if (logoFile) {
                formData.append('logo', logoFile);
            }
        }

        const r = await fetch(urlProcesador, {
            method: 'POST',
            body: formData
        });
        const res = await r.json();
        if(res.status === 'success') {
            alert("¡Nodo / Empresa registrado exitosamente!");
            document.getElementById('usuarioClienteForm').reset();
            const wrapperEmpresa = document.getElementById('wrapperCamposEmpresa');
            if (wrapperEmpresa && rolActualSesion.toLowerCase() === 'consultor') {
                wrapperEmpresa.style.display = 'block'; // Asegurar que permanezca visible
                if (clienteMap) {
                    // Reset marker to default
                    const defaultCoords = [19.432608, -99.133208];
                    clienteMarker.setLatLng(defaultCoords);
                    clienteMap.setView(defaultCoords, 13);
                }
            }
            cargarUsuariosCliente();
            cargarColabEmpresasSelect();
            cargarMisNodosSelect();
        } else {
            alert("Error: " + res.message);
        }
    });
}

if (document.getElementById('colaboradorForm')) {
    document.getElementById('colaboradorForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const pass = document.getElementById('colabPass').value.trim();
        const passErr = validarPasswordComplejidad(pass);
        if (passErr) {
            alert("Seguridad de Contraseña: " + passErr);
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'crear_usuario_operativo');
        formData.append('rol_ejecutor', rolActualSesion);
        formData.append('nombre', document.getElementById('colabNombre').value.trim());
        formData.append('rol', document.getElementById('colabRol').value);
        formData.append('email', document.getElementById('colabEmail').value.trim());
        formData.append('email_adicional', document.getElementById('colabEmailPersonal').value.trim());
        formData.append('telefono_principal', document.getElementById('colabTelEmpresa').value.trim());
        formData.append('telefono_adicional', document.getElementById('colabTelPersonal').value.trim());
        formData.append('pass', pass);
        
        let targetEmpresa = empresaCod;
        const selectEmpresa = document.getElementById('colabEmpresaSelect');
        if (rolActualSesion.toLowerCase() === 'consultor' && selectEmpresa && selectEmpresa.value) {
            targetEmpresa = selectEmpresa.value;
        }
        formData.append('empresa_cod', targetEmpresa);

        const r = await fetch(urlProcesador, {
            method: 'POST',
            body: formData
        });
        const res = await r.json();
        if(res.status === 'success') {
            alert("¡Colaborador registrado exitosamente!");
            document.getElementById('colaboradorForm').reset();
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

    if (vista === 'personal') {
        setTimeout(() => { if (clienteMap) clienteMap.invalidateSize(); }, 150);
    }

    // Cerrar menú móvil si está abierto
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.remove('mobile-expanded');
    }
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

async function cerrarSesion() {
    try {
        await fetch(`${base_url}/controllers/logout.php`);
    } catch(e) {}
    localStorage.clear();
    window.location.replace('login.html');
}

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('mobile-expanded');
    }
}

window.addEventListener('load', () => {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.style.opacity = '0';
        preloader.style.visibility = 'hidden';
        setTimeout(() => { preloader.remove(); }, 400);
    }
});
