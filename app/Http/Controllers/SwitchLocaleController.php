<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Changes the interface language: kept in the session, and saved on the
 * account when signed in so it follows the user to other devices.
 */
final class SwitchLocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put('locale', $locale);

        $user = $request->user();

        if ($user instanceof User && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return redirect()->back();
    }
}
