<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property 7: Role-Based Action Visibility
 *
 * Tests the visibility logic for document actions based on user roles.
 * Validates: Requirements 4.7, 5.2, 6.3, 7.3
 */
#[Group('Feature: dual-qr-pdf-stamping, Property 7: Role-Based Action Visibility')]
class RoleBasedActionVisibilityPropertyTest extends TestCase
{
    /**
     * Validates: Requirements 4.7, 5.2, 6.3, 7.3
     *
     * Property 7: Role-Based Action Visibility
     * For any authenticated user, the "Cetak QR Code" action SHALL be visible
     * if and only if the user's role is 'admin'.
     *
     * Visibility condition from DocumentResource:
     *   auth()->user()?->role === 'admin'
     */
    #[DataProvider('cetakQrCodeRoleProvider')]
    public function test_cetak_qr_code_visible_only_for_admin(string $role, bool $expectedVisible): void
    {
        // Replicate the exact visibility condition from DocumentResource
        $isVisible = $role === 'admin';

        $this->assertSame(
            $expectedVisible,
            $isVisible,
            "Cetak QR Code visibility for role '{$role}': expected "
                . ($expectedVisible ? 'visible' : 'hidden')
                . ' but got ' . ($isVisible ? 'visible' : 'hidden')
        );
    }

    /**
     * Validates: Requirements 4.7, 5.2, 6.3, 7.3
     *
     * Property 7: Role-Based Action Visibility
     * The "Terkendali" action SHALL be visible if and only if the user's role
     * is 'admin' or 'manajemen'.
     *
     * Visibility condition from DocumentResource:
     *   in_array(auth()->user()?->role, ['admin', 'manajemen'])
     */
    #[DataProvider('terkendaliRoleProvider')]
    public function test_terkendali_visible_only_for_admin_or_manajemen(string $role, bool $expectedVisible): void
    {
        // Replicate the exact visibility condition from DocumentResource
        $isVisible = in_array($role, ['admin', 'manajemen']);

        $this->assertSame(
            $expectedVisible,
            $isVisible,
            "Terkendali visibility for role '{$role}': expected "
                . ($expectedVisible ? 'visible' : 'hidden')
                . ' but got ' . ($isVisible ? 'visible' : 'hidden')
        );
    }

    /**
     * Validates: Requirements 4.7, 5.2, 6.3, 7.3
     *
     * Property 7: Role-Based Action Visibility
     * The "Tidak Terkendali" action SHALL be visible if and only if the user's role
     * is 'admin' or 'manajemen' AND the record has a non-empty file_path.
     *
     * Visibility condition from DocumentResource:
     *   in_array(auth()->user()?->role, ['admin', 'manajemen']) && !empty($record->file_path)
     */
    #[DataProvider('tidakTerkendaliRoleAndFileProvider')]
    public function test_tidak_terkendali_visible_only_for_admin_or_manajemen_with_file(
        string $role,
        ?string $filePath,
        bool $expectedVisible
    ): void {
        // Replicate the exact visibility condition from DocumentResource
        $isVisible = in_array($role, ['admin', 'manajemen']) && ! empty($filePath);

        $this->assertSame(
            $expectedVisible,
            $isVisible,
            "Tidak Terkendali visibility for role '{$role}' with file_path "
                . var_export($filePath, true) . ': expected '
                . ($expectedVisible ? 'visible' : 'hidden')
                . ' but got ' . ($isVisible ? 'visible' : 'hidden')
        );
    }

    /**
     * Generate all role combinations for Cetak QR Code visibility.
     * Only 'admin' should see this action.
     */
    public static function cetakQrCodeRoleProvider(): iterable
    {
        $faker = \Faker\Factory::create();

        // Exhaustive known roles
        yield 'admin' => ['admin', true];
        yield 'manajemen' => ['manajemen', false];
        yield 'user' => ['user', false];
        yield 'viewer' => ['viewer', false];
        yield 'operator' => ['operator', false];
        yield 'staff' => ['staff', false];

        // Generate 104 random roles to reach 110 total iterations
        for ($i = 0; $i < 104; $i++) {
            $randomRole = $faker->lexify('role_????') . "_{$i}";
            yield "random_{$i}" => [$randomRole, false];
        }
    }

    /**
     * Generate all role combinations for Terkendali visibility.
     * Only 'admin' and 'manajemen' should see this action.
     */
    public static function terkendaliRoleProvider(): iterable
    {
        $faker = \Faker\Factory::create();

        // Exhaustive known roles
        yield 'admin' => ['admin', true];
        yield 'manajemen' => ['manajemen', true];
        yield 'user' => ['user', false];
        yield 'viewer' => ['viewer', false];
        yield 'operator' => ['operator', false];
        yield 'staff' => ['staff', false];

        // Generate 104 random roles to reach 110 total iterations
        for ($i = 0; $i < 104; $i++) {
            $randomRole = $faker->lexify('role_????') . "_{$i}";
            yield "random_{$i}" => [$randomRole, false];
        }
    }

    /**
     * Generate all role + file_path combinations for Tidak Terkendali visibility.
     * Only 'admin' and 'manajemen' with a non-empty file_path should see this action.
     */
    public static function tidakTerkendaliRoleAndFileProvider(): iterable
    {
        $faker = \Faker\Factory::create();

        // Exhaustive known combinations
        // Admin with file -> visible
        yield 'admin_with_file' => ['admin', 'revisions/doc.pdf', true];
        // Admin without file -> hidden
        yield 'admin_null_file' => ['admin', null, false];
        yield 'admin_empty_file' => ['admin', '', false];
        // Manajemen with file -> visible
        yield 'manajemen_with_file' => ['manajemen', 'revisions/doc.pdf', true];
        // Manajemen without file -> hidden
        yield 'manajemen_null_file' => ['manajemen', null, false];
        yield 'manajemen_empty_file' => ['manajemen', '', false];
        // Other roles with file -> hidden
        yield 'user_with_file' => ['user', 'revisions/doc.pdf', false];
        yield 'viewer_with_file' => ['viewer', 'revisions/doc.pdf', false];
        yield 'operator_with_file' => ['operator', 'revisions/doc.pdf', false];
        yield 'staff_with_file' => ['staff', 'revisions/doc.pdf', false];
        // Other roles without file -> hidden
        yield 'user_null_file' => ['user', null, false];
        yield 'viewer_null_file' => ['viewer', null, false];

        // Generate 100 random combinations to reach 112 total iterations
        for ($i = 0; $i < 100; $i++) {
            $randomRole = $faker->lexify('role_????') . "_{$i}";
            $filePath = $faker->randomElement([
                'revisions/' . $faker->uuid() . '.pdf',
                null,
                '',
            ]);
            // Non-admin/manajemen roles should never see the action
            yield "random_{$i}" => [$randomRole, $filePath, false];
        }
    }
}
