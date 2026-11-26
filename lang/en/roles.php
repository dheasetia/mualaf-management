<?php

declare(strict_types=1);

return [
    'model' => 'role',
    'plural' => 'Roles and permissions',
    'fields' => [
        'name' => 'Role',
        'name_ar' => 'Arabic name',
        'name_en' => 'English name',
        'code' => 'Code',
        'scope' => 'Scope',
        'type' => 'Type',
        'description' => 'Description',
        'permissions' => 'Permissions',
        'permissions_count' => 'Permissions',
        'holders' => 'Users',
        'permissions_help' => 'Only permissions that a system role of the same scope holds are listed.',
        'scope_help' => 'The scope cannot be changed after the role is created.',
    ],
    'scopes' => [
        'platform' => 'Platform',
        'tenant' => 'Center',
    ],
    'types' => [
        'system' => 'System role',
        'custom' => 'Custom role',
    ],
    'system_note' => 'System roles are approved by the Islamic Center Dammam and change only through code updates. Create a custom role for other needs.',
    'errors' => [
        'system_role_locked' => 'System roles are read-only.',
        'code_taken' => 'A role with the same English name exists. Choose another name.',
        'permission_not_allowed' => 'Permission :permission is not available for this scope.',
        'permissions_required' => 'Choose at least one permission.',
        'in_use' => 'This role has been assigned before, so it cannot be deleted. Revoke it from users instead.',
    ],
];
