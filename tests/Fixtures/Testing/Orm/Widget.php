<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Orm;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'widget')]
class Widget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
