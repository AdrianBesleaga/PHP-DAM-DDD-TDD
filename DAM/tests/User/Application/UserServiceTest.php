<?php
declare(strict_types=1);

namespace Tests\User\Application;

use App\User\Application\UserService;
use App\User\Domain\User;
use App\User\Infrastructure\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $userService;
    private InMemoryUserRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->userService = new UserService($this->userRepository);
        
        $this->userService->createUser("Test User", "test@example.com", "tenant-1", "http://example.com/image.jpg");
    }

    public function testCreateUserAndRetrieveIt(): void
    {
        $newUser = $this->userService->createUser("Alice", "alice@example.com", "tenant-1");
        
        $this->assertInstanceOf(User::class, $newUser);
        $this->assertNotEmpty($newUser->getId()->getValue());
        $this->assertEquals("Alice", $newUser->getName());
        
        $fetchedUser = $this->userService->getUserById($newUser->getId()->getValue());
        $this->assertNotNull($fetchedUser);
        $this->assertEquals("Alice", $fetchedUser->getName());
        $this->assertNull($fetchedUser->getUserDetails()->getImageUrl());
    }

    public function testGetNonExistentUser(): void
    {
        $user = $this->userService->getUserById("non-existent");
        $this->assertNull($user);
    }
}
