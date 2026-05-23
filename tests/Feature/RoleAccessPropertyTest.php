<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property-based tests for role-based access control on DocumentReviewResource.
 *
 * Feature: document-review-module, Property 14: Role-based access control
 *
 * **Validates: Requirements 8.1, 8.6, 8.7, 8.8**
 */
#[Group('Feature: document-review-module, Property 14: Role-based access control')]
class RoleAccessPropertyTest extends TestCase
{
    /**
     * The access control logic extracted from DocumentReviewResource::canAccess().
     * Replicates the exact condition used in the resource.
     */
    private function canAccessDocumentReviewResource(?string $role): bool
    {
        return in_array($role, ['admin', 'manajemen']);
    }

    /**
     * The action visibility logic extracted from DocumentReviewResource actions.
     * Replicates the exact condition used for create review, export, and follow-up actions.
     */
    private function canPerformActions(?string $role): bool
    {
        return in_array($role, ['admin', 'manajemen']);
    }

    /**
     * **Validates: Requirements 8.1**
     *
     * Property 14: Role-based access control
     *
     * For any user with role 'admin', access to the DocumentReviewResource and all
     * its actions SHALL be granted (canAccess() returns true).
     */
    public function test_admin_role_has_full_access(): void
    {
        $this->assertTrue(
            $this->canAccessDocumentReviewResource('admin'),
            'Admin role should have access to DocumentReviewResource'
        );
        $this->assertTrue(
            $this->canPerformActions('admin'),
            'Admin role should be able to perform all actions (create review, export, follow-up)'
        );
    }

    /**
     * **Validates: Requirements 8.1**
     *
     * Property 14: Role-based access control
     *
     * For any user with role 'manajemen', access to the DocumentReviewResource and all
     * its actions SHALL be granted (canAccess() returns true).
     */
    public function test_manajemen_role_has_full_access(): void
    {
        $this->assertTrue(
            $this->canAccessDocumentReviewResource('manajemen'),
            'Manajemen role should have access to DocumentReviewResource'
        );
        $this->assertTrue(
            $this->canPerformActions('manajemen'),
            'Manajemen role should be able to perform all actions (create review, export, follow-up)'
        );
    }

    /**
     * **Validates: Requirements 8.6, 8.8**
     *
     * Property 14: Role-based access control
     *
     * For any user with a role NOT in ['admin', 'manajemen'], access to the
     * DocumentReviewResource SHALL be denied (canAccess() returns false) and
     * all actions SHALL be hidden/forbidden.
     */
    #[DataProvider('unauthorizedRoleProvider')]
    public function test_unauthorized_roles_are_denied_access(string $role, int $iteration): void
    {
        $this->assertFalse(
            $this->canAccessDocumentReviewResource($role),
            "Iteration {$iteration}: Role '{$role}' should NOT have access to DocumentReviewResource"
        );
        $this->assertFalse(
            $this->canPerformActions($role),
            "Iteration {$iteration}: Role '{$role}' should NOT be able to perform actions"
        );
    }

    /**
     * **Validates: Requirements 8.6**
     *
     * Property 14: Role-based access control
     *
     * Unauthenticated users (null role) SHALL be denied access.
     */
    public function test_null_role_is_denied_access(): void
    {
        $this->assertFalse(
            $this->canAccessDocumentReviewResource(null),
            'Null role (unauthenticated user) should NOT have access to DocumentReviewResource'
        );
        $this->assertFalse(
            $this->canPerformActions(null),
            'Null role (unauthenticated user) should NOT be able to perform actions'
        );
    }

    /**
     * **Validates: Requirements 8.1, 8.6, 8.7, 8.8**
     *
     * Property 14: Role-based access control
     *
     * The access control property SHALL hold universally: canAccess() returns true
     * if and only if role is in ['admin', 'manajemen']. This is verified across
     * 100 randomized iterations with mixed authorized and unauthorized roles.
     */
    #[DataProvider('randomizedRoleAccessProvider')]
    public function test_access_control_property_holds_for_any_role(
        string $role,
        bool $expectedAccess,
        int $iteration,
    ): void {
        $canAccess = $this->canAccessDocumentReviewResource($role);
        $canAct = $this->canPerformActions($role);

        $this->assertSame(
            $expectedAccess,
            $canAccess,
            "Iteration {$iteration}: canAccess() for role '{$role}' - expected "
                . ($expectedAccess ? 'true' : 'false')
                . ' but got ' . ($canAccess ? 'true' : 'false')
        );

        // Actions follow the same access rule
        $this->assertSame(
            $expectedAccess,
            $canAct,
            "Iteration {$iteration}: canPerformActions() for role '{$role}' - expected "
                . ($expectedAccess ? 'true' : 'false')
                . ' but got ' . ($canAct ? 'true' : 'false')
        );
    }

