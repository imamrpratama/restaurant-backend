<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class AuthController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'requires_2fa' => false,
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if 2FA is enabled
        if ($user->two_factor_enabled) {
            // Create a temporary token that expires in 5 minutes
            $tempToken = $user->createToken('2fa_temp', ['2fa:verify'], now()->addMinutes(5))->plainTextToken;

            return response()->json([
                'message' => '2FA verification required',
                'requires_2fa' => true,
                'temp_token' => $tempToken,
            ]);
        }

        // No 2FA, create full access token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'requires_2fa' => false,
        ]);
    }

    /**
     * Verify 2FA OTP code
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA is not enabled for this account',
            ], 400);
        }

        // Verify the OTP
        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->otp);

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid OTP code',
            ], 422);
        }

        // Delete temporary token
        $user->tokens()->where('name', '2fa_temp')->delete();

        // Create full access token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => '2FA verification successful',
        ]);
    }

    /**
     * Enable 2FA - Generate secret and QR code
     */
    public function enable2FA(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA is already enabled',
            ], 400);
        }

        // Generate secret key
        $secret = $this->google2fa->generateSecretKey();

        // Generate recovery codes (8 codes, 10 characters each)
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(bin2hex(random_bytes(5)));
        }

        // Save secret and recovery codes (not enabled yet, waiting for confirmation)
        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = json_encode(array_map(function($code) {
            return Hash::make($code);
        }, $recoveryCodes));
        $user->save();

        // Generate QR Code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Generate QR Code as SVG
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'secret' => $secret,
            'qr_code_svg' => base64_encode($qrCodeSvg),
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes, // Send plain codes to user (only once)
            'message' => 'Scan this QR code with your authenticator app',
        ]);
    }

    /**
     * Confirm 2FA - Verify OTP and enable 2FA
     */
    public function confirm2FA(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json([
                'message' => 'Please enable 2FA first',
            ], 400);
        }

        // Verify the OTP
        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->otp);

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid OTP code.  Please try again.',
            ], 422);
        }

        // Enable 2FA
        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'message' => '2FA has been enabled successfully',
            'google2fa_enabled' => true,
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Incorrect password',
            ], 422);
        }

        // Verify OTP
        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->otp);

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid OTP code',
            ], 422);
        }

        // Disable 2FA and clear secrets
        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'message' => '2FA has been disabled',
            'google2fa_enabled' => false,
        ]);
    }

    /**
     * Verify recovery code (in case user loses authenticator)
     */
    public function verifyRecoveryCode(Request $request)
    {
        $request->validate([
            'recovery_code' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->two_factor_enabled || !$user->two_factor_recovery_codes) {
            return response()->json([
                'message' => 'Recovery codes not available',
            ], 400);
        }

        $recoveryCodes = json_decode($user->two_factor_recovery_codes, true);
        $codeFound = false;

        foreach ($recoveryCodes as $index => $hashedCode) {
            if (Hash::check($request->recovery_code, $hashedCode)) {
                $codeFound = true;
                // Remove used recovery code
                unset($recoveryCodes[$index]);
                $user->two_factor_recovery_codes = json_encode(array_values($recoveryCodes));
                $user->save();
                break;
            }
        }

        if (!$codeFound) {
            return response()->json([
                'message' => 'Invalid recovery code',
            ], 422);
        }

        // Delete temporary token
        $user->tokens()->where('name', '2fa_temp')->delete();

        // Create full access token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Recovery code verified successfully',
            'remaining_codes' => count($recoveryCodes),
        ]);
    }

    /**
     * Google Sign In
     */
    public function googleSignIn(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        // Here you would verify the Google ID token
        // For now, this is a placeholder

        return response()->json([
            'message' => 'Google Sign-In not fully implemented',
        ], 501);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
