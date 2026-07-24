<?php
/**
 * Password Fieldtype for User Management Plugin
 * 
 * Provides secure password handling:
 * - Encrypts passwords for storage (admin cannot see actual password)
 * - Shows "Set Password" input for new passwords
 * - Shows "Send Password Reset Email" button for existing passwords
 * - Supports password strength validation
 */

namespace UserManagement\Fieldtypes;

// Require BaseField class if not already loaded
if (!class_exists('Birkly\FieldTypes\BaseField')) {
    require_once __DIR__ . '/../../../../fieldtypes/BaseField.php';
}

use Birkly\FieldTypes\BaseField;

class Password extends BaseField {
    protected $userService = null;
    protected $minLength = 8;
    protected $requireStrength = false;
    
    public function __construct(array $config) {
        parent::__construct($config);
        $this->minLength = $config['min_length'] ?? 8;
        $this->requireStrength = $config['require_strength'] ?? false;
        
        // Initialize user service if available
        if (class_exists('UserService')) {
            $this->userService = new \UserService();
        }
    }
    
    public function getType(): string {
        return 'password';
    }
    
    public function getCategory(): string {
        return 'User Management';
    }
    
    public function render(): string {
        $fieldName = htmlspecialchars($this->name);
        $value = $this->value ?? '';
        $hasExistingPassword = !empty($value);
        
        // Get user_id from entry context if available (for password reset)
        $entryId = $this->config['entry_id'] ?? '';
        $userId = $this->config['user_id'] ?? '';
        
        $html = '<div class="password-fieldtype" data-field-name="' . $fieldName . '" data-entry-id="' . htmlspecialchars($entryId) . '" data-user-id="' . htmlspecialchars($userId) . '">';
        
        // For new passwords (no existing value)
        if (!$hasExistingPassword) {
            $html .= '<div class="password-fieldtype-new">';
            $html .= '<input type="password" class="form-control password-fieldtype-input" name="' . $fieldName . '" placeholder="Enter new password" autocomplete="new-password">';
            $html .= '<small class="form-text text-muted password-fieldtype-hint">';
            $html .= 'Minimum ' . $this->minLength . ' characters';
            if ($this->requireStrength) {
                $html .= '. Must include uppercase, lowercase, number, and special character.';
            }
            $html .= '</small>';
            $html .= '<div class="password-fieldtype-strength" style="display: none;"></div>';
            $html .= '</div>';
        } else {
            // For existing passwords, show masked value and reset option
            $html .= '<div class="password-fieldtype-existing">';
            $html .= '<div class="password-fieldtype-masked">';
            $html .= '<input type="text" class="form-control" value="••••••••" readonly style="background-color: #f5f5f5;">';
            $html .= '<small class="form-text text-muted">Password is encrypted and cannot be viewed</small>';
            $html .= '</div>';
            $html .= '<div class="password-fieldtype-actions mt-2">';
            $html .= '<button type="button" class="btn btn-secondary btn-sm password-fieldtype-reset-email" data-user-id="' . htmlspecialchars($userId) . '">';
            $html .= '<span class="password-fieldtype-reset-icon">📧</span> Send Password Reset Email';
            $html .= '</button>';
            $html .= '<button type="button" class="btn btn-secondary btn-sm password-fieldtype-change ml-2">';
            $html .= 'Change Password';
            $html .= '</button>';
            $html .= '</div>';
            // Hidden input for new password (shown when "Change Password" is clicked)
            $html .= '<div class="password-fieldtype-change-form" style="display: none; margin-top: 1rem;">';
            $html .= '<input type="password" class="form-control password-fieldtype-input" name="' . $fieldName . '_new" placeholder="Enter new password" autocomplete="new-password">';
            $html .= '<small class="form-text text-muted password-fieldtype-hint">';
            $html .= 'Minimum ' . $this->minLength . ' characters';
            if ($this->requireStrength) {
                $html .= '. Must include uppercase, lowercase, number, and special character.';
            }
            $html .= '</small>';
            $html .= '<div class="password-fieldtype-strength" style="display: none;"></div>';
            $html .= '<button type="button" class="btn btn-secondary btn-sm mt-2 password-fieldtype-cancel-change">Cancel</button>';
            $html .= '</div>';
            // Store original encrypted password (will be replaced if new password is provided)
            $html .= '<input type="hidden" name="' . $fieldName . '" value="' . htmlspecialchars($value) . '">';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    public function validate($value): array {
        $errors = [];
        
        // If this is a new password field (no existing value), password is required
        if ($this->required && empty($value)) {
            $errors[] = 'Password is required';
            return $errors;
        }
        
        // If password is provided, validate it
        if (!empty($value)) {
            if (strlen($value) < $this->minLength) {
                $errors[] = "Password must be at least {$this->minLength} characters long";
            }
            
            if ($this->requireStrength) {
                $hasUpper = preg_match('/[A-Z]/', $value);
                $hasLower = preg_match('/[a-z]/', $value);
                $hasNumber = preg_match('/[0-9]/', $value);
                $hasSpecial = preg_match('/[^A-Za-z0-9]/', $value);
                
                if (!$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
                    $errors[] = 'Password must include uppercase, lowercase, number, and special character';
                }
            }
        }
        
        return $errors;
    }
    
    public function getJavaScript(): string {
        $jsFile = __DIR__ . '/fieldtype.js';
        if (file_exists($jsFile)) {
            return file_get_contents($jsFile);
        }
        return '';
    }
    
    public function getCSS(): string {
        return '
        .password-fieldtype {
            margin-bottom: 1rem;
        }
        .password-fieldtype-existing {
            margin-bottom: 0.5rem;
        }
        .password-fieldtype-masked input {
            font-family: monospace;
            letter-spacing: 0.1em;
        }
        .password-fieldtype-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .password-fieldtype-actions .ml-2 {
            margin-left: 0.5rem;
        }
        .password-fieldtype-reset-email {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .password-fieldtype-reset-icon {
            font-size: 0.9em;
        }
        .password-fieldtype-strength {
            margin-top: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .password-fieldtype-strength.weak {
            background-color: #fee;
            color: #c33;
        }
        .password-fieldtype-strength.medium {
            background-color: #ffeaa7;
            color: #856404;
        }
        .password-fieldtype-strength.strong {
            background-color: #d4edda;
            color: #155724;
        }
        .password-fieldtype-change-form {
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        ';
    }
    
    public function getAdminConfig(): array {
        return [
            'min_length' => [
                'type' => 'number',
                'label' => 'Minimum Length',
                'default' => 8,
                'description' => 'Minimum password length (default: 8)'
            ],
            'require_strength' => [
                'type' => 'boolean',
                'label' => 'Require Strong Password',
                'default' => false,
                'description' => 'Require uppercase, lowercase, number, and special character'
            ]
        ];
    }
    
    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'password'
        ];
    }
    
    public function getDefaultValue() {
        return '';
    }
    
    public function processValue($value) {
        // If value is empty, return empty string
        if (empty($value)) {
            return '';
        }
        
        // If value looks like it's already hashed (starts with $2y$, $2a$, $argon2id$, etc.), return as-is
        if (preg_match('/^\$2[ay]\$|\$argon2id\$/', $value)) {
            return $value;
        }
        
        // For profile entries that need to create users, we need to keep the password plain text
        // so it can be used to create the user account. The before_entry_save hook will handle
        // user creation and then remove the password from the entry.
        // 
        // For existing entries (with user_id), if a new password is provided, hash it.
        // However, profile entries typically don't store passwords after user creation.
        $entryId = $this->config['entry_id'] ?? '';
        $userId = $this->config['user_id'] ?? '';
        
        // If this is a new profile entry (no user_id), keep password plain text for user creation
        if (empty($userId) && empty($entryId)) {
            // This is a new entry, likely creating a user - keep plain text
            return $value;
        }
        
        // For existing entries or if user_id exists, hash the password
        if (!$this->userService) {
            // Fallback to PHP's password_hash if UserService not available
            return password_hash($value, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
        }
        
        return $this->userService->hashPassword($value);
    }
    
    public function processLoadedValue($value) {
        // Always return the encrypted value (never return plain text)
        return $value ?? '';
    }
    
    /**
     * Render a compact list/table cell representation for this field's value
     * Always masks password values for security
     */
    public function renderListCell($value, array $context = []): string {
        // Always mask password values in list views
        if (!empty($value)) {
            // Check if it's a hash (starts with $2y$, $2a$, $argon2id$, etc.)
            if (preg_match('/^\$2[ay]\$|\$argon2id\$/', $value)) {
                // It's a hashed password - show masked
                return '<span class="ft-list-text" style="font-family: monospace; letter-spacing: 0.1em; color: #666;">••••••••</span>';
            } else {
                // It might be plain text (shouldn't happen, but mask it anyway)
                return '<span class="ft-list-text" style="font-family: monospace; letter-spacing: 0.1em; color: #666;">••••••••</span>';
            }
        }
        
        // Empty password
        return '<span class="ft-list-empty">–</span>';
    }
    
    /**
     * Default list column width for password fields
     */
    public function getListColumnWidth(): string {
        return '120px'; // Password fields don't need much space
    }
}
