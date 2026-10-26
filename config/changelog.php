<?php

/*
 * Version history, newest first, shown on the Help page.
 *
 * Add an entry at the top for every dashboard change. Bump the second number
 * for fixes and small changes (2.1), the first for a new page or a change to
 * how people work (3.0). Write it for the people using the dashboard, not for
 * the code.
 */
return [
    [
        'version' => '2.0',
        'date' => '2026-10-27',
        'changes' => [
            'The dashboard is now a website: open it from any browser and sign in with your Formwerks Microsoft account.',
            'Roles decide what you see: Admins see everything, Managers the money pages, Staff the office pages.',
            'Figures come in by uploading the Dashboard Data workbook; every upload is checked against the workbook’s Report for Jim.',
            'Mosaic is no longer connected: hours come from the workbook’s Hours sheet, and Paused is set on the Projects page.',
            'Every page from the old dashboard is here, plus a Users page for roles.',
        ],
    ],
];
