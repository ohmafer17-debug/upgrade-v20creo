// 🚀 DETECCIÓN DINÁMICA DE ENTORNO (LOCAL VS PRODUCCIÓN)
const base_url = window.location.origin + (window.location.hostname === 'localhost' ? '/upgrade_systems' : '');
const urlProcesadorAdmin = `${base_url}/controllers/ups_procesar.php`;
let cacheLicencias = [];

// Candado de control (RBAC Integral)
(function() {
    const sessionActive = localStorage.getItem('ups_sesion_id');
    const roleActive = localStorage.getItem('cliente_sesion_rol');
    if (!sessionActive || sessionActive !== 'UPS-STAFF' || (roleActive !== 'Administrador' && roleActive !== 'Usuario Estándar' && roleActive !== 'Invitado')) {
        alert("Acceso Denegado: Esta ruta estratégica requiere autenticación.");
        localStorage.clear(); 
        window.location.replace("login.html");
        document.body.innerHTML = "<h1>Acceso Restringido - Redireccionando...</h1>";
    }
})();

window.addEventListener('pageshow', function (event) {
    if (event.persisted || (typeof window.performance != "undefined" && window.performance.navigation.type === 2)) {
        if (!localStorage.getItem('ups_sesion_id')) {
            window.location.replace("login.html");
        }
    }
});

let listaResponsablesCache = [];

function aplicarRestriccionesPorRol() {
    const rol = localStorage.getItem('cliente_sesion_rol') || 'Invitado';
    
    // Ocultar pestaña de administración de staff a Estándar e Invitados
    if (rol === 'Usuario Estándar' || rol === 'Invitado') {
        const menuAdmins = document.getElementById('menu-admins');
        if (menuAdmins) menuAdmins.style.display = 'none';
    }
    
    // Ocultar formulario de alta de licencias a Invitados (Solo Lectura)
    if (rol === 'Invitado') {
        const formBox = document.querySelector('#vista-licencias .form-box:not(#box-editar-empresa)');
        if (formBox) formBox.style.display = 'none';
        
        const formEditBox = document.getElementById('box-editar-empresa');
        if (formEditBox) formEditBox.style.display = 'none';
    }
}

function cambiarVistaUps(vista) {
    const rol = localStorage.getItem('cliente_sesion_rol') || 'Invitado';
    if (vista === 'admins' && (rol === 'Usuario Estándar' || rol === 'Invitado')) {
        alert("Acceso Denegado: Tu rango de acceso no tiene permisos para esta sección.");
        cambiarVistaUps('licencias');
        return;
    }

    document.querySelectorAll('.vista-seccion').forEach(s => s.classList.remove('activa'));
    document.querySelectorAll('.menu-items li').forEach(i => i.classList.remove('active'));
    document.getElementById('vista-' + vista).classList.add('activa');
    document.getElementById('menu-' + vista).classList.add('active');
    
    aplicarRestriccionesPorRol();

    if (vista === 'licencias') { inicializarFiltroEmpresas(); cargarLicenciasOFiltrarRoles(); }
    if (vista === 'admins') { cargarAdministradoresUps(); }
}

function cerrarSesionMaster() {
    if (confirm("¿Estás segura de que deseas cerrar la sesión?")) {
        localStorage.clear(); 
        window.location.replace("login.html");
    }
}


function evaluarFlujoResponsableNacional(valor) {
    const container = document.getElementById('wrapperFiltroRN');
    if (valor === 'SI') { container.style.display = "flex"; cargarCatalogoResponsables(); } 
    else {
        container.style.display = "none"; document.getElementById('previewRNBox').style.display = "none";
        document.getElementById('filtroNombresRNSelect').value = "NINGUNO"; restablecerRolesFormularioCompleto();
    }
}

