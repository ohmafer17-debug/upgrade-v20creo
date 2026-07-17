<?php
// =================================================================
// VALIDACIONES GLOBALES: validaciones.php
// =================================================================

/**
 * Valida la complejidad de la contraseña según las reglas:
 * - Mínimo 10 caracteres
 * - Al menos una mayúscula
 * - Al menos una minúscula
 * - Al menos un número
 * - Al menos un carácter especial
 * - Máximo 3 caracteres idénticos o secuenciales consecutivos
 * 
 * @param string $password
 * @return string|null Retorna el mensaje de error o null si es válida
 */
function validarPasswordComplejidad($password) {
    if (strlen($password) < 10) {
        return "La contraseña debe tener al menos 10 caracteres.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "La contraseña debe tener al menos una letra mayúscula.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "La contraseña debe tener al menos una letra minúscula.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "La contraseña debe tener al menos un número.";
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return "La contraseña debe tener al menos un carácter especial (ej. #, $, @, etc.).";
    }
    
    $len = strlen($password);
    
    // 1. Validar idénticos consecutivos (ej: aaaa, 1111)
    for ($i = 0; $i < $len - 3; $i++) {
        if ($password[$i] === $password[$i+1] && $password[$i] === $password[$i+2] && $password[$i] === $password[$i+3]) {
            return "La contraseña no puede contener más de 3 caracteres idénticos consecutivos.";
        }
    }
    
    // 2. Validar secuenciales consecutivos ascendentes/descendentes (ej: 1234, abcd, dcba, 4321)
    for ($i = 0; $i < $len - 3; $i++) {
        $c1 = ord($password[$i]);
        $c2 = ord($password[$i+1]);
        $c3 = ord($password[$i+2]);
        $c4 = ord($password[$i+3]);
        
        // Ascendente (ej: c2 = c1 + 1)
        if ($c2 === $c1 + 1 && $c3 === $c2 + 1 && $c4 === $c3 + 1) {
            return "La contraseña no puede contener más de 3 letras o números consecutivos en orden ascendente (ej. '1234' o 'abcd').";
        }
        // Descendente (ej: c2 = c1 - 1)
        if ($c2 === $c1 - 1 && $c3 === $c2 - 1 && $c4 === $c3 - 1) {
            return "La contraseña no puede contener más de 3 letras o números consecutivos en orden descendente (ej. '4321' o 'dcba').";
        }
    }
    
    return null; // Válida
}
?>
