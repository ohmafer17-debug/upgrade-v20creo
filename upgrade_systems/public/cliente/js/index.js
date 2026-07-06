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
let listadoCategoriasDinamicas = ["Programa Interno de PC", "Dictamen Estructural", "Dictamen Eléctrico", "Dictamen de Gas", "Póliza de Seguro", "Constancia de Simulacro"];

document.addEventListener("DOMContentLoaded", () => {
    if (document.getElementById('nombreEmpresa')) document.getElementById('nombreEmpresa').innerText = empresaCod;
    if (document.getElementById('containerMetaUsuario')) document.getElementById('containerMetaUsuario').innerHTML = `Usuario: <strong>${userName}</strong> | Rango: <span class="badge role">${rolActualSesion}</span>`;
    inyectarFechaHoraActualFormulario(); aplicarRestrictionsMatriz(); cargarUsuariosCliente(); cargarDocumentosCliente(); cargarLogoEmpresa();
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

function inyectarFechaHoraActualFormulario() {
    const ahora = new Date();
    const campo = document.getElementById('docFechaSubidaManual');
    if(campo) campo.value = ahora.toISOString().slice(0, 19).replace('T', ' ');
}

function evaluarCategoriaOtro(valor) {
    const wrapperExtra = document.getElementById('wrapperCategoriaExtra'); const wrapperSubida = document.getElementById('wrapperFechaSubidaManual');
    const inputExtra = document.getElementById('docTipoExtra'); const inputSubida = document.getElementById('docFechaSubidaManual');
    inyectarFechaHoraActualFormulario();
    if (valor === 'OTRO') { wrapperExtra.style.display = "flex"; wrapperSubida.style.display = "flex"; inputExtra.required = true; inputSubida.required = true; inputExtra.focus(); } 
    else { wrapperExtra.style.display = "none"; wrapperSubida.style.display = "none"; inputExtra.required = false; inputSubida.required = false; inputExtra.value = ""; inputSubida.value = ""; verificarSiExisteDocumento(valor); }
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
        cacheDocumentos = res.data; res.data.forEach(d => { if(!listadoCategoriasDinamicas.includes(d.tipo_doc)) listadoCategoriasDinamicas.push(d.tipo_doc); });
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
        let color = d.color_calculado; let txt = d.estatus_texto; let porcentaje = d.porcentaje_vida;
        
        // 🚀 RUTA ABSOLUTA DINÁMICA
        let btnVerODescarga = `<a href="${base_url}/controllers/documento_descargar.php?archivo=${d.nombre_archivo_fisico}&token_seguro=${empresaCod}" target="_blank" class="btn-action btn-view" onclick="registrarAuditoriaLectura(${d.id})"><i class="fas fa-eye"></i> Ver</a>`;
        let botonActualizar = `<button class="btn-action btn-update" id="btn-up-${index}"><i class="fas fa-sync-alt"></i> Actualizar</button>`;
        let botonSuspender = parseInt(d.estatus) === 0 ? `<button class="btn-action btn-activate" onclick="suspenderArchivo(${d.id})">Desarchivar</button>` : `<button class="btn-action btn-suspend" onclick="suspenderArchivo(${d.id})">Archivar</button>`;
        let msgWhatsAppUrl = encodeURIComponent(`🚨 Alerta documento [${d.tipo_doc}] en estado [${txt}] con ${porcentaje}% consumido.`);
        let botonWhatsApp = `<a href="https://api.whatsapp.com/send?text=${msgWhatsAppUrl}" target="_blank" class="btn-action btn-whatsapp"><i class="fab fa-whatsapp"></i> Notificar</a>`;
        let badgeColorClass = color === 'green' ? 'green' : (color === 'yellow' ? 'orange' : (color === 'gray' ? 'neutral' : 'red'));

        b.innerHTML += `<tr><td><span class="semaphore ${color}"></span></td><td><strong>${d.tipo_doc}</strong></td><td>${d.nombre_personalizado}</td><td>Vence: <code>${d.fecha_vencimiento}</code></td><td><span class="badge ${badgeColorClass}">${txt}</span></td><td class="actions-cell"><div class="btn-action-group">${btnVerODescarga}${botonActualizar}</div><div class="btn-action-group">${botonSuspender}${botonWhatsApp}</div></td></tr>`;
        
        setTimeout(() => {
            const btn = document.getElementById(`btn-up-${index}`);
            if (btn) btn.onclick = () => {
                let mot = prompt("Escribe el motivo del cambio:");
                if (mot && mot.trim() !== "") activarFlujoActualizar(d.tipo_doc, d.nombre_personalizado, d.fecha_vencimiento, mot.trim());
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
    } catch (e) {
        console.error("Error al registrar auditoria de lectura:", e);
    }
}

async function suspenderArchivo(idDocumento) {
    if (!confirm("¿Esta seguro de cambiar el estado de archivo/inactivo para este documento?")) return;
    try {
        const r = await fetch(urlProcesador, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'suspender_documento',
                id_documento: idDocumento,
                rol_ejecutor: rolActualSesion
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
    document.getElementById('containerFormSubida').scrollIntoView({ behavior: 'smooth' });
}

async function unificadoSubmitForm(e) {
    e.preventDefault(); const formData = new FormData();
    formData.append('accion', 'subir_documento'); formData.append('rol_ejecutor', rolActualSesion);
    formData.append('usuario_ejecutor', userName); formData.append('empresa_cod', empresaCod);
    
    // Determinar tipo_doc real si es personalizado
    const tipoSeleccionado = document.getElementById('docTipo').value;
    if (tipoSeleccionado === 'OTRO') {
        formData.append('tipo_doc', document.getElementById('docTipoExtra').value.trim());
    } else {
        formData.append('tipo_doc', tipoSeleccionado);
    }
    
    formData.append('nombre_personalizado', document.getElementById('docNombre').value.trim());
    formData.append('fecha_vencimiento', document.getElementById('docVencimiento').value);
    formData.append('fecha_subida', document.getElementById('docFechaSubidaManual').value);
    formData.append('es_actualizacion', document.getElementById('esActualizacion').value);
    formData.append('archivo', document.getElementById('docFile').files[0]);
    
    const r = await fetch(urlProcesador, { method: 'POST', body: formData }); const res = await r.json(); alert(res.message);
    if(res.status === 'success') { 
        document.getElementById('uploadDocForm').reset(); 
        // Limpiar inputs extra
        document.getElementById('wrapperCategoriaExtra').style.display = "none";
        document.getElementById('wrapperFechaSubidaManual').style.display = "none";
        document.getElementById('esActualizacion').value = "no";
        cargarDocumentosCliente(); 
    }
}

function validarPasswordComplejidad(password) {
    if (password.length < 10) {
        return "La contraseña debe tener al menos 10 caracteres.";
    }
    if (!/[A-Z]/.test(password)) {
        return "La contraseña debe tener al menos una letra mayúscula.";
    }
    if (!/[a-z]/.test(password)) {
        return "La contraseña debe tener al menos una letra minúscula.";
    }
    if (!/[0-9]/.test(password)) {
        return "La contraseña debe tener al menos un número.";
    }
    if (!/[^a-zA-Z0-9]/.test(password)) {
        return "La contraseña debe tener al menos un carácter especial (ej. #, $, @, etc.).";
    }
    
    // Repetidos idénticos
    for (let i = 0; i < password.length - 3; i++) {
        if (password[i] === password[i+1] && password[i] === password[i+2] && password[i] === password[i+3]) {
            return "La contraseña no puede contener más de 3 caracteres idénticos consecutivos.";
        }
    }
    
    // Secuenciales consecutivos
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

async function cargarUsuariosCliente() { 
    const r = await fetch(urlProcesador, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'listar_usuarios', empresa_cod: empresaCod }) }); 
    const res = await r.json();
    if(res.status === 'success') { 
        const b = document.getElementById('tablaUsuariosBody'); if(!b) return; b.innerHTML = ""; 
        res.data.forEach(u => { 
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
            b.innerHTML += `<tr><td><code>${u.cod}</code></td><td><strong>${u.nombre}</strong></td><td>${contactos}</td><td><span class="badge role">${u.rol || 'No asignado'}</span></td></tr>`; 
        }); 
    } 
}

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
