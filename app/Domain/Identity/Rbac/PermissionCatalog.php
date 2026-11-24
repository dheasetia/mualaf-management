<?php

declare(strict_types=1);

namespace App\Domain\Identity\Rbac;

/**
 * Every permission the platform knows, as `module.action` or `module.resource.action`
 * (03-tenancy-authorization §3.6). This list is the source of truth: RbacSeeder
 * syncs it to the database and the Gate only answers for codes listed here.
 */
final class PermissionCatalog
{
    /** @var list<string> */
    public const array CODES = [
        'tenants.view', 'tenants.create', 'tenants.update', 'tenants.activate', 'tenants.suspend',
        'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.assign_roles',
        'master.manage',
        'dais.view', 'dais.create', 'dais.update', 'dais.view_workload',
        'prospects.view', 'prospects.view_own', 'prospects.create', 'prospects.update',
        'prospects.assign_dai', 'prospects.change_status', 'prospects.transfer',
        'converts.view', 'converts.view_own', 'converts.view_class', 'converts.create', 'converts.update', 'converts.view_sensitive',
        'converts.assign_dai', 'converts.set_disclosure', 'converts.transfer', 'converts.review_transfer',
        'converts.correct_origin', 'converts.view_restricted', 'converts.view_high_confidentiality', 'converts.change_status',
        'converts.import',
        'identity.national_search', 'identity.verify', 'identity.reveal',
        'shahadah.record', 'shahadah.verify', 'shahadah.request_void', 'shahadah.approve_void',
        'duplicates.review',
        'followups.view', 'followups.create', 'followups.view_confidential',
        'dawah.activities.create', 'dawah.activities.verify',
        'dawah.plans.view', 'dawah.plans.create', 'dawah.plans.submit', 'dawah.plans.review', 'dawah.plans.approve',
        'dawah.reports.submit', 'dawah.reports.verify',
        'budget.view', 'budget.approve', 'budget.record_actual',
        'materials.view', 'materials.create', 'materials.approve', 'materials.distribute',
        'guidance.view', 'guidance.programs.manage', 'guidance.classes.manage', 'guidance.enroll',
        'guidance.attendance.record', 'guidance.progress.record',
        'exams.view', 'exams.manage', 'exams.grade', 'exams.finalize',
        'certificates.view', 'certificates.request', 'certificates.approve', 'certificates.issue', 'certificates.revoke',
        'documents.view', 'documents.upload', 'documents.verify', 'documents.generate', 'documents.templates.manage',
        'submissions.view', 'submissions.create', 'submissions.verify', 'submissions.approve', 'submissions.process',
        'transport.view', 'transport.request', 'transport.approve', 'transport.dispatch', 'transport.fleet.manage',
        'pilgrimage.view', 'pilgrimage.programs.manage', 'pilgrimage.register', 'pilgrimage.check_eligibility',
        'pilgrimage.approve',
        'international.referrals.create', 'international.referrals.send', 'international.organizations.manage',
        'reports.view_office', 'reports.view_national', 'reports.export',
        'audit.view_office', 'audit.view_national',
        'privileged.request', 'privileged.approve',
    ];

    /**
     * Actions that make a role high-privilege: holders must use MFA
     * (13-infrastructure-security §13.5).
     *
     * @var list<string>
     */
    public const array MFA_ACTIONS = [
        'assign_roles', 'approve', 'verify', 'reveal', 'review_transfer', 'finalize', 'issue', 'revoke',
        'set_disclosure', 'view_sensitive', 'view_confidential', 'correct_origin', 'approve_void',
    ];

    public static function exists(string $code): bool
    {
        return in_array($code, self::CODES, true);
    }

    /** @return array{module: string, action: string} */
    public static function split(string $code): array
    {
        $position = (int) strrpos($code, '.');

        return [
            'module' => substr($code, 0, $position),
            'action' => substr($code, $position + 1),
        ];
    }

    /**
     * Every code ending in `.view` (or `.view_*` office reads) — used for read-only roles.
     *
     * @return list<string>
     */
    public static function viewCodes(): array
    {
        return array_values(array_filter(
            self::CODES,
            fn (string $code): bool => in_array(self::split($code)['action'], ['view', 'view_office'], true),
        ));
    }
}
