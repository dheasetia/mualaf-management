<?php

declare(strict_types=1);

namespace App\Support\Antivirus;

use RuntimeException;

/**
 * clamd over TCP with the INSTREAM command (no extra dependency): the file
 * is sent in length-prefixed chunks and clamd answers "stream: OK" or
 * "stream: <signature> FOUND".
 */
final class ClamAvScanner implements FileScanner
{
    private const int CHUNK = 8192;

    public function scan(string $bytes): ?string
    {
        $host = (string) config('documents.antivirus.host');
        $port = (int) config('documents.antivirus.port');
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, (float) config('documents.antivirus.timeout'));

        if ($socket === false) {
            throw new RuntimeException("ClamAV unreachable at {$host}:{$port}: {$errorMessage}");
        }

        try {
            stream_set_timeout($socket, (int) config('documents.antivirus.timeout'));
            fwrite($socket, "zINSTREAM\0");

            foreach (str_split($bytes, self::CHUNK) as $chunk) {
                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }

            fwrite($socket, pack('N', 0));
            $reply = trim((string) stream_get_contents($socket), "\0\n ");
        } finally {
            fclose($socket);
        }

        if (str_ends_with($reply, 'OK')) {
            return null;
        }

        if (preg_match('/^stream: (.+) FOUND$/', $reply, $match) === 1) {
            return $match[1];
        }

        throw new RuntimeException('Unexpected ClamAV reply: '.$reply);
    }
}