async function cargarCatalogoResponsables() {
    const select = document.getElementById('filtroNombresRNSelect');
    select.innerHTML = `<option value="NINGUNO">-- Cargando catálogo corporativo... --</option>`;
    try {
        const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'listar_licencias_globales', filtro_empresa: 'TODAS' }) });
        const res = await r.json();
        if (res.status === 'success') {
            listaResponsablesCache = res.data.filter(u => u.rol.toLowerCase() === 'consultor' || u.rol.toLowerCase() === 'responsable nacional' || u.rol.toLowerCase() === 'responsable_nacional');
            select.innerHTML = `<option value="NINGUNO">-- Seleccione el nodo jerárquico superior --</option>`;
            listaResponsablesCache.forEach(u => { select.innerHTML += `<option value="${u.usuario_responsable}">${u.nombre_comercial} (${u.rol})</option>`; });
        }
    } catch (err) { console.error(err); }
}

function actualizarPrevisualizacionRN(emailElegido) {
    const box = document.getElementById('previewRNBox');
    const selectRolForm = document.getElementById('empresaRolInicial');
    if (emailElegido === 'NINGUNO') { box.style.display = "none"; restablecerRolesFormularioCompleto(); return; }
    
    const nodoSuperior = listaResponsablesCache.find(u => u.usuario_responsable === emailElegido);
    if (nodoSuperior) {
        box.innerHTML = `<strong>👤 Superior Asignado:</strong> ${nodoSuperior.nombre_comercial}<br><strong>📧 Correo Enlace:</strong> ${emailElegido}<br><strong>🎖️ Rango de Origen:</strong> ${nodoSuperior.rol}`;
        box.style.display = "block";
        const rolPadre = nodoSuperior.rol.toLowerCase();
        selectRolForm.innerHTML = "";
        if (rolPadre === 'consultor') {
            selectRolForm.innerHTML = `<option value="Responsable Nacional" selected>Responsable Nacional</option><option value="Tipo 1">Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`;
        } else if (rolPadre === 'responsable nacional' || rolPadre === 'responsable_nacional') {
            selectRolForm.innerHTML = `<option value="Tipo 1" selected>Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`;
        }
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

function restablecerRolesFormularioCompleto() {
    document.getElementById('empresaRolInicial').innerHTML = `<option value="Consultor" selected>Consultor (Dueño / Cuenta Corporativa)</option><option value="Responsable Nacional">Responsable Nacional</option><option value="Tipo 1">Tipo 1</option><option value="Tipo 2">Tipo 2</option><option value="Tipo 3">Tipo 3</option>`;
}

document.getElementById('registroEmpresaForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const pass = document.getElementById('passUsuario').value.trim();
    const passErr = validarPasswordComplejidad(pass);
    if (passErr) {
        alert("Seguridad de Contraseña: " + passErr);
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'registrar_nueva_empresa');
    formData.append('empresa_nombre', document.getElementById('empresaNombre').value.trim());
    formData.append('empresa_cod', document.getElementById('empresaCod').value.trim());
    formData.append('nombre_usuario', document.getElementById('nombreUsuario').value.trim());
    formData.append('email_usuario', document.getElementById('emailUsuario').value.trim());
    formData.append('email_adicional', document.getElementById('emailUsuarioAdicional').value.trim());
    formData.append('telefono_principal', document.getElementById('telefonoUsuarioPrincipal').value.trim());
    formData.append('telefono_adicional', document.getElementById('telefonoUsuarioAdicional').value.trim());
    formData.append('direccion', document.getElementById('empresaDireccion').value.trim());
    formData.append('coordenadas', document.getElementById('empresaCoordenadas').value.trim());
    formData.append('rol_inicial', document.getElementById('empresaRolInicial').value);
    formData.append('pass_usuario', pass);
    formData.append('rn_vinculado', document.getElementById('filtroNombresRNSelect').value);
    formData.append('usuario_ejecutor_email', localStorage.getItem('ups_sesion_email') || '');

    const logoFile = document.getElementById('empresaLogo').files[0];
    if (logoFile) {
        formData.append('logo', logoFile);
    }

    const r = await fetch(urlProcesadorAdmin, { method: 'POST', body: formData });
    const res = await r.json();
    if (res.status === 'success') { 
        alert(res.message); 
        document.getElementById('registroEmpresaForm').reset(); 
        document.getElementById('wrapperFiltroRN').style.display = "none"; 
        document.getElementById('previewRNBox').style.display = "none"; 
        restablecerRolesFormularioCompleto(); 
        cambiarVistaUps('licencias'); 
    } else { 
        alert(res.message); 
    }
});

async function inicializarFiltroEmpresas() {
    const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'obtener_solo_empresas_raiz', usuario_ejecutor_email: localStorage.getItem('ups_sesion_email') || '' }) });
    const res = await r.json();
    if (res.status === 'success') {
        const select = document.getElementById('filtroEmpresaSelect'); const valorActual = select.value;
        select.innerHTML = `<option value="TODAS">-- Mostrar Todas las Empresas Raíz --</option>`;
        res.data.forEach(emp => { select.innerHTML += `<option value="${emp.cod}">${emp.nombre} (${emp.cod})</option>`; });
        select.value = valorActual;
    }
}

