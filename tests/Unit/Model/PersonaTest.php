<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Enum\Tone;
use App\Model\Persona;
use PHPUnit\Framework\TestCase;

/**
 * The persona: built from loose data, its round trip, initials, first name and tone change.
 */
final class PersonaTest extends TestCase
{
    public function testFromArray(): void
    {
        $persona = Persona::fromArray(['name' => ' Claire Dubois ', 'role' => 'CTO', 'company' => 'Acme', 'personality' => 'Busy', 'gender' => 'F', 'tone' => 'warm']);

        self::assertSame('Claire Dubois', $persona->name);
        self::assertSame('CTO', $persona->role);
        self::assertSame('Acme', $persona->company);
        self::assertSame('Busy', $persona->personality);
        self::assertSame('f', $persona->gender);
        self::assertSame(Tone::Warm, $persona->tone);
    }

    public function testFromLooseArray(): void
    {
        $persona = Persona::fromArray(['name' => ['x'], 'gender' => 'other', 'tone' => 'grumpy', 'role' => 12]);

        self::assertSame('', $persona->name);
        self::assertSame('12', $persona->role);
        self::assertSame('', $persona->company);
        self::assertNull($persona->gender);
        self::assertNull($persona->tone);
    }

    public function testRoundTrip(): void
    {
        $full = new Persona('Claire Dubois', 'CTO', 'Acme', 'Busy', 'f', Tone::Angry);
        $bare = new Persona('Paul', 'CEO', 'Beta', 'Calm');

        self::assertEquals($full, Persona::fromArray($full->toArray()));
        self::assertEquals($bare, Persona::fromArray($bare->toArray()));
        self::assertArrayNotHasKey('gender', $bare->toArray());
        self::assertArrayNotHasKey('tone', $bare->toArray());
        self::assertSame('angry', $full->toArray()['tone']);
    }

    public function testInitials(): void
    {
        self::assertSame('CD', new Persona('Claire Dubois', '', '', '')->initials());
        self::assertSame('JM', new Persona('jean-marc Leroy', '', '', '')->initials());
        self::assertSame('ÉL', new Persona('élodie le Gall', '', '', '')->initials());
        self::assertSame('P', new Persona(' Paul ', '', '', '')->initials());
        self::assertSame('', new Persona('', '', '', '')->initials());
    }

    public function testFirstName(): void
    {
        self::assertSame('Claire', new Persona(' Claire Dubois', '', '', '')->firstName());
        self::assertSame('Jean-Marc', new Persona('Jean-Marc Leroy', '', '', '')->firstName());
        self::assertSame('', new Persona('', '', '', '')->firstName());
    }

    public function testWithToneKeepsTheRest(): void
    {
        $persona = new Persona('Claire Dubois', 'CTO', 'Acme', 'Busy', 'f');
        $angry = $persona->withTone(Tone::Angry);

        self::assertNull($persona->tone);
        self::assertSame(Tone::Angry, $angry->tone);
        self::assertSame(['Claire Dubois', 'CTO', 'Acme', 'Busy', 'f'], [$angry->name, $angry->role, $angry->company, $angry->personality, $angry->gender]);
    }
}
