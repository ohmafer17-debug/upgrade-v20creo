document.addEventListener("DOMContentLoaded", () => {
    verificarBloqueoUps();
});

document.getElementById('loginUpsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (verificarBloqueoUps()) return;

    const alertContainer = document.getElementById('alertContainer');
    alertContainer.style.display = "none";

    const payload = {
        email: document.getElementById('email').value.trim(),
        pass: document.getElementById('password').value.trim()
    };

    try {
        // 🚀 DETECCIÓN DINÁMICA DE ENTORNO (LOCAL VS PRODUCCIÓN)
        const base_url = window.location.origin + (window.location.hostname === 'localhost' ? '/upgrade_systems' : '');

        const r = await fetch(`${base_url}/controllers/login_procesar.php`, { 
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!r.ok) { throw new Error("HTTP estatus: " + r.status); }
        const resultado = await r.json();

        if (resultado.status === 'success') {
            sessionStorage.removeItem('ups_intentos_fallidos');
            if (resultado.id_cliente === 'UPS-STAFF' || resultado.rol_usuario === 'Administrador' || resultado.rol_usuario === 'Usuario Estándar' || resultado.rol_usuario === 'Invitado') {
                localStorage.setItem('ups_sesion_id', resultado.id_cliente);
                localStorage.setItem('ups_sesion_nombre', resultado.nombre_usuario);
                localStorage.setItem('ups_sesion_email', resultado.data.email);
                localStorage.setItem('cliente_sesion_rol', resultado.rol_usuario);
                
                // 🚀 REDIRECCIÓN MODULARIZADA
                window.location.href = './index.html';
            }
        } else {
            if (resultado.message.includes("bloqueado") || resultado.message.includes("intentos fallidos")) {
                const segundos = parseInt(resultado.message.replace(/\D/g, "")) || 15;
                const tiempoBloqueoHasta = Date.now() + (segundos * 1000);
                localStorage.setItem('ups_bloqueado_hasta', tiempoBloqueoHasta);
                verificarBloqueoUps();
            } else {
                registrarIntentoFallidoUps(resultado.message);
            }
        }
    } catch (error) {
        console.error(error);
        alertContainer.innerText = "Error de enlace con el backend.";
        alertContainer.style.display = "block";
    }
});

function registrarIntentoFallidoUps(mensajeServidor) {
    const alertContainer = document.getElementById('alertContainer');
    let intentos = parseInt(sessionStorage.getItem('ups_intentos_fallidos')) || 0;
    intentos++;
    sessionStorage.setItem('ups_intentos_fallidos', intentos);

    if (intentos >= 3) {
        const tiempoBloqueoHasta = Date.now() + 15000; // 15 segundos
        localStorage.setItem('ups_bloqueado_hasta', tiempoBloqueoHasta);
        verificarBloqueoUps();
    } else {
        alertContainer.innerHTML = `<strong>Error:</strong> ${mensajeServidor}<br><span style="font-size:0.85rem; font-weight:600; color:#ef4444;">Intentos restantes: ${3 - intentos}</span>`;
        alertContainer.style.display = "block";
    }
}

function verificarBloqueoUps() {
    const btn = document.getElementById('btnSubmitUps');
    const alertContainer = document.getElementById('alertContainer');
    const bloqueoHasta = localStorage.getItem('ups_bloqueado_hasta');

    if (bloqueoHasta && Date.now() < bloqueoHasta) {
        btn.disabled = true;
        const intervalo = setInterval(() => {
            const tiempoRestante = Math.ceil((bloqueoHasta - Date.now()) / 1000);
            if (tiempoRestante <= 0) {
                clearInterval(intervalo);
                localStorage.removeItem('ups_bloqueado_hasta');
                sessionStorage.removeItem('ups_intentos_fallidos');
                btn.disabled = false;
                btn.querySelector('span').innerText = "Ingresar al Panel Máster";
                alertContainer.style.display = "none";
            } else {
                btn.querySelector('span').innerText = `Bloqueado (${tiempoRestante}s)`;
                alertContainer.innerHTML = `<strong>Consola Bloqueada:</strong> Inténtalo de nuevo en ${tiempoRestante} segundos.`;
                alertContainer.style.display = "block";
            }
        }, 1000);
        return true;
    }
    return false;
}

// --- CONTROL DEL MODAL DE RECUPERACIÓN ---
const base_url_rec = window.location.origin + (window.location.hostname === 'localhost' ? '/upgrade_systems' : '');
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