async function cargarLicenciasOFiltrarRoles() {
    const filtro = document.getElementById('filtroEmpresaSelect').value;
    const r = await fetch(urlProcesadorAdmin, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ 
            accion: 'listar_licencias_globales', 
            filtro_empresa: filtro,
            usuario_ejecutor_email: localStorage.getItem('ups_sesion_email') || ''
        }) 
    });
    const res = await r.json();
    if (res.status === 'success') {
        cacheLicencias = res.data;
        const t = document.getElementById('tablaLicenciasBody'); t.innerHTML = "";
        const rolActive = localStorage.getItem('cliente_sesion_rol') || 'Invitado';

        res.data.forEach((emp, index) => {
            const esActivo = parseInt(emp.activo) === 1;
            const logoHtml = emp.logo ? `<img src="${base_url}/public/uploads/logos/${emp.logo}" class="logo-thumbnail">` : `<i class="fas fa-building" style="color: #94a3b8; font-size: 1.25rem; vertical-align: middle; margin-right: 12px;"></i>`;
            let botonLlamar = emp.telefono_principal ? `<a href="tel:${emp.telefono_principal}" class="btn-action call"><i class="fas fa-phone"></i> Llamar</a>` : '';
            
            let accionesHtml = '';
            if (rolActive !== 'Invitado') {
                accionesHtml += `<button class="btn-action edit" onclick="prepararEdicionEmpresa(${index})"><i class="fas fa-edit"></i> Editar</button>`;
                accionesHtml += `<button class="btn-action suspend" onclick="cambiarEstatusEmpresa('${emp.id}', ${emp.activo})"><i class="fas fa-power-off"></i> ${esActivo ? 'Suspender' : 'Activar'}</button>`;
            }
            accionesHtml += botonLlamar;

            t.innerHTML += `<tr><td>${logoHtml}<code>${emp.empresa_cod}</code></td><td><strong>${emp.nombre_comercial}</strong></td><td>${emp.usuario_responsable}</td><td><span class="badge role">${emp.rol}</span></td><td><span class="badge ${esActivo ? 'green' : 'red'}">${esActivo ? 'Activa' : 'Suspendida'}</span></td><td class="actions-cell">${accionesHtml}</td></tr>`;
        });
    }
}

