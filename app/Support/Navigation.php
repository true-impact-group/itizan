<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Sidebar links per role. "match" is the route-name pattern that marks the link as the current page.
 * Labels are Arabic source strings translated in the view.
 */
final class Navigation
{
    /** @return list<array{label: string, route: string, match: string}> */
    public static function for(Role $role): array
    {
        return match ($role) {
            Role::Client => [
                self::item('الرئيسية', 'account.dashboard', 'account.dashboard'),
                self::item('المواعيد والاستشارات', 'account.appointments.index', 'account.appointments.*'),
                self::item('التقييم الطبي', 'account.assessment.show', 'account.assessment.*'),
                self::item('خطتي', 'account.plan.show', 'account.plan.*'),
                self::item('الوزن والتقدم', 'account.progress.index', 'account.progress.*'),
                self::item('الأدوية والجرعات', 'account.medications.index', 'account.medications.*'),
                self::item('الأعراض الجانبية', 'account.side-effects.index', 'account.side-effects.*'),
                self::item('التحاليل', 'account.labs.index', 'account.labs.*'),
                self::item('طلباتي', 'account.orders.index', 'account.orders.*'),
                self::item('التواصل مع الفريق', 'account.contact-requests.index', 'account.contact-requests.*'),
                self::item('الاشتراك والفواتير', 'account.subscription.show', 'account.subscription.*'),
                self::item('الدعم والشكاوى', 'account.tickets.index', 'account.tickets.*'),
                self::item('ملفي الشخصي', 'account.profile.edit', 'account.profile.*'),
            ],
            Role::Doctor => [
                self::item('لوحة الطبيب', 'practitioner.dashboard', 'practitioner.dashboard'),
                self::item('المواعيد', 'practitioner.appointments.index', 'practitioner.appointments.*'),
                self::item('التقييمات بانتظار المراجعة', 'practitioner.assessments.index', 'practitioner.assessments.*'),
                self::item('العملاء', 'practitioner.clients.index', 'practitioner.clients.*'),
                self::item('نتائج التحاليل', 'practitioner.labs.index', 'practitioner.labs.*'),
                self::item('طلبات التواصل', 'care.contact-requests.index', 'care.contact-requests.*'),
                self::item('أوقات التوفر', 'practitioner.availability.edit', 'practitioner.availability.*'),
            ],
            Role::Dietitian => [
                self::item('لوحة أخصائي التغذية', 'practitioner.dashboard', 'practitioner.dashboard'),
                self::item('العملاء', 'practitioner.clients.index', 'practitioner.clients.*'),
            ],
            Role::CareTeam => [
                self::item('لوحة فريق الرعاية', 'care.dashboard', 'care.dashboard'),
                self::item('طلبات التواصل', 'care.contact-requests.index', 'care.contact-requests.*'),
                self::item('الأعراض الجانبية', 'care.side-effects.index', 'care.side-effects.*'),
            ],
            Role::Pharmacy => [
                self::item('طلبات الصيدلية', 'pharmacy.orders.index', 'pharmacy.orders.*'),
            ],
            Role::Delivery => [
                self::item('طلبات التوصيل', 'delivery.orders.index', 'delivery.orders.*'),
            ],
            Role::Support => [
                self::item('لوحة التحكم', 'admin.dashboard', 'admin.dashboard'),
                self::item('العملاء', 'admin.clients.index', 'admin.clients.*'),
                self::item('الاشتراكات', 'admin.subscriptions.index', 'admin.subscriptions.*'),
                self::item('المواعيد', 'admin.appointments.index', 'admin.appointments.*'),
                self::item('الدعم والشكاوى', 'admin.tickets.index', 'admin.tickets.*'),
                self::item('متابعة العمليات', 'admin.operations', 'admin.operations'),
            ],
            Role::Admin => [
                self::item('لوحة التحكم', 'admin.dashboard', 'admin.dashboard'),
                self::item('العملاء', 'admin.clients.index', 'admin.clients.*'),
                self::item('الاشتراكات', 'admin.subscriptions.index', 'admin.subscriptions.*'),
                self::item('المدفوعات', 'admin.payments.index', 'admin.payments.*'),
                self::item('الباقات والأسعار', 'admin.packages.index', 'admin.packages.*'),
                self::item('المواعيد', 'admin.appointments.index', 'admin.appointments.*'),
                self::item('متابعة العمليات', 'admin.operations', 'admin.operations'),
                self::item('الدعم والشكاوى', 'admin.tickets.index', 'admin.tickets.*'),
                self::item('الأطباء والمختصون', 'admin.practitioners.index', 'admin.practitioners.*'),
                self::item('الموظفون والصلاحيات', 'admin.staff.index', 'admin.staff.*'),
                self::item('المنتجات', 'admin.products.index', 'admin.products.*'),
                self::item('الصيدليات', 'admin.pharmacies.index', 'admin.pharmacies.*'),
                self::item('الصفحات والسياسات', 'admin.pages.index', 'admin.pages.*'),
                self::item('الأسئلة الشائعة', 'admin.faqs.index', 'admin.faqs.*'),
                self::item('التقارير', 'admin.reports', 'admin.reports'),
                self::item('سجل التدقيق', 'admin.audit-logs', 'admin.audit-logs'),
            ],
        };
    }

    /** @return array{label: string, route: string, match: string} */
    private static function item(string $label, string $route, string $match): array
    {
        return ['label' => $label, 'route' => $route, 'match' => $match];
    }
}
