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
const urlProcesadorAdmin = `${base_url}/controllers/ups_procesar.php`;
let cacheLicencias = [];
let adminMap = null;
let adminMarker = null;
let adminEditMap = null;
let adminEditMarker = null;
let consMap = null;
let consMarker = null;

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
    
    if (vista === 'registrar') {
        initAdminMaps();
        setTimeout(() => { if (adminMap) adminMap.invalidateSize(); }, 150);
    }

    // Cerrar menú móvil si está abierto
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.remove('mobile-expanded');
    }
}

async function cerrarSesionMaster() {
    if (confirm("¿Estás segura de que deseas cerrar la sesión?")) {
        try {
            await fetch(`../controllers/logout.php`);
        } catch(e) {}
        localStorage.clear(); 
        window.location.replace("login.html");
    }
}


function evaluarFlujoResponsableNacional(valor) {}



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



let numeroAleatorioRegistro = Math.floor(100 + Math.random() * 900);

function regenerarNumeroAleatorio() {
    numeroAleatorioRegistro = Math.floor(100 + Math.random() * 900);
}

document.addEventListener('DOMContentLoaded', () => {
    // Rellenar datos del usuario activo y su rol en la cabecera flotante
    const sesionNombre = localStorage.getItem('ups_sesion_nombre') || 'Usuario Staff';
    const sesionRol = localStorage.getItem('cliente_sesion_rol') || 'Staff';
    const elNombre = document.getElementById('headerNombreUsuario');
    const elRol = document.getElementById('headerRolUsuario');
    if (elNombre) elNombre.textContent = sesionNombre;
    if (elRol) elRol.textContent = sesionRol;

    const empNombreInput = document.getElementById('empresaNombre');
    if (empNombreInput) {
        empNombreInput.addEventListener('input', function() {
            const nombre = this.value;
            const codInput = document.getElementById('empresaCod');
            if (!codInput) return;
            
            if (!nombre.trim()) {
                codInput.value = '';
                return;
            }

            let limpio = nombre.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            limpio = limpio.replace(/[^a-zA-Z0-9]/g, "").toUpperCase();

            let letras = limpio;
            if (letras.length < 3) {
                letras = (letras + "XYZ").substring(0, 4);
            } else {
                letras = letras.substring(0, 4);
            }

            codInput.value = `${letras}-${numeroAleatorioRegistro}`;
        });
    }
});


document.getElementById('registroEmpresaForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    try {
        const pass = document.getElementById('passUsuario').value.trim();
        const passErr = validarPasswordComplejidad(pass);
        if (passErr) {
            alert("Seguridad de Contraseña: " + passErr);
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'registrar_nueva_empresa');
        formData.append('empresa_nombre', document.getElementById('empresaNombre').value.trim());
        formData.append('empresa_cod', document.getElementById('empresaCod').value.trim().toUpperCase());
        formData.append('encargado', document.getElementById('empresaEncargado').value.trim());
        formData.append('email_usuario', document.getElementById('emailUsuario').value.trim());
        formData.append('director_email', '');
        formData.append('email_adicional', document.getElementById('emailUsuarioAdicional').value.trim());
        formData.append('telefono_principal', document.getElementById('telefonoUsuarioPrincipal').value.trim());
        formData.append('telefono_adicional', document.getElementById('telefonoUsuarioAdicional').value.trim());
        formData.append('direccion', document.getElementById('empresaDireccion').value.trim());
        formData.append('coordenadas', document.getElementById('empresaCoordenadas').value.trim());
        formData.append('coordenadas_gps', document.getElementById('empresaCoordenadasGps').value.trim());
        formData.append('rol_inicial', 'Consultor');
        formData.append('pass_usuario', pass);
        formData.append('rn_vinculado', 'NINGUNO');
        formData.append('usuario_ejecutor_email', localStorage.getItem('ups_sesion_email') || '');

        const logoFile = document.getElementById('empresaLogo').files[0];
        if (logoFile) formData.append('logo', logoFile);

        const r = await fetch(urlProcesadorAdmin, { method: 'POST', body: formData });
        const res = await r.json();
        if (res.status === 'success') {
            alert(res.message);
            document.getElementById('registroEmpresaForm').reset();
            regenerarNumeroAleatorio();
            cambiarVistaUps('licencias');
        } else {
            alert(res.message);
        }
    } catch (error) {
        console.error("Error submitting company form:", error);
        alert("Error al procesar el formulario: " + error.message);
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
        // Limpiar inputs de búsqueda local al recargar de servidor
        document.getElementById('busquedaLicenciaId').value = "";
        document.getElementById('busquedaLicenciaNombre').value = "";
        renderizarLicencias(cacheLicencias);
    }
}

