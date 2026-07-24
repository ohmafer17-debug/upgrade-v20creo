document.addEventListener("DOMContentLoaded", () => {
    verificarBloqueoActive();
});

document.getElementById('formLoginGlobal').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (verificarBloqueoActive()) return;

    const msgBox = document.getElementById('alertBox');
    msgBox.style.display = "none";
    
    const payload = {
        email: document.getElementById('loginEmail').value.trim(),
        pass: document.getElementById('loginPass').value.trim()
    };

    try {
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

        const r = await fetch(`${base_url}/controllers/login_procesar.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!r.ok) { throw new Error("HTTP estatus: " + r.status); }
        const res = await r.json();

        if (res.status === 'success') {
            sessionStorage.removeItem('login_intentos_fallidos');
            localStorage.setItem('cliente_sesion_id', res.id_cliente);
            localStorage.setItem('cliente_sesion_nombre', res.nombre_usuario);
            localStorage.setItem('cliente_sesion_rol', res.rol_usuario);

            alert(res.message);
            
            // 🚀 REDIRECCIÓN MODULARIZADA
            if (res.rol_usuario === 'Administrador' && res.id_cliente === 'UPS-STAFF') {
                window.location.href = "../admin/index.html";
            } else {
                window.location.href = "index.html";
            }
        } else {
            if (res.message.includes("bloqueado") || res.message.includes("intentos fallidos")) {
                const segundos = parseInt(res.message.replace(/\D/g, "")) || 15;
                const tiempoBloqueoHasta = Date.now() + (segundos * 1000);
                localStorage.setItem('login_bloqueado_hasta', tiempoBloqueoHasta);
                verificarBloqueoActive();
            } else {
                registrarIntentoFallido(res.message);
            }
        }
    } catch (error) {
        console.error(error);
        msgBox.innerText = "Error de red: No se pudo conectar con el servidor.";
        msgBox.style.display = "block";
    }
});

function registrarIntentoFallido(mensajeServidor) {
    const msgBox = document.getElementById('alertBox');
    let intentos = parseInt(sessionStorage.getItem('login_intentos_fallidos')) || 0;
    intentos++;
    sessionStorage.setItem('login_intentos_fallidos', intentos);

    if (intentos >= 3) {
        const tiempoBloqueoHasta = Date.now() + 15000; // 15 segundos
        localStorage.setItem('login_bloqueado_hasta', tiempoBloqueoHasta);
        verificarBloqueoActive();
    } else {
        msgBox.innerHTML = `<strong>Acceso Denegado:</strong> ${mensajeServidor}<br><span style="font-size:0.85rem; font-weight:600; color:#ef4444;">Intentos restantes: ${3 - intentos}</span>`;
        msgBox.style.display = "block";
    }
}

function verificarBloqueoActive() {
    const btn = document.getElementById('btnSubmitLogin');
    const msgBox = document.getElementById('alertBox');
    const bloqueoHasta = localStorage.getItem('login_bloqueado_hasta');

    if (bloqueoHasta && Date.now() < bloqueoHasta) {
        btn.disabled = true;
        
        const intervalo = setInterval(() => {
            const tiempoRestante = Math.ceil((bloqueoHasta - Date.now()) / 1000);
            
            if (tiempoRestante <= 0) {
                clearInterval(intervalo);
                localStorage.removeItem('login_bloqueado_hasta');
                sessionStorage.removeItem('login_intentos_fallidos');
                btn.disabled = false;
                btn.innerText = "Ingresar al Sistema";
                msgBox.style.display = "none";
            } else {
                btn.innerText = `Bloqueado (${tiempoRestante}s)`;
                msgBox.innerHTML = `<i class="fas fa-lock"></i> <strong>Formulario Congelado:</strong> Demasiados intentos fallidos. Inténtalo de nuevo en ${tiempoRestante} segundos.`;
                msgBox.style.display = "block";
            }
        }, 1000);

        return true;
    }
    return false;
}

// --- CONTROL DEL MODAL DE RECUPERACIÓN ---
const base_url_rec = (() => {
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
const urlRecuperar = `${base_url_rec}/controllers/recuperar_procesar.php`;

function abrirModalRecuperar(e) {
    e.preventDefault();
    document.getElementById('modalRecuperar').style.display = 'flex';
    irAlPasoRecuperar(1);
}

function cerrarModalRecuperar() {
    document.getElementById('modalRecuperar').style.display = 'none';
    document.getElementById('alertBoxRecuperar').style.display = 'none';
    document.getElementById('recEmail').value = '';
    document.getElementById('recCodigo').value = '';
    document.getElementById('recNuevaPass').value = '';
    document.getElementById('recNuevaPassConf').value = '';
}

function irAlPasoRecuperar(paso) {
    document.querySelectorAll('.recuperar-step').forEach(s => s.classList.remove('activo'));
    document.getElementById('step-' + paso).classList.add('activo');
    document.getElementById('alertBoxRecuperar').style.display = 'none';
}

async function solicitarCodigoRecuperacion() {
    const email = document.getElementById('recEmail').value.trim();
    const alertBox = document.getElementById('alertBoxRecuperar');
    alertBox.style.display = 'none';

    if (!email) {
        alertBox.innerText = "Por favor, introduce tu correo electrónico.";
        alertBox.style.display = 'block';
        return;
    }

    try {
        const r = await fetch(urlRecuperar, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'solicitar_codigo', email: email })
        });
        const res = await r.json();

        if (res.status === 'success') {
            // 🚀 SIMULACIÓN LOCAL: Mostramos el código de recuperación en una alerta para que el usuario pueda validarlo
            alert(`[SIMULACIÓN LOCAL - CORREO ENVIADO]\n\nSe ha enviado un código de recuperación a ${email}.\nCódigo generado: ${res.codigo_simulado}`);
            console.log("Código de recuperación simulado:", res.codigo_simulado);
            
            irAlPasoRecuperar(2);
        } else {
            alertBox.innerText = res.message;
            alertBox.style.display = 'block';
        }
    } catch (err) {
        console.error(err);
        alertBox.innerText = "Error de red al solicitar el código.";
        alertBox.style.display = 'block';
    }
}

async function validarCodigoRecuperacion() {
    const codigo = document.getElementById('recCodigo').value.trim();
    const alertBox = document.getElementById('alertBoxRecuperar');
    alertBox.style.display = 'none';

    if (!codigo || codigo.length !== 6) {
        alertBox.innerText = "Por favor, introduce el código de 6 dígitos.";
        alertBox.style.display = 'block';
        return;
    }

    try {
        const r = await fetch(urlRecuperar, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'validar_codigo', codigo: codigo })
        });
        const res = await r.json();

        if (res.status === 'success') {
            irAlPasoRecuperar(3);
        } else {
            alertBox.innerText = res.message;
            alertBox.style.display = 'block';
        }
    } catch (err) {
        console.error(err);
        alertBox.innerText = "Error de red al validar el código.";
        alertBox.style.display = 'block';
    }
}

async function guardarNuevaContrasena() {
    const pass = document.getElementById('recNuevaPass').value.trim();
    const conf = document.getElementById('recNuevaPassConf').value.trim();
    const alertBox = document.getElementById('alertBoxRecuperar');
    alertBox.style.display = 'none';

    if (pass !== conf) {
        alertBox.innerText = "Las contraseñas no coinciden.";
        alertBox.style.display = 'block';
        return;
    }

    // Validar complejidad en cliente
    const passErr = validarPasswordComplejidad(pass);
    if (passErr) {
        alertBox.innerText = "Contraseña: " + passErr;
        alertBox.style.display = 'block';
        return;
    }

    try {
        const r = await fetch(urlRecuperar, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'restablecer_contrasena', pass: pass })
        });
        const res = await r.json();

        if (res.status === 'success') {
            alert(res.message);
            cerrarModalRecuperar();
        } else {
            alertBox.innerText = res.message;
            alertBox.style.display = 'block';
        }
    } catch (err) {
        console.error(err);
        alertBox.innerText = "Error de red al guardar la nueva contraseña.";
        alertBox.style.display = 'block';
    }
}

function validarPasswordComplejidad(password) {
    if (password.length < 10) {
        return "Debe tener al menos 10 caracteres.";
    }
    if (!/[A-Z]/.test(password)) {
        return "Debe tener al menos una letra mayúscula.";
    }
    if (!/[a-z]/.test(password)) {
        return "Debe tener al menos una letra minúscula.";
    }
    if (!/[0-9]/.test(password)) {
        return "Debe tener al menos un número.";
    }
    if (!/[^a-zA-Z0-9]/.test(password)) {
        return "Debe tener al menos un carácter especial.";
    }
    
    // Repetidos idénticos
    for (let i = 0; i < password.length - 3; i++) {
        if (password[i] === password[i+1] && password[i] === password[i+2] && password[i] === password[i+3]) {
            return "No puede contener más de 3 caracteres idénticos consecutivos.";
        }
    }
    
    // Secuenciales consecutivos
    for (let i = 0; i < password.length - 3; i++) {
        let c1 = password.charCodeAt(i);
        let c2 = password.charCodeAt(i+1);
        let c3 = password.charCodeAt(i+2);
        let c4 = password.charCodeAt(i+3);
        
        if (c2 === c1 + 1 && c3 === c2 + 1 && c4 === c3 + 1) {
            return "No puede contener más de 3 letras o números consecutivos en orden ascendente (ej. '1234' o 'abcd').";
        }
        if (c2 === c1 - 1 && c3 === c2 - 1 && c4 === c3 - 1) {
            return "No puede contener más de 3 letras o números consecutivos en orden descendente (ej. '4321' o 'dcba').";
        }
    }
    return null;
}