function prepararEdicionEmpresa(index) {
    const emp = cacheLicencias[index];
    document.getElementById('editEmpresaId').value = emp.id;
    document.getElementById('editEmpresaCod').value = emp.empresa_cod;
    document.getElementById('editEmpresaNombre').value = emp.nombre_comercial;
    document.getElementById('editEmpresaEmail').value = emp.usuario_responsable;
    document.getElementById('editEmpresaEmailAdicional').value = emp.email_adicional || '';
    document.getElementById('editEmpresaTelPrincipal').value = emp.telefono_principal || '';
    document.getElementById('editEmpresaTelAdicional').value = emp.telefono_adicional || '';
    document.getElementById('editEmpresaDireccion').value = emp.direccion || '';
    document.getElementById('editEmpresaCoordenadas').value = emp.coordenadas || '';
    document.getElementById('editEmpresaRol').value = emp.rol || 'Consultor';
    document.getElementById('editEmpresaPass').value = ''; // Vacío por defecto
    
    // Vista previa del logo
    const previewBox = document.getElementById('editLogoPreview');
    const previewImg = document.getElementById('editLogoImg');
    const fileInput = document.getElementById('editEmpresaLogo');
    if (fileInput) fileInput.value = '';
    
    if (emp.logo && previewImg && previewBox) {
        previewImg.src = `${base_url}/public/uploads/logos/${emp.logo}`;
        previewBox.style.display = 'block';
    } else if (previewBox) {
        previewBox.style.display = 'none';
        previewImg.src = '';
    }

    document.getElementById('box-editar-empresa').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelarEdicionEmpresa() { 
    document.getElementById('box-editar-empresa').style.display = 'none'; 
    const previewBox = document.getElementById('editLogoPreview');
    if (previewBox) previewBox.style.display = 'none';
}

document.getElementById('edicionEmpresaForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const pass = document.getElementById('editEmpresaPass').value.trim();
    if (pass !== "") {
        const passErr = validarPasswordComplejidad(pass);
        if (passErr) {
            alert("Seguridad de Contraseña: " + passErr);
            return;
        }
    }

    const formData = new FormData();
    formData.append('accion', 'editar_empresa_cliente');
    formData.append('id', document.getElementById('editEmpresaId').value);
    formData.append('nombre', document.getElementById('editEmpresaNombre').value.trim());
    formData.append('email', document.getElementById('editEmpresaEmail').value.trim());
    formData.append('email_adicional', document.getElementById('editEmpresaEmailAdicional').value.trim());
    formData.append('telefono_principal', document.getElementById('editEmpresaTelPrincipal').value.trim());
    formData.append('telefono_adicional', document.getElementById('editEmpresaTelAdicional').value.trim());
    formData.append('direccion', document.getElementById('editEmpresaDireccion').value.trim());
    formData.append('coordenadas', document.getElementById('editEmpresaCoordenadas').value.trim());
    formData.append('rol', document.getElementById('editEmpresaRol').value);
    formData.append('pass', pass);
    formData.append('usuario_ejecutor_email', localStorage.getItem('ups_sesion_email') || '');

    const logoFile = document.getElementById('editEmpresaLogo').files[0];
    if (logoFile) {
        formData.append('logo', logoFile);
    }

    const r = await fetch(urlProcesadorAdmin, { method: 'POST', body: formData });
    const res = await r.json();
    if (res.status === 'success') { 
        alert(res.message); 
        cancelarEdicionEmpresa(); 
        cargarLicenciasOFiltrarRoles(); 
    } else { 
        alert(res.message); 
    }
});

async function cambiarEstatusEmpresa(id, estatusActual) {
    const nuevoEstatus = parseInt(estatusActual) === 1 ? 0 : 1;
    if (confirm(`¿Confirmas el cambio de estatus para esta empresa?`)) {
        const r = await fetch(urlProcesadorAdmin, { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ 
                accion: 'estatus_empresa_cliente', 
                id: id, 
                activo: nuevoEstatus,
                usuario_ejecutor_email: localStorage.getItem('ups_sesion_email') || ''
            }) 
        });
        const res = await r.json(); 
        if (res.status === 'success') { 
            cargarLicenciasOFiltrarRoles(); 
        } else {
            alert(res.message);
        }
    }
}

