<?php

declare(strict_types=1);

namespace App\Agent;

use App\Model\Text;
use App\Repository\CompanyRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The company's offer sheet, read by topic: the coach checks a seller's promise against it, the writer draws a
 * situation from it.
 */
#[AsTool('read_offer', 'Read the parts of the company\'s offer sheet about a topic (a service, the prices and terms, references, what the company does not do, the classic objections); leave the topic empty for the whole sheet.')]
final readonly class ReadOffer
{
    public function __construct(
        private CompanyRepository $companies,
    ) {
    }

    /**
     * @param string $topic a word or two; empty for the whole sheet
     */
    public function __invoke(string $topic = ''): string
    {
        $offer = $this->companies->get()->offer;
        $words = Text::words($topic, 3);
        if ([] === $words) {
            return $offer;
        }
        $parts = array_filter(preg_split('/\n\s*\n/', $offer) ?: [], static function (string $part) use ($words): bool {
            $part = Text::normalize($part);

            return [] !== array_filter($words, static fn (string $w): bool => str_contains($part, $w));
        });

        return [] !== $parts ? implode("\n\n", $parts) : "Nothing about \"{$topic}\" in the offer sheet: it does not say.";
    }
}
