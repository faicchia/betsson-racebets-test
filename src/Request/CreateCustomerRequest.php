<?php

declare(strict_types=1);

namespace App\Request;

use App\ValueObject\Gender;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCustomerRequest
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Email(),
            new Assert\Length(max: 255),
        ])]
        public ?string $email = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Length(max: 100),
        ])]
        public ?string $firstName = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Length(max: 100),
        ])]
        public ?string $lastName = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Choice(callback: [Gender::class, 'values']),
        ])]
        public ?string $gender = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Country(),
        ])]
        public ?string $country = null,
    ) {
    }
}