document.getElementById('registroAdminForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const idEdit = document.getElementById('adminIdEdit').value;
    const pass = document.getElementById('adminPass').value.trim();
    
    if (idEdit === "" || pass.length < 20) {
        const passErr = validarPasswordComplejidad(pass);
        if (passErr) {
            alert("Seguridad de Contraseña: " + passErr);
            return;
        }
    }
    
    const payload = {
        accion: idEdit ? 'editar_administrador_ups' : 'registrar_administrador_ups',
        id: idEdit,
        nombre: document.getElementById('adminNombre').value.trim(),
        email: document.getElementById('adminEmail').value.trim(),
        email_secundario: document.getElementById('adminEmailSecundario').value.trim(),
        telefono_principal: document.getElementById('adminTelPrincipal').value.trim(),
        telefono_secundario: document.getElementById('adminTelSecundario').value.trim(),
        rol: document.getElementById('adminRol').value,
        pass: pass,
        usuario_ejecutor_email: localStorage.getItem('ups_sesion_email') || ''
    };
    
    const r = await fetch(urlProcesadorAdmin, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const res = await r.json();
    if (res.status === 'success') {
        alert(res.message);
        document.getElementById('registroAdminForm').reset();
        document.getElementById('adminIdEdit').value = "";
        document.getElementById('btnSubmitAdmin').innerHTML = `<i class="fas fa-user-check"></i> Registrar Admin Staff`;
        cargarAdministradoresUps();
    } else {
        alert(res.message);
    }
});

async function cargarAdministradoresUps() {
    const r = await fetch(urlProcesadorAdmin, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            accion: 'listar_administradores_ups',
            usuario_ejecutor_email: localStorage.getItem('ups_sesion_email') || ''
        })
    });
    const res = await r.json();
    if (res.status === 'success') {
        const t = document.getElementById('tablaAdminsBody'); t.innerHTML = "";
        const emailLogueado = localStorage.getItem('ups_sesion_email') || '';

        res.data.forEach(adm => {
            let contactos = `<div><span style="font-weight:600;"><i class="fas fa-envelope"></i> Principal:</span> <code>${adm.email}</code></div>`;
            if (adm.email_secundario) {
                contactos += `<div><span style="font-weight:600;"><i class="fas fa-envelope"></i> Secundario:</span> <code>${adm.email_secundario}</code></div>`;
            }
            if (adm.telefono_principal) {
                contactos += `<div><span style="font-weight:600;"><i class="fas fa-phone"></i> Principal:</span> ${adm.telefono_principal}</div>`;
            }
            if (adm.telefono_secundario) {
                contactos += `<div><span style="font-weight:600;"><i class="fas fa-phone"></i> Secundario:</span> ${adm.telefono_secundario}</div>`;
            }
            
            let botonesLlamada = '';
            if (adm.telefono_principal) {
                botonesLlamada += `<a href="tel:${adm.telefono_principal}" class="btn-action call" style="font-size:0.75rem; padding:4px 8px; margin: 2px;"><i class="fas fa-phone"></i> Principal</a>`;
            }
            if (adm.telefono_secundario) {
                botonesLlamada += `<a href="tel:${adm.telefono_secundario}" class="btn-action call" style="font-size:0.75rem; padding:4px 8px; margin: 2px; background:#f0fdf4; color:#166534; border-color:#bbf7d0;"><i class="fas fa-phone"></i> Secundario</a>`;
            }
            
            let botonesControlHtml = '';
            if (adm.email !== emailLogueado) {
                botonesControlHtml += `<button class="btn-action edit" onclick="cargarAdminEnFormulario('${adm.id}', '${adm.nombre}', '${adm.email}', '${adm.email_secundario || ''}', '${adm.telefono_principal || ''}', '${adm.telefono_secundario || ''}', '${adm.rol}', '${adm.pass}')"><i class="fas fa-pen"></i> Editar</button>`;
                botonesControlHtml += `<button class="btn-action suspend" onclick="cambiarEstatusAdmin('${adm.id}', '${adm.estatus}')"><i class="fas fa-ban"></i> ${adm.estatus === 'Activo' ? 'Suspender' : 'Activar'}</button>`;
            } else {
                botonesControlHtml += `<span style="font-size:0.8rem; font-weight:700; color:#64748b; font-style:italic;"><i class="fas fa-user-shield"></i> Tu Cuenta Activa</span>`;
            }
            
            t.innerHTML += `<tr>
                <td>${adm.id}</td>
                <td><strong>${adm.nombre}</strong></td>
                <td>${contactos}</td>
                <td><span class="badge role" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-weight:700;">${adm.rol}</span></td>
                <td><span class="badge ${adm.estatus === 'Activo' ? 'green' : 'red'}">${adm.estatus}</span></td>
                <td class="actions-cell">
                    ${botonesControlHtml}
                    ${botonesLlamada}
                </td>
            </tr>`;
        });
    }
}

