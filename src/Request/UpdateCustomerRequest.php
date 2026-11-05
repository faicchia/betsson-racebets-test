<?php

declare(strict_types=1);

namespace App\Request;

use App\ValueObject\Gender;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateCustomerRequest
{
    #[Assert\Sequentially([
        new Assert\NotBlank(allowNull: true),
        new Assert\Email(),
        new Assert\Length(max: 255),
    ])]
    public string $email;

    #[Assert\Sequentially([
        new Assert\NotBlank(allowNull: true),
        new Assert\Length(max: 100),
    ])]
    public string $firstName;

    #[Assert\Sequentially([
        new Assert\NotBlank(allowNull: true),
        new Assert\Length(max: 100),
    ])]
    public string $lastName;

    #[Assert\Sequentially([
        new Assert\NotBlank(allowNull: true),
        new Assert\Choice(callback: [Gender::class, 'values']),
    ])]
    public string $gender;

    #[Assert\Sequentially([
        new Assert\NotBlank(allowNull: true),
        new Assert\Country(),
    ])]
    public string $country;

    /**
     * @return array<'email'|'firstName'|'lastName'|'gender'|'country', string> the fields present in the payload
     */
    public function changes(): array
    {
        return array_filter([
            'email' => $this->email ?? null,
            'firstName' => $this->firstName ?? null,
            'lastName' => $this->lastName ?? null,
            'gender' => $this->gender ?? null,
            'country' => $this->country ?? null,
        ], static fn (?string $value): bool => null !== $value);
    }
}
