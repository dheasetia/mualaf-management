<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Data;

/**
 * A message from an approved WhatsApp template. Parameters are generic text
 * only: never names, identity numbers, religion or shahadah details.
 */
final readonly class WhatsAppTemplateMessage
{
    /** @param  list<string>  $bodyParameters */
    public function __construct(
        public string $template,
        public string $language,
        public array $bodyParameters = [],
    ) {}

    /** @return array<string, mixed> Cloud API "template" object */
    public function toPayload(): array
    {
        return [
            'name' => $this->template,
            'language' => ['code' => $this->language],
            'components' => $this->bodyParameters === [] ? [] : [[
                'type' => 'body',
                'parameters' => array_map(static fn (string $text): array => ['type' => 'text', 'text' => $text], $this->bodyParameters),
            ]],
        ];
    }
}
