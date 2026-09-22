<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SignatureData
{
    public static function fromRequest(Request $request, bool $required = false): ?string
    {
        /** @var UploadedFile|null $file */
        $file = $request->file('signature_file');
        $drawn = trim((string) $request->input('signature_drawn', ''));

        if ($file) {
            $mime = $file->getMimeType() ?: 'image/png';
            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                throw ValidationException::withMessages(['signature_file' => 'Signature must be PNG, JPG, or WEBP.']);
            }
            if ($file->getSize() > 2 * 1024 * 1024) {
                throw ValidationException::withMessages(['signature_file' => 'Signature image must not exceed 2 MB.']);
            }
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file->getRealPath()));
        }

        if ($drawn !== '') {
            if (!preg_match('#^data:image/(png|jpeg|webp);base64,#i', $drawn)) {
                throw ValidationException::withMessages(['signature_drawn' => 'The drawn signature data is invalid. Please clear and draw it again.']);
            }
            if (strlen($drawn) > 3_000_000) {
                throw ValidationException::withMessages(['signature_drawn' => 'The drawn signature is too large. Please draw it again.']);
            }
            return $drawn;
        }

        if ($required) {
            throw ValidationException::withMessages(['signature_file' => 'An e-signature is required for approver accounts. Upload a signature or draw one below.']);
        }

        return null;
    }

    public static function dummy(string $name): string
    {
        $safe = htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="520" height="150" viewBox="0 0 520 150">'
            . '<rect width="100%" height="100%" fill="white" fill-opacity="0"/>'
            . '<text x="18" y="88" font-family="cursive" font-size="44" font-style="italic" fill="#17213c">'.$safe.'</text>'
            . '<path d="M18 108 C145 128 330 95 500 111" fill="none" stroke="#17213c" stroke-width="2"/>'
            . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