function renderizarLicencias(lista) {
    const t = document.getElementById('tablaLicenciasBody'); 
    t.innerHTML = "";
    const rolActive = localStorage.getItem('cliente_sesion_rol') || 'Invitado';

    const roots = [];
    const children = [];
    lista.forEach((emp, index) => {
        const origIndex = cacheLicencias.findIndex(c => c.id === emp.id);
        const empCopy = { ...emp, originalIndex: origIndex };
        if (emp.rol && emp.rol.toLowerCase() === 'consultor') {
            roots.push(empCopy);
        } else {
            children.push(empCopy);
        }
    });

    const orderedList = [];
    roots.forEach(root => {
        orderedList.push(root);
        const linked = children.filter(child => child.empresa_cod.startsWith(root.empresa_cod + "/"));
        linked.forEach(child => {
            orderedList.push(child);
            const childIdx = children.indexOf(child);
            if (childIdx > -1) children.splice(childIdx, 1);
        });
    });
    // No listar nodos huérfanos de forma independiente en la tabla general del administrador
    // children.forEach(orphan => {
    //     orderedList.push(orphan);
    // });

    orderedList.forEach(emp => {
        const esActivo = parseInt(emp.activo) === 1;
        const logoHtml = emp.logo ? `<img src="${base_url}/public/uploads/logos/${emp.logo}" class="logo-thumbnail">` : `<i class="fas fa-building" style="color: #94a3b8; font-size: 1.25rem; vertical-align: middle; margin-right: 12px;"></i>`;
        let botonLlamar = emp.telefono_principal ? `<a href="tel:${emp.telefono_principal}" class="btn-action call"><i class="fas fa-phone"></i> Llamar</a>` : '';
        
        let accionesHtml = '';
        if (rolActive !== 'Invitado') {
            accionesHtml += `<button class="btn-action edit" onclick="prepararEdicionEmpresa(${emp.originalIndex})"><i class="fas fa-edit"></i> Editar</button>`;
            accionesHtml += `<button class="btn-action suspend" onclick="cambiarEstatusEmpresa('${emp.id}', ${emp.activo})"><i class="fas fa-power-off"></i> ${esActivo ? 'Suspender' : 'Activar'}</button>`;
        }
        accionesHtml += botonLlamar;

        let rowStyle = '';
        let indentHtml = '';
        if (emp.rol && emp.rol.toLowerCase() === 'consultor') {
            rowStyle = 'style="background: #f1f5f9; font-weight: 600;"';
        } else {
            rowStyle = 'style="background: #ffffff;"';
            indentHtml = '<span style="color: #94a3b8; font-family: monospace; margin-right: 8px; font-weight: bold; font-size: 1.1rem;">└─</span>';
        }

        let consultorSublabel = '';
        if (emp.rol && emp.rol.toLowerCase() !== 'consultor') {
            const parentCode = emp.empresa_cod.split('/')[0];
            const parentEmp = cacheLicencias.find(parent => parent.empresa_cod === parentCode);
            if (parentEmp) {
                consultorSublabel = `<br><small style="color: #64748b; font-size: 0.8rem; margin-left: 20px;">👤 Consultor: ${parentEmp.nombre_comercial}</small>`;
            }
        }

        const fechaVal = emp.fecha_creacion ? emp.fecha_creacion.split(' ')[0] : 'N/A';
        const creadorVal = emp.creado_por ? emp.creado_por : 'Sistema';

        t.innerHTML += `<tr ${rowStyle}>
            <td>${logoHtml}<code>${emp.empresa_cod}</code></td>
            <td>${indentHtml}<strong>${emp.nombre_comercial}</strong>${consultorSublabel}</td>
            <td>${emp.usuario_responsable}</td>
            <td><span class="badge role">${emp.rol}</span></td>
            <td><span style="font-family: monospace; font-size: 0.85rem; color: #475569;">${fechaVal}</span></td>
            <td><span style="font-family: monospace; font-size: 0.85rem; color: #475569;">${creadorVal}</span></td>
            <td><span class="badge ${esActivo ? 'green' : 'red'}">${esActivo ? 'Activa' : 'Suspendida'}</span></td>
            <td class="actions-cell">${accionesHtml}</td>
        </tr>`;
    });
}

function filtrarTablaLicenciasLocal() {
    const queryId = document.getElementById('busquedaLicenciaId').value.trim().toLowerCase();
    const queryNombre = document.getElementById('busquedaLicenciaNombre').value.trim().toLowerCase();

    const listaFiltrada = cacheLicencias.filter(emp => {
        const matchesId = emp.empresa_cod.toLowerCase().includes(queryId);
        const matchesNombre = emp.nombre_comercial.toLowerCase().includes(queryNombre);
        return matchesId && matchesNombre;
    });

    renderizarLicencias(listaFiltrada);
}

