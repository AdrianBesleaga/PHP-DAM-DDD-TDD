<?php
declare(strict_types=1);

namespace App\User\Domain;

class UserId
{
    private string $id;

    public function __construct(string $id)
    {
        if (empty(trim($id))) {
            throw new \InvalidArgumentException("UserId cannot be empty");
        }
        $this->id = $id;
    }

    public function getValue(): string
    {
        return $this->id;
    }

    public function equals(UserId $other): bool
    {
        return $this->id === $other->getValue();
    }
}