function cargarAdminEnFormulario(id, nombre, email, emailSecundario, telPrincipal, telSecundario, rol, pass) {
    document.getElementById('adminIdEdit').value = id;
    document.getElementById('adminNombre').value = nombre;
    document.getElementById('adminEmail').value = email;
    document.getElementById('adminEmailSecundario').value = emailSecundario;
    document.getElementById('adminTelPrincipal').value = telPrincipal;
    document.getElementById('adminTelSecundario').value = telSecundario;
    document.getElementById('adminRol').value = rol;
    document.getElementById('adminPass').value = pass;
    document.getElementById('btnSubmitAdmin').innerHTML = `<i class="fas fa-arrows-rotate"></i> Actualizar Admin Staff`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function cambiarEstatusAdmin(id, estatusActual) {
    const nuevoEstatus = estatusActual === 'Activo' ? 'Suspendido' : 'Activo';
    if (confirm(`¿Cambiar estatus del administrador a [${nuevoEstatus}]?`)) {
        const r = await fetch(urlProcesadorAdmin, { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ 
                accion: 'estatus_administrador_ups', 
                id: id, 
                estatus: nuevoEstatus,
                usuario_ejecutor_email: localStorage.getItem('ups_sesion_email') || ''
            }) 
        });
        const res = await r.json(); 
        if (res.status === 'success') { 
            cargarAdministradoresUps(); 
        } else {
            alert(res.message);
        }
    }
}

