<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<int, Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Manager => [
                Permission::ViewProjects,
                Permission::ViewPmDesk,
                Permission::ViewAlerts,
                Permission::ViewOffice,
                Permission::ViewFinancials,
                Permission::EditBilling,
                Permission::EditOffice,
                Permission::ManageStaffCosts,
                Permission::ViewBusinessDevelopment,
                Permission::EditBusinessDevelopment,
                Permission::EditTeam,
                Permission::ViewTimesheets,
            ],
            self::Staff => [Permission::ViewProjects, Permission::ViewPmDesk, Permission::ViewAlerts, Permission::ViewTimesheets],
        };
    }
}
