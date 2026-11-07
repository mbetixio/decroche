<?php

declare(strict_types=1);

namespace App\Onboarding;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The steps of the first-boot onboarding, in order.
 */
enum OnboardingStep: string implements TranslatableInterface
{
    /** The working language. */
    case Welcome = 'welcome';
    /** The first manager's account. */
    case Account = 'account';
    /** The company: its name, website and description (or the example company). */
    case Company = 'company';
    /** The analysis of the website, a minute or two. */
    case Analysis = 'analysis';
    /** The offer sheet, to proofread. */
    case Offer = 'offer';
    /** The call situations, to keep, edit or remove. */
    case Situations = 'situations';
    /** The scorecard. */
    case Scorecard = 'scorecard';
    /** Invitations to the team. */
    case Team = 'team';
    /** Ready: the first call. */
    case Done = 'done';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('onboarding.step.'.$this->value, locale: $locale);
    }

    public function index(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public function next(): ?self
    {
        return self::cases()[$this->index() + 1] ?? null;
    }

    public function previous(): ?self
    {
        return self::cases()[$this->index() - 1] ?? null;
    }

    /**
     * Steps that need no account: the very first visitor sets the language and creates the manager's account.
     */
    public function isPublic(): bool
    {
        return self::Welcome === $this || self::Account === $this;
    }
}
