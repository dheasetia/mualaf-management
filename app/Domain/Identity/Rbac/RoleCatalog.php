<?php

declare(strict_types=1);

namespace App\Domain\Identity\Rbac;

use App\Domain\Identity\Enums\RoleScope;

/**
 * System roles and their permissions: the role × permission matrix of
 * 03-tenancy-authorization §3.7 in executable form. Changing a role here and
 * re-running RbacSeeder is the only way system roles change.
 */
final class RoleCatalog
{
    /**
     * @return array<string, array{name_ar: string, name_en: string, scope: RoleScope, permissions: list<string>}>
     */
    public static function roles(): array
    {
        return [
            // Platform (Islamic Center Dammam). No access to individual office data;
            // that needs a privileged access grant (04-identity-privacy §4.8).
            'super_admin' => self::platform('المشرف العام', 'Super Admin', [
                'tenants.view', 'tenants.create', 'tenants.update', 'tenants.activate', 'tenants.suspend',
                'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.assign_roles',
                'master.manage', 'documents.templates.manage', 'international.organizations.manage',
                'reports.view_national', 'reports.export', 'audit.view_national',
                'privileged.request', 'privileged.approve',
            ]),
            'central_admin' => self::platform('مسؤول المنصة', 'Central Admin', [
                'tenants.view', 'tenants.create', 'tenants.update', 'tenants.activate', 'tenants.suspend',
                'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.assign_roles',
                'master.manage', 'documents.templates.manage', 'international.organizations.manage',
                'converts.correct_origin', 'reports.view_national', 'audit.view_national', 'privileged.request',
            ]),
            // Also approves privileged access, so requests do not stall on a single
            // Super Admin; the requester never approves their own request.
            'central_auditor' => self::platform('المدقق المركزي', 'Central Auditor', [
                'tenants.view', 'users.view', 'reports.view_national', 'audit.view_national', 'privileged.approve',
            ]),
            'central_reporting' => self::platform('التقارير المركزية', 'Central Reporting', [
                'tenants.view', 'reports.view_national', 'reports.export',
            ]),
            'support_officer' => self::platform('مسؤول الدعم الفني', 'Support Officer', [
                'tenants.view', 'users.view',
            ]),

            // Office roles.
            'center_admin' => self::tenant('مدير المركز', 'Center Admin', [
                'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.assign_roles',
                'dais.view', 'dais.create', 'dais.update',
                'prospects.view', 'converts.view', 'converts.view_restricted', 'dawah.plans.view', 'budget.view', 'materials.view',
                'guidance.view', 'exams.view', 'certificates.view', 'documents.view', 'submissions.view',
                'transport.view', 'pilgrimage.view',
                'reports.view_office', 'reports.export', 'audit.view_office',
            ]),
            'head_of_dawah' => self::tenant('رئيس قسم الدعوة', 'Head of Dawah', [
                'dais.view', 'dais.view_workload',
                'prospects.view', 'prospects.create', 'prospects.update', 'prospects.assign_dai',
                'prospects.change_status', 'prospects.transfer',
                'converts.view', 'converts.update', 'converts.view_sensitive', 'converts.assign_dai',
                'converts.set_disclosure', 'converts.transfer', 'converts.review_transfer',
                'converts.view_restricted', 'converts.view_high_confidentiality', 'converts.change_status',
                'identity.national_search', 'duplicates.review',
                'followups.view', 'followups.create', 'followups.view_confidential',
                'dawah.activities.verify', 'dawah.plans.view', 'dawah.plans.review', 'dawah.plans.approve',
                'dawah.reports.verify', 'budget.view', 'materials.view', 'materials.create', 'materials.approve',
                'guidance.view', 'certificates.view', 'submissions.view',
                'transport.view', 'transport.approve', 'pilgrimage.view',
                'international.referrals.create', 'international.referrals.send',
                'reports.view_office',
            ]),
            'dai' => self::tenant('داعية', "Da'i", [
                'prospects.view_own', 'prospects.create', 'prospects.update', 'prospects.change_status',
                'converts.view_own', 'followups.view', 'followups.create',
                'dawah.activities.create', 'dawah.plans.view', 'dawah.plans.create', 'dawah.plans.submit',
                'dawah.reports.submit', 'materials.view', 'materials.create', 'materials.distribute', 'transport.request',
                'international.referrals.create',
            ]),
            'registration_officer' => self::tenant('موظف التسجيل', 'Registration Officer', [
                'prospects.view', 'converts.view', 'converts.create', 'converts.update',
                'identity.national_search', 'shahadah.record', 'documents.view', 'documents.upload',
            ]),
            'shahadah_verifier' => self::tenant('مدقق الشهادة', 'Shahadah Verifier', [
                'prospects.view', 'converts.view', 'identity.verify', 'identity.reveal', 'shahadah.verify',
                'documents.view', 'documents.verify',
            ]),
            'guidance_coordinator' => self::tenant('منسق التأهيل', 'Guidance Coordinator', [
                'converts.view', 'followups.view', 'guidance.view', 'guidance.programs.manage',
                'guidance.classes.manage', 'guidance.enroll', 'exams.view', 'certificates.request',
                'transport.request', 'reports.view_office',
            ]),
            'teacher' => self::tenant('معلم', 'Teacher', [
                // Only converts enrolled in the teacher's own classes (enforced in the guidance module).
                'converts.view_class', 'guidance.view', 'guidance.attendance.record', 'guidance.progress.record',
                'exams.grade',
            ]),
            'examination_officer' => self::tenant('مسؤول الاختبارات', 'Examination Officer', [
                'converts.view', 'guidance.view', 'exams.view', 'exams.manage', 'exams.grade', 'exams.finalize',
                'certificates.request',
            ]),
            'certificate_officer' => self::tenant('مسؤول الشهادات', 'Certificate Officer', [
                'converts.view', 'guidance.view', 'exams.view', 'certificates.view', 'certificates.issue',
                'documents.view', 'documents.generate',
            ]),
            'administrative_officer' => self::tenant('الموظف الإداري', 'Administrative Officer', [
                'converts.view', 'documents.view', 'documents.upload', 'documents.generate',
                'submissions.view', 'submissions.create', 'submissions.verify', 'submissions.process',
            ]),
            'approver' => self::tenant('المعتمد', 'Approver', [
                'converts.view', 'dawah.plans.view', 'budget.view', 'budget.approve',
                'certificates.view', 'certificates.approve', 'certificates.revoke',
                'submissions.view', 'submissions.approve', 'pilgrimage.view', 'pilgrimage.approve',
                'reports.view_office',
            ]),
            'transportation_officer' => self::tenant('مسؤول النقل', 'Transportation Officer', [
                'converts.view', 'dawah.plans.view', 'transport.view', 'transport.request', 'transport.approve',
                'transport.dispatch', 'transport.fleet.manage', 'budget.record_actual', 'pilgrimage.view',
            ]),
            'pilgrimage_officer' => self::tenant('مسؤول الحج والعمرة', 'Pilgrimage Officer', [
                'converts.view', 'transport.request', 'pilgrimage.view', 'pilgrimage.programs.manage',
                'pilgrimage.register', 'pilgrimage.check_eligibility',
            ]),
            'local_auditor' => self::tenant('المدقق المحلي', 'Local Auditor', [
                // Read-only across the office: every office-level view permission.
                ...PermissionCatalog::viewCodes(),
            ]),
        ];
    }

    /** @param  list<string>  $permissions */
    public static function requiresMfa(RoleScope $scope, array $permissions): bool
    {
        if ($scope === RoleScope::Platform) {
            return true;
        }

        foreach ($permissions as $code) {
            if (in_array(PermissionCatalog::split($code)['action'], PermissionCatalog::MFA_ACTIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $permissions
     * @return array{name_ar: string, name_en: string, scope: RoleScope, permissions: list<string>}
     */
    private static function platform(string $nameAr, string $nameEn, array $permissions): array
    {
        return ['name_ar' => $nameAr, 'name_en' => $nameEn, 'scope' => RoleScope::Platform, 'permissions' => $permissions];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{name_ar: string, name_en: string, scope: RoleScope, permissions: list<string>}
     */
    private static function tenant(string $nameAr, string $nameEn, array $permissions): array
    {
        return ['name_ar' => $nameAr, 'name_en' => $nameEn, 'scope' => RoleScope::Tenant, 'permissions' => $permissions];
    }
}
