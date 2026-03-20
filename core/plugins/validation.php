<?php
/**
 * Input Validation System for Birkly Plugins
 * 
 * Provides comprehensive validation functions for plugin inputs.
 */

/**
 * Validate a value against a schema
 * 
 * @param mixed $value Value to validate
 * @param array $schema Validation schema:
 *   - 'type' (string): Data type
 *   - 'required' (bool): Whether field is required
 *   - 'default' (mixed): Default value if not provided
 *   - Additional type-specific options
 * 
 * @return array ['valid' => bool, 'value' => mixed, 'errors' => array]
 */
function validate_against_schema($value, $schema) {
    $errors = [];
    $valid = true;
    
    // Check required
    if (!empty($schema['required']) && ($value === null || $value === '')) {
        $errors[] = 'Field is required';
        return ['valid' => false, 'value' => null, 'errors' => $errors];
    }
    
    // Use default if value is empty and not required
    if (($value === null || $value === '') && isset($schema['default'])) {
        $value = $schema['default'];
    }
    
    // Skip validation if value is empty and not required
    if (($value === null || $value === '') && empty($schema['required'])) {
        return ['valid' => true, 'value' => $value, 'errors' => []];
    }
    
    $type = $schema['type'] ?? 'string';
    
    switch ($type) {
        case 'string':
            if (!is_string($value)) {
                $errors[] = 'Value must be a string';
                $valid = false;
            } else {
                if (isset($schema['min_length']) && strlen($value) < $schema['min_length']) {
                    $errors[] = "String must be at least {$schema['min_length']} characters";
                    $valid = false;
                }
                if (isset($schema['max_length']) && strlen($value) > $schema['max_length']) {
                    $errors[] = "String must be at most {$schema['max_length']} characters";
                    $valid = false;
                }
                if (isset($schema['pattern']) && !preg_match($schema['pattern'], $value)) {
                    $errors[] = 'String does not match required pattern';
                    $valid = false;
                }
                if (isset($schema['enum']) && !in_array($value, $schema['enum'])) {
                    $errors[] = 'Value must be one of: ' . implode(', ', $schema['enum']);
                    $valid = false;
                }
            }
            break;
            
        case 'integer':
        case 'int':
            $intValue = filter_var($value, FILTER_VALIDATE_INT);
            if ($intValue === false) {
                $errors[] = 'Value must be an integer';
                $valid = false;
            } else {
                $value = $intValue;
                if (isset($schema['minimum']) && $value < $schema['minimum']) {
                    $errors[] = "Value must be at least {$schema['minimum']}";
                    $valid = false;
                }
                if (isset($schema['maximum']) && $value > $schema['maximum']) {
                    $errors[] = "Value must be at most {$schema['maximum']}";
                    $valid = false;
                }
            }
            break;
            
        case 'number':
        case 'float':
            $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($floatValue === false) {
                $errors[] = 'Value must be a number';
                $valid = false;
            } else {
                $value = $floatValue;
                if (isset($schema['minimum']) && $value < $schema['minimum']) {
                    $errors[] = "Value must be at least {$schema['minimum']}";
                    $valid = false;
                }
                if (isset($schema['maximum']) && $value > $schema['maximum']) {
                    $errors[] = "Value must be at most {$schema['maximum']}";
                    $valid = false;
                }
            }
            break;
            
        case 'boolean':
        case 'bool':
            if (is_bool($value)) {
                // Already boolean
            } elseif (is_string($value)) {
                $value = in_array(strtolower($value), ['true', '1', 'yes', 'on']);
            } elseif (is_numeric($value)) {
                $value = (bool)$value;
            } else {
                $errors[] = 'Value must be a boolean';
                $valid = false;
            }
            break;
            
        case 'email':
            $email = filter_var($value, FILTER_VALIDATE_EMAIL);
            if ($email === false) {
                $errors[] = 'Value must be a valid email address';
                $valid = false;
            } else {
                $value = $email;
            }
            break;
            
        case 'url':
            $url = filter_var($value, FILTER_VALIDATE_URL);
            if ($url === false) {
                $errors[] = 'Value must be a valid URL';
                $valid = false;
            } else {
                $value = $url;
            }
            break;
            
        case 'array':
            if (!is_array($value)) {
                $errors[] = 'Value must be an array';
                $valid = false;
            } else {
                // Validate array items if schema provided
                if (isset($schema['items'])) {
                    $validatedArray = [];
                    foreach ($value as $index => $item) {
                        $itemResult = validate_against_schema($item, $schema['items']);
                        if (!$itemResult['valid']) {
                            $errors[] = "Item at index $index: " . implode(', ', $itemResult['errors']);
                            $valid = false;
                        } else {
                            $validatedArray[] = $itemResult['value'];
                        }
                    }
                    $value = $validatedArray;
                }
            }
            break;
            
        case 'object':
            if (!is_array($value) && !is_object($value)) {
                $errors[] = 'Value must be an object';
                $valid = false;
            } else {
                $value = (array)$value;
                // Validate object properties if schema provided
                if (isset($schema['properties'])) {
                    $validatedObject = [];
                    foreach ($schema['properties'] as $propName => $propSchema) {
                        $propValue = $value[$propName] ?? null;
                        $propResult = validate_against_schema($propValue, $propSchema);
                        if (!$propResult['valid']) {
                            $errors[] = "Property '$propName': " . implode(', ', $propResult['errors']);
                            $valid = false;
                        } else {
                            $validatedObject[$propName] = $propResult['value'];
                        }
                    }
                    $value = $validatedObject;
                }
            }
            break;
    }
    
    return ['valid' => $valid, 'value' => $value, 'errors' => $errors];
}

/**
 * Validate multiple values against a schema
 * 
 * @param array $data Data to validate
 * @param array $schema Schema definition (key => schema)
 * @return array ['valid' => bool, 'data' => array, 'errors' => array]
 */
function validate_data($data, $schema) {
    $validated = [];
    $errors = [];
    $allValid = true;
    
    foreach ($schema as $field => $fieldSchema) {
        $value = $data[$field] ?? null;
        $result = validate_against_schema($value, $fieldSchema);
        
        if (!$result['valid']) {
            $errors[$field] = $result['errors'];
            $allValid = false;
        }
        
        $validated[$field] = $result['value'];
    }
    
    return ['valid' => $allValid, 'data' => $validated, 'errors' => $errors];
}
