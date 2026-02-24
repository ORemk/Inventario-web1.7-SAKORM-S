<?php
/**
 * validators.php - Funciones de validación centralizadas
 * 
 * Proporciona validaciones reutilizables para todos los endpoints
 * Evita código duplicado en usuarios.php, clientes.php, proveedores.php
 */

/**
 * Valida que un email sea válido
 * 
 * @param string $email - Email a validar
 * @return bool - true si es válido, false si no
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida que un teléfono tenga formato válido
 * 
 * @param string $telefono - Teléfono a validar
 * @return bool - true si es válido (10+ dígitos)
 */
function validarTelefono($telefono) {
    if (empty($telefono)) {
        return true; // Campo opcional
    }
    $solo_digitos = preg_replace('/[^0-9]/', '', $telefono);
    return strlen($solo_digitos) >= 10;
}

/**
 * Valida que una contraseña sea segura
 * 
 * @param string $password - Contraseña a validar
 * @return array - Array de errores (vacío si válida)
 */
function validarPassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una mayúscula';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una minúscula';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos un número';
    }
    
    return $errors;
}

/**
 * Valida un nombre (uso general)
 * 
 * @param string $nombre - Nombre a validar
 * @param int $maxLength - Longitud máxima (default 100)
 * @return array - Array de errores (vacío si válido)
 */
function validarNombre($nombre, $maxLength = 100) {
    $errors = [];
    
    if (empty($nombre)) {
        $errors[] = 'El nombre es requerido';
    } elseif (strlen($nombre) > $maxLength) {
        $errors[] = "El nombre no puede exceder $maxLength caracteres";
    }
    
    return $errors;
}

/**
 * Valida un campo de texto genérico
 * 
 * @param string $value - Valor a validar
 * @param string $fieldName - Nombre del campo (para mensaje)
 * @param int $maxLength - Longitud máxima
 * @param bool $required - Si es requerido
 * @return array - Array de errores (vacío si válido)
 */
function validarCampoTexto($value, $fieldName = 'Campo', $maxLength = 150, $required = true) {
    $errors = [];
    
    if (empty($value) && $required) {
        $errors[] = "$fieldName es requerido";
    } elseif (!empty($value) && strlen($value) > $maxLength) {
        $errors[] = "$fieldName no puede exceder $maxLength caracteres";
    }
    
    return $errors;
}

/**
 * Valida que un valor sea un número válido
 * 
 * @param mixed $value - Valor a validar
 * @param string $fieldName - Nombre del campo (para mensaje)
 * @param bool $allowNegative - Si permite negativos
 * @param bool $allowDecimal - Si permite decimales
 * @return array - Array de errores (vacío si válido)
 */
function validarNumero($value, $fieldName = 'Número', $allowNegative = false, $allowDecimal = true) {
    $errors = [];
    
    if (empty($value) && $value !== '0' && $value !== 0) {
        return $errors; // Campo opcional por defecto
    }
    
    if (!is_numeric($value)) {
        $errors[] = "$fieldName debe ser un número válido";
    } else {
        $numValue = $allowDecimal ? (float)$value : (int)$value;
        
        if (!$allowNegative && $numValue < 0) {
            $errors[] = "$fieldName no puede ser negativo";
        }
    }
    
    return $errors;
}

/**
 * Valida una fecha en formato YYYY-MM-DD
 * 
 * @param string $date - Fecha a validar
 * @param bool $required - Si es requerida
 * @return array - Array de errores (vacío si válida)
 */
function validarFecha($date, $required = false) {
    $errors = [];
    
    if (empty($date)) {
        if ($required) {
            $errors[] = 'La fecha es requerida';
        }
        return $errors;
    }
    
    $format = 'Y-m-d';
    $d = \DateTime::createFromFormat($format, $date);
    
    if (!($d && $d->format($format) === $date)) {
        $errors[] = 'La fecha debe estar en formato YYYY-MM-DD';
    }
    
    return $errors;
}

/**
 * Valida el formato de una dirección
 * 
 * @param string $direccion - Dirección a validar
 * @return array - Array de errores (vacío si válida)
 */
function validarDireccion($direccion) {
    $errors = [];
    
    if (empty($direccion)) {
        $errors[] = 'La dirección es requerida';
    } elseif (strlen($direccion) < 5) {
        $errors[] = 'La dirección debe tener al menos 5 caracteres';
    } elseif (strlen($direccion) > 200) {
        $errors[] = 'La dirección no puede exceder 200 caracteres';
    }
    
    return $errors;
}

/**
 * Valida un código (para productos, etc.)
 * 
 * @param string $codigo - Código a validar
 * @return array - Array de errores (vacío si válido)
 */
function validarCodigo($codigo) {
    $errors = [];
    
    if (empty($codigo)) {
        $errors[] = 'El código es requerido';
    } elseif (strlen($codigo) > 50) {
        $errors[] = 'El código no puede exceder 50 caracteres';
    } elseif (!preg_match('/^[a-zA-Z0-9\-_]+$/', $codigo)) {
        $errors[] = 'El código solo puede contener letras, números, guiones y guiones bajos';
    }
    
    return $errors;
}

/**
 * Valida que no haya errores en un array de validaciones
 * Propósito: Simplificar verificación múltiple
 * 
 * @param array $validationResults - Array de resultados de validaciones
 * @return array - Array combinado de todos los errores
 */
function combinarErrores($validationResults) {
    $allErrors = [];
    
    foreach ($validationResults as $errors) {
        if (is_array($errors) && !empty($errors)) {
            $allErrors = array_merge($allErrors, $errors);
        }
    }
    
    return $allErrors;
}
