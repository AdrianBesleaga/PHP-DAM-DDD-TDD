<?php
declare(strict_types=1);

namespace App\User\Application;

use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserDetails;
use App\User\Domain\UserRepository;
use App\Shared\Domain\TenantId;

class UserService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getUserById(string $id): ?User
    {
        return $this->userRepository->getUserById(new UserId($id));
    }

    public function createUser(string $name, string $email, string $tenantIdString, ?string $imageUrl = null): User
    {
        $id = new UserId(uniqid()); 
        $tenantId = new TenantId($tenantIdString);
        
        $userDetails = new UserDetails($imageUrl);
        
        // Using static factory method instead of new
        $user = User::create($id, $name, $email, $tenantId, $userDetails);
        
        $this->userRepository->save($user);
        
        return $user;
    }
}
