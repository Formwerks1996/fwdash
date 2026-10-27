<?php

namespace App\Support;

use App\Enums\Phase;

/**
 * The phase ladders Formwerks used when it set jobs up in Mosaic, one per kind
 * of job, with each phase's usual share of the fixed fee. The shares are the
 * Mosaic averages (2022–2026) rounded and scaled to 100; a phase with no share
 * is billed by the hour or priced on its own, like a feasibility study.
 */
class ProjectTemplates
{
    /**
     * @var array<string, array{label: string, phases: array<int, array{name: string, code: string, share: int}>}>
     */
    public const Templates = [
        'multi-family' => [
            'label' => 'Multi-family',
            'phases' => [
                ['name' => 'Feasibility Study', 'code' => 'FS', 'share' => 0],
                ['name' => 'Schematic Design', 'code' => 'SD', 'share' => 18],
                ['name' => 'Design Development to Development Permit Application', 'code' => 'DD to DP', 'share' => 19],
                ['name' => 'Development Permit Processing', 'code' => 'DPP', 'share' => 8],
                ['name' => 'Building Permit Submission', 'code' => 'BP', 'share' => 19],
                ['name' => 'Issued for Construction', 'code' => 'IFC', 'share' => 16],
                ['name' => 'Construction Administration', 'code' => 'CA', 'share' => 20],
                ['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0],
            ],
        ],
        'rezoning' => [
            'label' => 'Multi-family with rezoning',
            'phases' => [
                ['name' => 'Feasibility Study', 'code' => 'FS', 'share' => 0],
                ['name' => 'Schematic Design', 'code' => 'SD', 'share' => 15],
                ['name' => 'Design Development to Rezoning', 'code' => 'DD to RZ', 'share' => 11],
                ['name' => 'Rezoning Processing', 'code' => 'RZP', 'share' => 5],
                ['name' => 'Design Development to Development Permit Application', 'code' => 'DD to DP', 'share' => 15],
                ['name' => 'Development Permit Processing', 'code' => 'DPP', 'share' => 6],
                ['name' => 'Building Permit Submission', 'code' => 'BP', 'share' => 16],
                ['name' => 'Issued for Construction', 'code' => 'IFC', 'share' => 15],
                ['name' => 'Construction Administration', 'code' => 'CA', 'share' => 17],
                ['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0],
            ],
        ],
        'character-home' => [
            'label' => 'Character home / infill',
            'phases' => [
                ['name' => 'Schematic Design', 'code' => 'SD', 'share' => 21],
                ['name' => 'Design Development to Development Permit Application', 'code' => 'DD to DP', 'share' => 26],
                ['name' => 'Development Permit Processing', 'code' => 'DPP', 'share' => 13],
                ['name' => 'Building Permit Submission', 'code' => 'BP', 'share' => 16],
                ['name' => 'Issued for Construction', 'code' => 'IFC', 'share' => 13],
                ['name' => 'Construction Administration', 'code' => 'CA', 'share' => 11],
                ['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0],
            ],
        ],
        'single-family' => [
            'label' => 'Single family',
            'phases' => [
                ['name' => 'Schematic Design', 'code' => 'SD', 'share' => 16],
                ['name' => 'Design Development', 'code' => 'DD to DP', 'share' => 17],
                ['name' => 'Development Permit Drawings and Application', 'code' => 'DD to DP', 'share' => 14],
                ['name' => 'Development Permit Processing', 'code' => 'DPP', 'share' => 11],
                ['name' => 'Drawings for Building Permit and Application', 'code' => 'BP', 'share' => 18],
                ['name' => 'Construction Detailing', 'code' => 'IFC', 'share' => 13],
                ['name' => 'Construction Administration', 'code' => 'CA', 'share' => 11],
                ['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0],
            ],
        ],
        'interiors' => [
            'label' => 'Interiors',
            'phases' => [
                ['name' => 'Schematic Design', 'code' => 'SD', 'share' => 20],
                ['name' => 'Design Development', 'code' => 'DD to DP', 'share' => 40],
                ['name' => 'Construction Documentation', 'code' => 'IFC', 'share' => 40],
                ['name' => 'Project Coordination', 'code' => 'CA', 'share' => 0],
                ['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0],
            ],
        ],
        'landscape' => [
            'label' => 'Landscape',
            'phases' => [
                ['name' => 'Conceptual Design', 'code' => 'SD', 'share' => 22],
                ['name' => 'Landscape Master Plan', 'code' => 'DD to DP', 'share' => 22],
                ['name' => 'Development and Building Permit Application', 'code' => 'BP', 'share' => 23],
                ['name' => 'Tender Drawings', 'code' => 'IFT', 'share' => 19],
                ['name' => 'Site Review', 'code' => 'CA', 'share' => 14],
                ['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0],
            ],
        ],
        'rla' => [
            'label' => 'RLA',
            'phases' => [
                ['name' => 'Feasibility Study', 'code' => 'FS', 'share' => 0],
                ['name' => 'Schematic Design', 'code' => 'SD', 'share' => 13],
                ['name' => 'Design Development to Development Permit Application', 'code' => 'DD to DP', 'share' => 19],
                ['name' => 'Building Permit Submission', 'code' => 'BP', 'share' => 21],
                ['name' => 'Construction Documents', 'code' => 'IFC', 'share' => 20],
                ['name' => 'Field Administration', 'code' => 'CA', 'share' => 22],
                ['name' => 'Project Close Out', 'code' => 'CA', 'share' => 3],
                ['name' => 'Post Construction Warranty Review', 'code' => 'Misc.', 'share' => 2],
                ['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0],
            ],
        ],
        'hourly' => [
            'label' => 'Hourly only',
            'phases' => [
                ['name' => 'Hourly', 'code' => Phase::Hours, 'share' => 0],
            ],
        ],
    ];

    /** The template a new job in each division starts from. */
    public const ByDivision = [
        'MF' => 'multi-family',
        'MPX' => 'multi-family',
        'CHC' => 'character-home',
        'SF' => 'single-family',
        'INT' => 'interiors',
        'LAN' => 'landscape',
        'RLA' => 'rla',
        'REN' => 'hourly',
    ];

    /** What each billing phase is called on a contract. */
    public const Names = [
        'FS' => 'Feasibility Study',
        'SD' => 'Schematic Design',
        'DD to RZ' => 'Design Development to Rezoning',
        'RZP' => 'Rezoning Processing',
        'DD to DP' => 'Design Development to Development Permit Application',
        'DPP' => 'Development Permit Processing',
        'BP' => 'Building Permit Submission',
        'IFT' => 'Issued for Tender',
        'IFC' => 'Issued for Construction',
        'CA' => 'Construction Administration',
        'Misc.' => 'Miscellaneous',
        'Hours' => 'Hourly',
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn (array $template) => $template['label'], self::Templates);
    }

    /** @return array<int, array{name: string, code: string, share: int}> */
    public static function phases(string $template): array
    {
        return self::Templates[$template]['phases'] ?? [];
    }

    public static function nameFor(string $code): string
    {
        return self::Names[$code] ?? $code;
    }

    public static function forDivision(?string $division): string
    {
        return self::ByDivision[strtoupper((string) $division)] ?? 'multi-family';
    }

    /** @return array<int, string> Every phase a job can be billed under, Hours last. */
    public static function billingCodes(): array
    {
        return [...Phase::codes(), Phase::Hours];
    }
}
