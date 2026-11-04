<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A setting of this instance, by name (the model's connection, sealed when it holds a key).
 */
#[ORM\Entity]
class Setting
{
    public function __construct(
        #[ORM\Id, ORM\Column(length: 40)]
        public private(set) string $name,
        #[ORM\Column(type: Types::TEXT)]
        public string $value,
    ) {
    }
}
