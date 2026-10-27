<?php

/*
 * The sidebar, and the "What each page is for" list on Help, in one place so
 * the two cannot drift. Each page: route name, Phosphor icon, label, the
 * permission needed to see it (null = everyone signed in), whether people
 * change figures on it, and one sentence for Help. Routes that do not exist
 * yet are skipped.
 */
return [

    'Timesheets' => [
        ['route' => 'timesheets', 'icon' => 'ph-clock', 'label' => 'Timesheets', 'permission' => 'view timesheets', 'editable' => true,
            'help' => 'Log your own hours each week: one row per project and phase, office time or kind of time off. Admins can read anyone’s week.'],
    ],

    'Firm health' => [
        ['route' => 'overview', 'icon' => 'ph-squares-four', 'label' => 'Overview', 'permission' => 'view financials', 'editable' => false,
            'help' => 'How the year is going: revenue billed by month against last year, fee still to bill, the realized rate and cash collected.'],
        ['route' => 'monthEnd', 'icon' => 'ph-pencil-simple', 'label' => 'Month End', 'permission' => 'view financials', 'editable' => true,
            'help' => 'Enter the month’s billing — phase percentages, CA draws and hourly lines — and close the month when it is invoiced.'],
        ['route' => 'invoicing', 'icon' => 'ph-receipt', 'label' => 'Invoicing', 'permission' => 'view financials', 'editable' => false,
            'help' => 'Every ongoing contract with what was billed this month, to date, and what is left. Download the monthly billing report from here.'],
        ['route' => 'targets', 'icon' => 'ph-target', 'label' => 'Monthly Targets', 'permission' => 'view financials', 'editable' => true,
            'help' => 'A revenue target per month, set against what was actually invoiced.'],
        ['route' => 'extras', 'icon' => 'ph-timer', 'label' => 'Extras', 'permission' => 'view financials', 'editable' => false,
            'help' => 'Hourly (time and materials) billing by job and month, including hours worked but not billed.'],
    ],

    'Projects' => [
        ['route' => 'my-week', 'icon' => 'ph-calendar-check', 'label' => 'My Week', 'permission' => null, 'editable' => false,
            'help' => 'Your week from the Team Lineup: who you go to and the projects you are on, with each one’s PM and partner.'],
        ['route' => 'projects.index', 'icon' => 'ph-folders', 'label' => 'Projects', 'permission' => 'view projects', 'editable' => true,
            'help' => 'Every billing job with its status, hours, billed and $/hr. Click a row for the project page: contract, month-by-month billing and payments.'],
        ['route' => 'project-roles', 'icon' => 'ph-user-switch', 'label' => 'Leads & PMs', 'permission' => 'edit team', 'editable' => true,
            'help' => 'Managers set who leads each project and who manages it. The PM Desk and the Projects pages show it.'],
        ['route' => 'alerts', 'icon' => 'ph-warning', 'label' => 'Project Alerts', 'permission' => 'view alerts', 'editable' => false,
            'help' => 'Projects whose hours are running ahead of what has been billed.'],
        ['route' => 'pm-desk', 'icon' => 'ph-briefcase', 'label' => 'PM Desk', 'permission' => 'view pm desk', 'editable' => true,
            'help' => 'One person’s week: their projects, their team and their tasks. Staff see only their own desk.'],
        ['route' => 'lineup', 'icon' => 'ph-users-three', 'label' => 'Team Lineup', 'permission' => 'view office', 'editable' => true,
            'help' => 'Who reports to whom this week, and what everyone is working on.'],
    ],

    'Business development' => [
        ['route' => 'pipeline', 'icon' => 'ph-funnel', 'label' => 'Pipeline', 'permission' => 'view business development', 'editable' => true,
            'help' => 'Pursuits you are chasing, kept by hand. Add one with New Pursuit at the top of any page.'],
        ['route' => 'developers.index', 'icon' => 'ph-buildings', 'label' => 'Developers', 'permission' => 'view business development', 'editable' => true,
            'help' => 'Who the firm works for, from billing jobs and ones added here. Click one for its page; merge two spellings of the same firm.'],
        ['route' => 'outreach.index', 'icon' => 'ph-paper-plane-tilt', 'label' => 'Outreach List', 'permission' => 'view business development', 'editable' => false,
            'help' => 'Developers the firm doesn’t work with yet, from the BC prospects research. Read-only.'],
    ],

    'Office' => [
        ['route' => 'software', 'icon' => 'ph-cube', 'label' => 'Software', 'permission' => 'view office', 'editable' => true,
            'help' => 'What the firm pays for software, annualised and converted to CAD, with seats paid for but unused.'],
        ['route' => 'hardware', 'icon' => 'ph-laptop', 'label' => 'Hardware', 'permission' => 'view office', 'editable' => true,
            'help' => 'Every computer, who has it, and when it is due for replacement.'],
    ],

    'Admin' => [
        ['route' => 'imports.create', 'icon' => 'ph-upload-simple', 'label' => 'Upload workbook', 'permission' => 'import data', 'editable' => true,
            'help' => 'Update the dashboard from the Dashboard Data spreadsheet.'],
        ['route' => 'users', 'icon' => 'ph-users', 'label' => 'Users', 'permission' => 'manage users', 'editable' => true,
            'help' => 'Everyone who has signed in, and their role.'],
    ],

    'Help' => [
        ['route' => 'help', 'icon' => 'ph-question', 'label' => 'Help', 'permission' => null, 'editable' => false,
            'help' => 'This page.'],
    ],

];
