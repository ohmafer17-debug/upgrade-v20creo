// 🚀 DETECCIÓN DINÁMICA DE ENTORNO (LOCAL VS PRODUCCIÓN)
const base_url = window.location.origin + (window.location.hostname === 'localhost' ? '/upgrade_systems' : '');
const urlProcesadorAdmin = `${base_url}/controllers/ups_procesar.php`;
let cacheLicencias = [];

// Candado de control
(function() {
    const sessionActive = localStorage.getItem('ups_sesion_id');
    const roleActive = localStorage.getItem('cliente_sesion_rol');
    if (!sessionActive || sessionActive !== 'UPS-STAFF' || roleActive !== 'Administrador') {
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

function cambiarVistaUps(vista) {
    document.querySelectorAll('.vista-seccion').forEach(s => s.classList.remove('activa'));
    document.querySelectorAll('.menu-items li').forEach(i => i.classList.remove('active'));
    document.getElementById('vista-' + vista).classList.add('activa');
    document.getElementById('menu-' + vista).classList.add('active');
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

    const payload = { 
        accion: 'registrar_nueva_empresa', 
        empresa_nombre: document.getElementById('empresaNombre').value.trim(), 
        empresa_cod: document.getElementById('empresaCod').value.trim(), 
        nombre_usuario: document.getElementById('nombreUsuario').value.trim(), 
        email_usuario: document.getElementById('emailUsuario').value.trim(), 
        email_adicional: document.getElementById('emailUsuarioAdicional').value.trim(),
        telefono_principal: document.getElementById('telefonoUsuarioPrincipal').value.trim(),
        telefono_adicional: document.getElementById('telefonoUsuarioAdicional').value.trim(),
        direccion: document.getElementById('empresaDireccion').value.trim(),
        coordenadas: document.getElementById('empresaCoordenadas').value.trim(),
        rol_inicial: document.getElementById('empresaRolInicial').value, 
        pass_usuario: pass, 
        rn_vinculado: document.getElementById('filtroNombresRNSelect').value 
    };
    const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const res = await r.json();
    if (res.status === 'success') { alert(res.message); document.getElementById('registroEmpresaForm').reset(); document.getElementById('wrapperFiltroRN').style.display = "none"; document.getElementById('previewRNBox').style.display = "none"; restablecerRolesFormularioCompleto(); cambiarVistaUps('licencias'); } else { alert(res.message); }
});

async function inicializarFiltroEmpresas() {
    const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'obtener_solo_empresas_raiz' }) });
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
    const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'listar_licencias_globales', filtro_empresa: filtro }) });
    const res = await r.json();
    if (res.status === 'success') {
        cacheLicencias = res.data;
        const t = document.getElementById('tablaLicenciasBody'); t.innerHTML = "";
        res.data.forEach((emp, index) => {
            const esActivo = parseInt(emp.activo) === 1;
            t.innerHTML += `<tr><td><code>${emp.empresa_cod}</code></td><td><strong>${emp.nombre_comercial}</strong></td><td>${emp.usuario_responsable}</td><td><span class="badge role">${emp.rol}</span></td><td><span class="badge ${esActivo ? 'green' : 'red'}">${esActivo ? 'Activa' : 'Suspendida'}</span></td><td class="actions-cell"><button class="btn-action edit" onclick="prepararEdicionEmpresa(${index})"><i class="fas fa-edit"></i> Editar</button><button class="btn-action suspend" onclick="cambiarEstatusEmpresa('${emp.id}', ${emp.activo})"><i class="fas fa-power-off"></i> ${esActivo ? 'Suspender' : 'Activar'}</button></td></tr>`;
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
    document.getElementById('box-editar-empresa').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelarEdicionEmpresa() { document.getElementById('box-editar-empresa').style.display = 'none'; }

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

    const payload = {
        accion: 'editar_empresa_cliente',
        id: document.getElementById('editEmpresaId').value,
        nombre: document.getElementById('editEmpresaNombre').value.trim(),
        email: document.getElementById('editEmpresaEmail').value.trim(),
        email_adicional: document.getElementById('editEmpresaEmailAdicional').value.trim(),
        telefono_principal: document.getElementById('editEmpresaTelPrincipal').value.trim(),
        telefono_adicional: document.getElementById('editEmpresaTelAdicional').value.trim(),
        direccion: document.getElementById('editEmpresaDireccion').value.trim(),
        coordenadas: document.getElementById('editEmpresaCoordenadas').value.trim(),
        rol: document.getElementById('editEmpresaRol').value,
        pass: pass
    };

    const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const res = await r.json();
    if (res.status === 'success') { alert(res.message); cancelarEdicionEmpresa(); cargarLicenciasOFiltrarRoles(); } else { alert(res.message); }
});

async function cambiarEstatusEmpresa(id, estatusActual) {
    const nuevoEstatus = parseInt(estatusActual) === 1 ? 0 : 1;
    if (confirm(`¿Confirmas el cambio de estatus para esta empresa?`)) {
        const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'estatus_empresa_cliente', id: id, activo: nuevoEstatus }) });
        const res = await r.json(); if (res.status === 'success') { cargarLicenciasOFiltrarRoles(); }
    }
}

