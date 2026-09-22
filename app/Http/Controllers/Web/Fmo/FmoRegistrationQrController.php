<?php
namespace App\Http\Controllers\Web\Fmo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 | Panel revision: printable registration QR for the FMO office door.
 |
 | The Asset Management side hands out voucher codes, which suits staff and
 | approvers. Students have nothing to type, so the FMO gets a poster instead:
 | one QR that points at the ordinary registration page. No new account path is
 | created -- the QR is only a shortcut to /register, and the institutional
 | email + OTP checks there still apply exactly as before.
 |
 | Access is limited to the FMO side by the fmo_access middleware on the route
 | group (FMO Super Admin and FMO Admin/staff accounts).
 */
class FmoRegistrationQrController extends Controller
{
    public function index(Request $request)
    {
        $registrationUrl = route('register');

        // Same QR service the CAPEX asset labels already use, so the deployment
        // gains no new dependency.
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=560x560&margin=0&data='
            . urlencode($registrationUrl);

        return view('fmo.registration-qr', [
            'title' => 'Registration QR Code',
            'subtitle' => 'Print this poster for the FMO office door so students can scan straight into account registration.',
            'registrationUrl' => $registrationUrl,
            'qrImageUrl' => $qrImageUrl,
        ]);
    }
}
