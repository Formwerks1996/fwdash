<?php

namespace App\Enums;

enum Permission: string
{
    case ViewProjects = 'view projects';
    case ViewOffice = 'view office';
    case ViewAlerts = 'view alerts';
    case ViewFinancials = 'view financials';
    case EditBilling = 'edit billing';
    case EditOffice = 'edit office';
    case ViewBusinessDevelopment = 'view business development';
    case EditBusinessDevelopment = 'edit business development';
    case EditTeam = 'edit team';
    case ImportData = 'import data';
    case ManageUsers = 'manage users';
    case ViewTimesheets = 'view timesheets';
    case ViewAllTimesheets = 'view all timesheets';
}
