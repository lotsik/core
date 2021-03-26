<?php

namespace Apiato\Core\Traits\TestsTraits\PhpUnit;

use App\Containers\User\Models\User;
use Illuminate\Support\Facades\Hash;

trait TestsAuthHelperTrait
{
    /**
     * Logged in user object.
     */
    protected ?User $testingUser = null;

    /**
     * Roles and permissions, to be attached on the user
     */
    protected array $access = [
        'permissions' => '',
        'roles' => '',
    ];

    /**
     * Same as `getTestingUser()` but always overrides the User Access
     * (roles and permissions) with null. So the user can be used to test
     * if unauthorized user tried to access your protected endpoint.
     *
     * @param null $userDetails
     *
     * @return  User
     */
    public function getTestingUserWithoutAccess($userDetails = null): User
    {
        return $this->getTestingUser($userDetails, $this->getNullAccess());
    }

    /**
     * Try to get the last logged in User, if not found then create new one.
     * Note: if $userDetails are provided it will always create new user, even
     * if another one was previously created during the execution of your test.
     *
     * By default Users will be given the Roles and Permissions found int he class
     * `$access` property. But the $access parameter can be used to override the
     * defined roles and permissions in the `$access` property of your class.
     *
     * @param null $access roles and permissions you'd like to provide this user with
     * @param null $userDetails what to be attached on the User object
     *
     * @return  User
     */
    public function getTestingUser($userDetails = null, $access = null): User
    {
        return is_null($userDetails) ? $this->findOrCreateTestingUser($userDetails, $access)
            : $this->createTestingUser($userDetails, $access);
    }

    /**
     * @param $userDetails
     * @param $access
     *
     * @return  User
     */
    private function findOrCreateTestingUser($userDetails, $access): User
    {
        return $this->testingUser ?: $this->createTestingUser($userDetails, $access);
    }

    /**
     * @param null $access
     * @param null $userDetails
     *
     * @return  User
     */
    private function createTestingUser($userDetails = null, $access = null): User
    {
        // "inject" the confirmed status, if user details are submitted
        if (is_array($userDetails)) {
            $defaults = [
                'confirmed' => true,
            ];

            $userDetails = array_merge($defaults, $userDetails);
        }

        // create new user
        $user = $this->factoryCreateUser($userDetails);

        // assign user roles and permissions based on the access property
        $user = $this->setupTestingUserAccess($user, $access);

        // authentication the user
        $this->actingAs($user, 'api');

        // set the created user
        return $this->testingUser = $user;
    }

    /**
     * @param null $userDetails
     *
     * @return  User
     */
    private function factoryCreateUser($userDetails = null): User
    {
        return User::factory()->admin()->create($this->prepareUserDetails($userDetails));
    }

    /**
     * @param null $userDetails
     *
     * @return  array
     */
    private function prepareUserDetails($userDetails = null): array
    {
        $defaultUserDetails = [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'password' => 'testing-password',
        ];

        // if no user detail provided, use the default details, to find the password or generate one before encoding it
        return $this->prepareUserPassword($userDetails ?: $defaultUserDetails);
    }

    /**
     * @param $userDetails
     *
     * @return  null
     */
    private function prepareUserPassword($userDetails)
    {
        // get password from the user details or generate one
        $password = $userDetails['password'] ?? $this->faker->password;

        // hash the password and set it back at the user details
        $userDetails['password'] = Hash::make($password);

        return $userDetails;
    }

    /**
     * @param $user
     * @param $access
     *
     * @return  mixed
     */
    private function setupTestingUserAccess($user, $access = null)
    {
        $access = $access ?: $this->getAccess();

        $user = $this->setupTestingUserPermissions($user, $access);
        $user = $this->setupTestingUserRoles($user, $access);

        return $user;
    }

    /**
     * @return  array|null
     */
    private function getAccess(): ?array
    {
        return $this->access ?? null;
    }

    /**
     * @param $user
     * @param $access
     *
     * @return  mixed
     */
    private function setupTestingUserPermissions($user, $access)
    {
        if (isset($access['permissions']) && !empty($access['permissions'])) {
            $user->givePermissionTo($access['permissions']);
            $user = $user->fresh();
        }

        return $user;
    }

    /**
     * @param $user
     * @param $access
     *
     * @return  mixed
     */
    private function setupTestingUserRoles($user, $access)
    {
        if (isset($access['roles']) && !empty($access['roles']) && !$user->hasRole($access['roles'])) {
            $user->assignRole($access['roles']);
            $user = $user->fresh();
        }

        return $user;
    }

    /**
     * @return  array
     */
    private function getNullAccess(): array
    {
        return [
            'permissions' => null,
            'roles' => null
        ];
    }
}
