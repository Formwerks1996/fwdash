<?php

namespace App\Enums;

enum Permission: string
{
    case ViewFinancials = 'view financials';
    case EditBilling = 'edit billing';
    case EditOffice = 'edit office';
    case ImportData = 'import data';
    case ManageUsers = 'manage users';
}