function exportarLicenciasCSV() {
    const queryId = document.getElementById('busquedaLicenciaId').value.trim().toLowerCase();
    const queryNombre = document.getElementById('busquedaLicenciaNombre').value.trim().toLowerCase();

    const listaFiltrada = cacheLicencias.filter(emp => {
        const matchesId = emp.empresa_cod.toLowerCase().includes(queryId);
        const matchesNombre = emp.nombre_comercial.toLowerCase().includes(queryNombre);
        return matchesId && matchesNombre;
    });

    if (listaFiltrada.length === 0) {
        alert("No hay registros para exportar.");
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
    csvContent += "Código Interno,Nombre de la Cuenta,Correo Electrónico,Rol en Sistema,Fecha de Creación,Creado Por,Estatus\n";

    listaFiltrada.forEach(emp => {
        const esActivo = parseInt(emp.activo) === 1 ? 'Activa' : 'Suspendida';
        const fechaVal = emp.fecha_creacion ? emp.fecha_creacion : 'N/A';
        const creadorVal = emp.creado_por ? emp.creado_por : 'Sistema';
        
        const cleanNombre = `"${emp.nombre_comercial.replace(/"/g, '""')}"`;
        const cleanRol = `"${emp.rol.replace(/"/g, '""')}"`;
        const cleanCreador = `"${creadorVal.replace(/"/g, '""')}"`;

        csvContent += `${emp.empresa_cod},${cleanNombre},${emp.usuario_responsable},${cleanRol},${fechaVal},${cleanCreador},${esActivo}\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Reporte_Licencias_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function prepararEdicionEmpresa(index) {
    const emp = cacheLicencias[index];
    document.getElementById('editEmpresaId').value = emp.id;
    document.getElementById('editEmpresaCod').value = emp.empresa_cod;
    document.getElementById('editEmpresaNombre').value = emp.nombre_comercial;
    document.getElementById('editEmpresaEncargado').value = emp.encargado || '';
    document.getElementById('editEmpresaEmail').value = emp.usuario_responsable;
    document.getElementById('editEmpresaEmailAdicional').value = emp.email_adicional || '';
    document.getElementById('editEmpresaTelPrincipal').value = emp.telefono_principal || '';
    document.getElementById('editEmpresaTelAdicional').value = emp.telefono_adicional || '';
    document.getElementById('editEmpresaDireccion').value = emp.direccion || '';
    document.getElementById('editEmpresaCoordenadas').value = emp.coordenadas || '';
    document.getElementById('editEmpresaCoordenadasGps').value = emp.coordenadas_gps || '';
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
    document.getElementById('box-editar-empresa').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicionEmpresa() { 
    document.getElementById('box-editar-empresa').style.display = 'none'; 
    document.getElementById('editEmpresaPass').value = '';
    document.getElementById('editEmpresaPassConfirm').value = '';
    const previewBox = document.getElementById('editLogoPreview');
    if (previewBox) previewBox.style.display = 'none';
}

document.getElementById('edicionEmpresaForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const pass = document.getElementById('editEmpresaPass').value.trim();
    const passConfirm = document.getElementById('editEmpresaPassConfirm').value.trim();
    if (pass !== "") {
        if (pass !== passConfirm) {
            alert("Error: Las contraseñas ingresadas no coinciden.");
            return;
        }
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
    formData.append('encargado', document.getElementById('editEmpresaEncargado').value.trim());
    formData.append('email', document.getElementById('editEmpresaEmail').value.trim());
    formData.append('director_email', '');
    formData.append('email_adicional', document.getElementById('editEmpresaEmailAdicional').value.trim());
    formData.append('telefono_principal', document.getElementById('editEmpresaTelPrincipal').value.trim());
    formData.append('telefono_adicional', document.getElementById('editEmpresaTelAdicional').value.trim());
    formData.append('direccion', document.getElementById('editEmpresaDireccion').value.trim());
    formData.append('coordenadas', document.getElementById('editEmpresaCoordenadas').value.trim());
    formData.append('coordenadas_gps', document.getElementById('editEmpresaCoordenadasGps').value.trim());
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
        await inicializarFiltroEmpresas();
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
    document.getElementById('adminNombre').closest('.form-box').scrollIntoView({ behavior: 'smooth' });
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

// 🚀 GEOCODIFICACIÓN DESACTIVADA: El sistema ahora almacena URLs de Google Maps directamente
async function geocodificarDireccion(idDireccion, idCoordenadas) {}
async function geocodificarCoordenadas(idCoordenadas, idDireccion) {}

function initAdminMaps() {}
function updateEditMapPosition(coordsStr) {}

// Inicializar listeners al cargar
document.addEventListener("DOMContentLoaded", () => {
    // Aplicar restricciones
    aplicarRestriccionesPorRol();
});



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

