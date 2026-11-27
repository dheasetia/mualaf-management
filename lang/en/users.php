<?php

declare(strict_types=1);

return [
    'model' => 'user',
    'plural' => 'Users',
    'staff_plural' => 'Center staff',
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'new_password' => 'New password',
        'new_password_help' => 'Leave empty to keep the current password. Give the password to the user yourself.',
        'password_help' => 'Give the password to the user yourself; they can change it on their profile.',
        'roles' => 'Roles',
        'office_roles' => 'Center roles',
        'platform_roles' => 'Platform roles',
        'offices' => 'Centers',
        'office' => 'Center',
        'status' => 'Status',
        'last_login' => 'Last sign-in',
        'reason' => 'Reason',
        'reason_help' => 'Required when removing a role or taking the user out of the center.',
    ],
    'status' => [
        'active' => 'Active',
        'inactive' => 'Deactivated',
    ],
    'actions' => [
        'create' => 'New user',
        'add_existing' => 'Add existing user',
        'add_existing_help' => 'For staff who already have an account in another center or on the platform.',
        'roles' => 'Roles',
        'office_roles' => 'Center roles',
        'remove_from_office' => 'Remove from center',
        'remove_from_office_confirm' => 'All of this user\'s roles in this center are revoked and their membership ends. Their account and roles elsewhere stay.',
        'activate' => 'Activate account',
        'deactivate' => 'Deactivate account',
        'deactivate_confirm' => 'The user cannot sign in to any panel until the account is activated again.',
    ],
    'notifications' => [
        'saved' => 'Saved.',
        'roles_saved' => 'Roles updated.',
        'removed' => 'The user was removed from the center.',
        'activated' => 'Account activated.',
        'deactivated' => 'Account deactivated.',
    ],
    'errors' => [
        'own_account' => 'You cannot change your own account or roles here. Use your profile page.',
        'email_taken' => 'This email belongs to another account. Use "Add existing user" to add them to the center.',
        'user_not_found' => 'No account has this email.',
        'already_in_office' => 'This user is already staff of this center.',
        'not_in_office' => 'This user is not staff of this center.',
        'revoke_reason_required' => 'Write why the role is being removed.',
        'role_scope_mismatch' => 'Choose roles of the right kind for this place.',
    ],
];