// 🚀 GEOCODIFICACIÓN DINÁMICA BIDIRECCIONAL (OPENSTREETMAP NOMINATIM)
async function geocodificarDireccion(idDireccion, idCoordenadas) {
    const direccionVal = document.getElementById(idDireccion).value.trim();
    if (!direccionVal || direccionVal.length < 5) return;
    
    const inputCoordenadas = document.getElementById(idCoordenadas);
    
    // Mostrar indicador de carga / progreso
    let indicator = document.getElementById(idDireccion + '_geo_indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = idDireccion + '_geo_indicator';
        indicator.className = 'geocoded-indicator';
        document.getElementById(idDireccion).parentNode.appendChild(indicator);
    }
    indicator.style.display = 'block';
    indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando coordenadas en mapa...';
    indicator.style.background = '#eff6ff';
    indicator.style.color = '#1d4ed8';
    indicator.style.borderColor = '#bfdbfe';

    try {
        const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(direccionVal)}&limit=1`, {
            headers: { 'Accept': 'application/json', 'User-Agent': 'UpgradeSystems-App' }
        });
        if (!r.ok) throw new Error("Error en red");
        const data = await r.json();
        if (data && data.length > 0) {
            const lat = parseFloat(data[0].lat).toFixed(6);
            const lon = parseFloat(data[0].lon).toFixed(6);
            inputCoordenadas.value = `${lat}, ${lon}`;
            
            indicator.innerHTML = `<i class="fas fa-circle-check"></i> Coordenadas sincronizadas: ${lat}, ${lon}`;
            indicator.style.background = '#dcfce7';
            indicator.style.color = '#15803d';
            indicator.style.borderColor = '#bbf7d0';
        } else {
            indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No se encontraron coordenadas exactas en OpenStreetMap.';
            indicator.style.background = '#fef3c7';
            indicator.style.color = '#b45309';
            indicator.style.borderColor = '#fde68a';
        }
    } catch (err) {
        console.error(err);
        indicator.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Error de red al consultar mapa de coordenadas.';
        indicator.style.background = '#fee2e2';
        indicator.style.color = '#b91c1c';
        indicator.style.borderColor = '#fca5a5';
    }
}

async function geocodificarCoordenadas(idCoordenadas, idDireccion) {
    const coordsVal = document.getElementById(idCoordenadas).value.trim();
    if (!coordsVal) return;
    
    const inputDireccion = document.getElementById(idDireccion);
    const parts = coordsVal.split(',').map(c => c.trim());
    if (parts.length !== 2) return;
    
    const lat = parseFloat(parts[0]);
    const lon = parseFloat(parts[1]);
    if (isNaN(lat) || isNaN(lon)) return;

    let indicator = document.getElementById(idCoordenadas + '_geo_indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = idCoordenadas + '_geo_indicator';
        indicator.className = 'geocoded-indicator';
        document.getElementById(idCoordenadas).parentNode.appendChild(indicator);
    }
    indicator.style.display = 'block';
    indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Identificando dirección física...';
    indicator.style.background = '#eff6ff';
    indicator.style.color = '#1d4ed8';
    indicator.style.borderColor = '#bfdbfe';

    try {
        const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`, {
            headers: { 'Accept': 'application/json', 'User-Agent': 'UpgradeSystems-App' }
        });
        if (!r.ok) throw new Error("Error en red");
        const data = await r.json();
        if (data && data.display_name) {
            inputDireccion.value = data.display_name;
            
            indicator.innerHTML = '<i class="fas fa-circle-check"></i> Dirección física identificada y sincronizada.';
            indicator.style.background = '#dcfce7';
            indicator.style.color = '#15803d';
            indicator.style.borderColor = '#bbf7d0';
        } else {
            indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Coordenadas fuera de zona urbana o sin nombre.';
            indicator.style.background = '#fef3c7';
            indicator.style.color = '#b45309';
            indicator.style.borderColor = '#fde68a';
        }
    } catch (err) {
        console.error(err);
        indicator.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Error al geocodificar de reversa.';
        indicator.style.background = '#fee2e2';
        indicator.style.color = '#b91c1c';
        indicator.style.borderColor = '#fca5a5';
    }
}

// Inicializar listeners al cargar
document.addEventListener("DOMContentLoaded", () => {
    // Aplicar restricciones
    aplicarRestriccionesPorRol();

    // Registro
    const empDir = document.getElementById('empresaDireccion');
    const empCoords = document.getElementById('empresaCoordenadas');
    if (empDir && empCoords) {
        empDir.addEventListener('blur', () => geocodificarDireccion('empresaDireccion', 'empresaCoordenadas'));
        empCoords.addEventListener('blur', () => geocodificarCoordenadas('empresaCoordenadas', 'empresaDireccion'));
    }
    
    // Edición
    const editDir = document.getElementById('editEmpresaDireccion');
    const editCoords = document.getElementById('editEmpresaCoordenadas');
    if (editDir && editCoords) {
        editDir.addEventListener('blur', () => geocodificarDireccion('editEmpresaDireccion', 'editEmpresaCoordenadas'));
        editCoords.addEventListener('blur', () => geocodificarCoordenadas('editEmpresaCoordenadas', 'editEmpresaDireccion'));
    }
});

