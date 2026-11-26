<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\View\View;

/**
 * The Muallaf Center look shared by both panels: deep green with a gold
 * accent on warm cream, the logo lockup and the Arabic-first typeface.
 * The theme stylesheet places the white logo on the green sidebar and
 * the colored one on light surfaces. Body text is Noto Naskh Arabic, headings Cairo.
 */
final class Brand
{
    /**
     * Brand green (#075B43 is 600, the button shade; #103E32 is 800, the sidebar).
     *
     * @var array<int, string>
     */
    private const array GREEN = [
        50 => '#EDF6F2',
        100 => '#D3EAE0',
        200 => '#A8D3C2',
        300 => '#76B79E',
        400 => '#479A7C',
        500 => '#1B7A5D',
        600 => '#075B43',
        700 => '#064E3A',
        800 => '#103E32',
        900 => '#0C3229',
        950 => '#07201A',
    ];

    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->viteTheme('resources/css/filament/theme.css')
            // Noto Naskh Arabic for body text, Cairo for headings (theme.css); one stylesheet from the CSP font host.
            ->font('Noto Naskh Arabic', url: 'https://fonts.bunny.net/css?family=noto-naskh-arabic:400,500,600,700|cairo:500,600,700&display=swap')
            ->colors([
                'primary' => array_map(Color::convertToOklch(...), self::GREEN),
                'gray' => Color::Stone,
            ])
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/brand/favicon.png'));
    }
}
