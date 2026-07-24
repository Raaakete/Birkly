<?php
/**
 * User Fieldtype for User Management Plugin
 * 
 * Allows selecting one or multiple users from the user management system.
 * Stores user IDs (string for single, array for multiple).
 */

namespace UserManagement\Fieldtypes;

// Require BaseField class if not already loaded
if (!class_exists('Birkly\FieldTypes\BaseField')) {
    require_once __DIR__ . '/../../../../fieldtypes/BaseField.php';
}

use Birkly\FieldTypes\BaseField;

class User extends BaseField {
    protected $multiple = false;
    protected $allowRoles = []; // Filter by roles
    protected $userService = null;
    
    public function __construct(array $config) {
        parent::__construct($config);
        $this->multiple = $config['multiple'] ?? false;
        $this->allowRoles = $config['allow_roles'] ?? [];
        
        // Initialize user service if available
        // UserService is loaded by plugin.php, should be available
        if (class_exists('UserService')) {
            $this->userService = new \UserService();
        }
    }
    
    public function getType(): string {
        return 'user';
    }
    
    public function getCategory(): string {
        return 'User Management';
    }
    
    public function render(): string {
        // Load users from user management plugin
        $users = $this->getAvailableUsers();
        $value = $this->value ?? ($this->multiple ? [] : '');

        $selectedIds = $this->multiple
            ? (is_array($value) ? $value : (empty($value) ? [] : [$value]))
            : (empty($value) ? [] : [(string)$value]);

        // Build map for labels
        $userMap = [];
        foreach ($users as $u) {
            $id = (string)($u['id'] ?? '');
            if ($id === '') continue;
            $label = (string)($u['name'] ?? $u['username'] ?? $u['email'] ?? $id);
            $email = (string)($u['email'] ?? '');
            if ($email !== '' && stripos($label, $email) === false) {
                $label .= " ({$email})";
            }
            $userMap[$id] = $label;
        }

        $fieldName = htmlspecialchars($this->name);
        $multiple = $this->multiple ? '1' : '0';

        $html = '<div class="user-fieldtype" data-field-name="' . $fieldName . '" data-multiple="' . $multiple . '">';

        // Hidden inputs (authoritative values submitted)
        $html .= '<div class="user-fieldtype-hidden-inputs">';
        if ($this->multiple) {
            foreach ($selectedIds as $sid) {
                $sid = (string)$sid;
                if ($sid === '') continue;
                $html .= '<input type="hidden" class="user-fieldtype-hidden" name="' . $fieldName . '[]" value="' . htmlspecialchars($sid) . '">';
            }
        } else {
            $sid = $selectedIds[0] ?? '';
            $html .= '<input type="hidden" class="user-fieldtype-hidden" name="' . $fieldName . '" value="' . htmlspecialchars((string)$sid) . '">';
        }
        $html .= '</div>';

        // Controls (add form)
        $html .= '<div class="user-fieldtype-controls">';
        $html .= '<input type="text" class="form-control user-fieldtype-search" placeholder="Search users..." autocomplete="off">';
        $html .= '<div class="user-fieldtype-add-row">';
        $html .= '<select class="form-control user-fieldtype-select" aria-label="Select user to add">';
        $html .= '<option value="">-- Select User --</option>';
        foreach ($users as $u) {
            $id = (string)($u['id'] ?? '');
            if ($id === '') continue;
            $label = $userMap[$id] ?? $id;
            $html .= '<option value="' . htmlspecialchars($id) . '">' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select>';
        $html .= '<button type="button" class="btn btn-secondary user-fieldtype-add">Add</button>';
        if (!$this->multiple) {
            $html .= '<button type="button" class="btn btn-secondary user-fieldtype-clear">Clear</button>';
        }
        $html .= '</div>';
        $html .= '</div>';

        // Selected list (below add form, with max-height and scroll)
        $html .= '<div class="user-fieldtype-selected" aria-live="polite">';
        foreach ($selectedIds as $sid) {
            $sid = (string)$sid;
            if ($sid === '') continue;
            $label = $userMap[$sid] ?? ("Unknown user ({$sid})");
            $html .= '<span class="user-fieldtype-chip" data-user-id="' . htmlspecialchars($sid) . '">';
            $html .= '<span class="user-fieldtype-chip-label">' . htmlspecialchars($label) . '</span>';
            $html .= '<button type="button" class="btn user-fieldtype-remove" data-user-id="' . htmlspecialchars($sid) . '" aria-label="Remove user">×</button>';
            $html .= '</span>';
        }
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }
    
    public function validate($value): array {
        $errors = [];
        
        if ($this->required) {
            if ($this->multiple) {
                if (empty($value) || !is_array($value) || count($value) === 0) {
                    $errors[] = 'At least one user must be selected';
                }
            } else {
                if (empty($value)) {
                    $errors[] = 'A user must be selected';
                }
            }
        }
        
        // Validate user IDs exist
        $userIds = $this->multiple ? (is_array($value) ? $value : []) : (empty($value) ? [] : [$value]);
        foreach ($userIds as $userId) {
            if (!empty($userId) && !$this->userExists($userId)) {
                $errors[] = "User ID '$userId' does not exist";
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
        .user-fieldtype {
            margin-bottom: 1rem;
        }
        .user-fieldtype-controls {
            margin-top: 0.5rem;
        }
        .user-fieldtype-add-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.5rem;
        }
        .user-fieldtype-add-row .user-fieldtype-select {
            flex: 1 1 auto;
            min-width: 180px;
        }
        .user-fieldtype-selected {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.75rem;
            padding: 0.5rem;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 4px;
            background: rgba(0,0,0,0.01);
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .user-fieldtype-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.4rem;
            border: 1px solid rgba(0,0,0,0.12);
            border-radius: 999px;
            background: rgba(0,0,0,0.03);
            max-width: 100%;
        }
        .user-fieldtype-chip-label {
            font-size: 0.9rem;
            line-height: 1.1;
            max-width: 520px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .user-fieldtype-remove {
            padding: 0;
            width: 22px;
            height: 22px;
            line-height: 20px;
            border-radius: 999px;
        }
        ';
    }
    
    public function getAdminConfig(): array {
        return [
            'multiple' => [
                'type' => 'boolean',
                'label' => 'Allow Multiple Users',
                'default' => false,
                'description' => 'Allow selecting multiple users'
            ],
            'allow_roles' => [
                'type' => 'multiselect',
                'label' => 'Filter by Roles',
                'options' => ['member', 'subscriber', 'premium', 'vip'],
                'default' => [],
                'description' => 'Only show users with these roles (leave empty for all users)'
            ]
        ];
    }
    
    public function getSchema(): array {
        $schema = [
            'type' => $this->multiple ? 'array' : 'string',
        ];
        
        if ($this->multiple) {
            $schema['items'] = ['type' => 'string'];
        }
        
        return $schema;
    }
    
    public function getDefaultValue() {
        return $this->multiple ? [] : '';
    }
    
    public function processValue($value) {
        // Store as array if multiple, string if single
        if ($this->multiple) {
            if (is_array($value)) {
                // Filter out empty values
                return array_filter($value, function($v) {
                    return !empty($v);
                });
            }
            return empty($value) ? [] : [$value];
        }
        
        // Single value
        if (is_array($value)) {
            return $value[0] ?? '';
        }
        return $value ?? '';
    }
    
    public function processLoadedValue($value) {
        // Ensure correct format when loading
        return $this->processValue($value);
    }
    
    // Helper methods
    protected function getAvailableUsers() {
        // Get users from user management plugin
        if (!$this->userService) {
            return [];
        }
        
        try {
            $users = $this->userService->listUsers(['status' => 'active', 'deleted' => false]);
            
            // Filter by roles if specified
            if (!empty($this->allowRoles)) {
                $users = array_filter($users, function($user) {
                    return in_array($user['role'] ?? '', $this->allowRoles);
                });
            }
            
            // Sort by name
            usort($users, function($a, $b) {
                $nameA = $a['name'] ?? $a['email'] ?? '';
                $nameB = $b['name'] ?? $b['email'] ?? '';
                return strcasecmp($nameA, $nameB);
            });
            
            return $users;
        } catch (Exception $e) {
            error_log("Error loading users for fieldtype: " . $e->getMessage());
            return [];
        }
    }
    
    protected function userExists($userId) {
        if (!$this->userService) {
            return false;
        }
        
        try {
            $user = $this->userService->getUser($userId);
            return $user !== null;
        } catch (Exception $e) {
            return false;
        }
    }
}
