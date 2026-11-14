<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function testTextIsComparedLoosely(): void
    {
        self::assertSame('au coeur du sujet c est clair', Text::normalize('Au cœur du sujet, c’est clair !'));
        self::assertSame(['budget', 'mois'], Text::words('Le budget, par mois ?', 4));
        self::assertSame([], Text::words('  ?! '));
    }
}