document.getElementById('registroAdminForm').addEventListener('submit', async function(e) {
    e.preventDefault(); const idEdit = document.getElementById('adminIdEdit').value;
    const pass = document.getElementById('adminPass').value.trim();
    
    if (idEdit === "" || pass.length < 20) {
        const passErr = validarPasswordComplejidad(pass);
        if (passErr) {
            alert("Seguridad de Contraseña: " + passErr);
            return;
        }
    }
    
    const payload = { accion: idEdit ? 'editar_administrador_ups' : 'registrar_administrador_ups', id: idEdit, nombre: document.getElementById('adminNombre').value.trim(), email: document.getElementById('adminEmail').value.trim(), pass: pass };
    const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const res = await r.json();
    if (res.status === 'success') { alert(res.message); document.getElementById('registroAdminForm').reset(); document.getElementById('adminIdEdit').value = ""; document.getElementById('btnSubmitAdmin').innerHTML = `<i class="fas fa-user-check"></i> Registrar Admin Staff`; cargarAdministradoresUps(); } else { alert(res.message); }
});

async function cargarAdministradoresUps() {
    const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'listar_administradores_ups' }) });
    const res = await r.json();
    if (res.status === 'success') {
        const t = document.getElementById('tablaAdminsBody'); t.innerHTML = "";
        res.data.forEach(adm => { t.innerHTML += `<tr><td>${adm.id}</td><td><strong>${adm.nombre}</strong></td><td><code>${adm.email}</code></td><td><span class="badge ${adm.estatus === 'Activo' ? 'green' : 'red'}">${adm.estatus}</span></td><td class="actions-cell"><button class="btn-action edit" onclick="cargarAdminEnFormulario('${adm.id}', '${adm.nombre}', '${adm.email}', '${adm.pass}')"><i class="fas fa-pen"></i> Editar</button><button class="btn-action suspend" onclick="cambiarEstatusAdmin('${adm.id}', '${adm.estatus}')"><i class="fas fa-ban"></i> ${adm.estatus === 'Activo' ? 'Suspender' : 'Activar'}</button></td></tr>`; });
    }
}

function cargarAdminEnFormulario(id, nombre, email, pass) { document.getElementById('adminIdEdit').value = id; document.getElementById('adminNombre').value = nombre; document.getElementById('adminEmail').value = email; document.getElementById('adminPass').value = pass; document.getElementById('btnSubmitAdmin').innerHTML = `<i class="fas fa-arrows-rotate"></i> Actualizar Admin Staff`; }

async function cambiarEstatusAdmin(id, estatusActual) {
    const nuevoEstatus = estatusActual === 'Activo' ? 'Suspendido' : 'Activo';
    if (confirm(`¿Cambiar estatus del administrador a [${nuevoEstatus}]?`)) {
        const r = await fetch(urlProcesadorAdmin, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'estatus_administrador_ups', id: id, estatus: nuevoEstatus }) });
        const res = await r.json(); if (res.status === 'success') { cargarAdministradoresUps(); }
    }
}