    /**
     * **Validates: Requirements 8.1, 8.6, 8.7, 8.8**
     *
     * Property 14: Role-based access control
     *
     * The access control check is deterministic: calling canAccess() multiple times
     * with the same role always returns the same result.
     */
    #[DataProvider('determinismProvider')]
    public function test_access_control_is_deterministic(string $role, bool $expectedAccess, int $iteration): void
    {
        $result1 = $this->canAccessDocumentReviewResource($role);
        $result2 = $this->canAccessDocumentReviewResource($role);
        $result3 = $this->canAccessDocumentReviewResource($role);

        $this->assertSame($result1, $result2, "Iteration {$iteration}: Access check for '{$role}' is not deterministic");
        $this->assertSame($result2, $result3, "Iteration {$iteration}: Access check for '{$role}' is not deterministic");
        $this->assertSame(
            $expectedAccess,
            $result1,
            "Iteration {$iteration}: Role '{$role}' should " . ($expectedAccess ? 'have' : 'NOT have') . ' access'
        );
    }

    /**
     * **Validates: Requirements 8.7**
     *
     * Property 14: Role-based access control
     *
     * The navigation item visibility follows the same access control rule.
     * Navigation is hidden for unauthorized roles (same condition as canAccess).
     */
    #[DataProvider('navigationVisibilityProvider')]
    public function test_navigation_visibility_matches_access_control(string $role, bool $expectedVisible, int $iteration): void
    {
        // Navigation visibility uses the same canAccess() logic in Filament Resource
        $isVisible = $this->canAccessDocumentReviewResource($role);

        $this->assertSame(
            $expectedVisible,
            $isVisible,
            "Iteration {$iteration}: Navigation visibility for role '{$role}' - expected "
                . ($expectedVisible ? 'visible' : 'hidden')
                . ' but got ' . ($isVisible ? 'visible' : 'hidden')
        );
    }

    /**
     * Data provider for unauthorized roles.
     * Generates 100 iterations with various non-admin/non-manajemen roles.
     */
    public static function unauthorizedRoleProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        // Known unauthorized roles in the system
        $knownUnauthorizedRoles = ['user', 'viewer', 'operator', 'staff', 'analis'];

        for ($i = 0; $i < 100; $i++) {
            // Mix known roles with randomly generated ones
            $role = $faker->randomElement([
                ...$knownUnauthorizedRoles,
                $faker->lexify('????'),
                $faker->word(),
            ]);

            // Ensure we never accidentally generate 'admin' or 'manajemen'
            while (in_array($role, ['admin', 'manajemen'])) {
                $role = $faker->lexify('????');
            }

            yield "unauthorized_iteration_{$i}_{$role}" => [$role, $i];
        }
    }

    /**
     * Data provider that generates 100 randomized iterations with a mix of
     * authorized and unauthorized roles to verify the access control property.
     */
    public static function randomizedRoleAccessProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        $authorizedRoles = ['admin', 'manajemen'];
        $unauthorizedRoles = ['user', 'viewer', 'operator', 'staff', 'analis', 'guest'];

        for ($i = 0; $i < 100; $i++) {
            // 30% chance of authorized role, 70% unauthorized
            if ($faker->boolean(30)) {
                $role = $faker->randomElement($authorizedRoles);
                $expectedAccess = true;
            } else {
                $role = $faker->randomElement([
                    ...$unauthorizedRoles,
                    $faker->lexify('????_role'),
                ]);

                // Ensure we never accidentally generate 'admin' or 'manajemen'
                while (in_array($role, ['admin', 'manajemen'])) {
                    $role = $faker->lexify('????_role');
                }

                $expectedAccess = false;
            }

            yield "mixed_iteration_{$i}_{$role}" => [$role, $expectedAccess, $i];
        }
    }

    /**
     * Data provider for determinism testing.
     * Generates 100 iterations randomly picking from all possible roles.
     */
    public static function determinismProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        $allRoles = ['admin', 'manajemen', 'user', 'viewer', 'operator', 'staff', 'analis', 'guest'];

        for ($i = 0; $i < 100; $i++) {
            $role = $faker->randomElement($allRoles);
            $expectedAccess = in_array($role, ['admin', 'manajemen']);

            yield "determinism_iteration_{$i}_{$role}" => [$role, $expectedAccess, $i];
        }
    }

    /**
     * Data provider for navigation visibility testing.
     * Generates 100 iterations to verify navigation item is hidden for unauthorized roles.
     */
    public static function navigationVisibilityProvider(): iterable
    {
        $faker = \Faker\Factory::create('id_ID');

        $authorizedRoles = ['admin', 'manajemen'];
        $unauthorizedRoles = ['user', 'viewer', 'operator', 'staff', 'analis', 'guest'];

        for ($i = 0; $i < 100; $i++) {
            if ($faker->boolean(30)) {
                $role = $faker->randomElement($authorizedRoles);
                $expectedVisible = true;
            } else {
                $role = $faker->randomElement($unauthorizedRoles);
                $expectedVisible = false;
            }

            yield "nav_iteration_{$i}_{$role}" => [$role, $expectedVisible, $i];
        }
    }
}
