<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MpesaService
{
    protected string $baseUrl;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $shortcode;
    protected string $passkey;
    protected string $callbackUrl;

    public function __construct()
    {
        $this->baseUrl        = config('mpesa.base_url');
        $this->consumerKey    = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');
        $this->shortcode      = config('mpesa.shortcode');
        $this->passkey        = config('mpesa.passkey');
        $this->callbackUrl    = config('mpesa.callback_url');
    }

    /**
     * Generate OAuth access token from Daraja API.
     */
    public function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->get("{$this->baseUrl}/oauth/v1/generate", ['grant_type' => 'client_credentials']);

        if ($response->failed()) {
            Log::error('MPesa access token failed', ['response' => $response->body()]);
            throw new \RuntimeException('Failed to get MPesa access token.');
        }

        return $response->json('access_token');
    }


    public function stkPush(string $phone, float $amount, string $reference, string $description = 'Subscription Payment'): array
    {
        $token     = $this->getAccessToken();
        $timestamp = Carbon::now()->format('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => (int) ceil($amount),
            'PartyA'            => $phone,
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->callbackUrl,
            'AccountReference'  => $reference,
            'TransactionDesc'   => $description,
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", $payload);

        if ($response->failed()) {
            Log::error('MPesa STK push failed', ['payload' => $payload, 'response' => $response->body()]);
            throw new \RuntimeException('MPesa STK push request failed.');
        }

        return $response->json();
    }

    public function stkQuery(string $checkoutRequestId): array
    {
        $token     = $this->getAccessToken();
        $timestamp = Carbon::now()->format('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                'BusinessShortCode' => $this->shortcode,
                'Password'          => $password,
                'Timestamp'         => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ]);

        return $response->json();
    }


    public function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '+')) {
            $phone = ltrim($phone, '+');
        }

        return $phone;
    }
}