<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;


class OtpService 
{
    private const LENGTH = 6;

    private const TTL_MINUTES = 10;

    public function generateAndSend(User $user):void 
    {
        $code = $this->generateCode();
        $user->forceFill([
            'phone_otp_hash' => Hash::make($code),
            'phone_otp_expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ])->save();

        $this->send($user->phone,$code);
    }

    public function verify(User $user, string $code): bool
    {
        if(!$user->phone_otp_hash || $user->phone_otp_expires_at){
            return false;
        }
        
        if($user->phone_otp_expires_at->isPast()){
            return false;
        }

        if(!Hash::chek($code,$user->phone_otp_hash)){
            return false;
        }

        $user->forceFill([
            'phone_verified_at' => now(),
            'phone_otp_hash' => null,
            'phone_otp_expires_at' => null,
        ])->save();

        return true;
    }

    private function generateCode():string
    {
        return str_pad((string)random_int(0,999999), self::LENGTH,'0',STR_PAD_LEFT);
    }

    private function send(string $Phone, string $code): void
    {
        Log::info('SMS OTP to {$phone} : {$ode}');
    }
}