<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Certificates\Services\CertificateVerifier;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public certificate check from the QR code (no sign-in; rate limited).
 */
final class VerifyCertificateController extends Controller
{
    public function __invoke(Request $request, string $uuid, CertificateVerifier $verifier): View
    {
        $result = $verifier->verify($uuid, (string) $request->query('t', ''));

        return view('certificates.verify', ['result' => $result]);
    }
}
