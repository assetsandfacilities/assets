<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 | Printable registration QR for the Asset Management office.
 |
 | The Facilities side already posts one of these on its door. Asset Management
 | asked for the same thing so a requestor can scan instead of hunting for the
 | system URL. The QR is only a shortcut to /register: the institutional email
 | check, the one-time code and (for requestors and approvers) the voucher all
 | still run exactly as before, so posting the sheet creates no new way in.
 */
class RegistrationQrController extends Controller
{
    public function index(Request $request)
    {
        $registrationUrl = route('register');

        // Same QR service the CAPEX asset labels already use, so the deployment
        // gains no new dependency.
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=560x560&margin=0&data='
            . urlencode($registrationUrl);

        return view('access-vouchers.registration-qr', [
            'title' => 'Registration QR Code',
            'subtitle' => 'Print this poster for the Asset Management office so requestors can scan straight into account registration.',
            'registrationUrl' => $registrationUrl,
            'qrImageUrl' => $qrImageUrl,
        ]);
    }
}
