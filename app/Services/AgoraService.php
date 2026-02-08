<?php

namespace App\Services;

use App\Models\Setting;

class AgoraService
{
    protected string $appId;
    protected string $appCertificate;
    protected int $tokenExpiry;

    public function __construct()
    {
        $this->appId = Setting::get('agora_app_id', '');
        $this->appCertificate = Setting::get('agora_app_certificate', '');
        $this->tokenExpiry = (int) Setting::get('agora_token_expiry', 86400);
    }

    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->appCertificate);
    }

    public function generateToken(string $channelName, int $uid, string $role = 'audience'): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $roleValue = $role === 'host' ? 1 : 2;
        $privilegeExpiredTs = time() + $this->tokenExpiry;

        return $this->buildToken(
            $this->appId,
            $this->appCertificate,
            $channelName,
            $uid,
            $roleValue,
            $privilegeExpiredTs
        );
    }

    public function generateRtmToken(int $uid): string
    {
        $privilegeExpiredTs = time() + $this->tokenExpiry;

        return $this->buildRtmToken(
            $this->appId,
            $this->appCertificate,
            (string) $uid,
            $privilegeExpiredTs
        );
    }

    protected function buildToken(
        string $appId,
        string $appCertificate,
        string $channelName,
        int $uid,
        int $role,
        int $privilegeExpiredTs
    ): string {
        $message = $this->packMessage($uid, $role, $privilegeExpiredTs);
        $signature = $this->generateSignature($appCertificate, $appId, $channelName, $uid, $message);
        
        return $this->encodeToken($appId, $signature, $message);
    }

    protected function buildRtmToken(
        string $appId,
        string $appCertificate,
        string $userId,
        int $privilegeExpiredTs
    ): string {
        $message = pack('V', $privilegeExpiredTs);
        $signature = hash_hmac('sha256', $appId . $userId . $message, $appCertificate, true);
        
        return base64_encode($appId . $signature . $message);
    }

    protected function packMessage(int $uid, int $role, int $privilegeExpiredTs): string
    {
        $message = pack('V', $uid);
        $message .= pack('V', $role);
        $message .= pack('V', $privilegeExpiredTs);
        $message .= pack('V', time());
        
        return $message;
    }

    protected function generateSignature(
        string $appCertificate,
        string $appId,
        string $channelName,
        int $uid,
        string $message
    ): string {
        $content = $appId . $channelName . pack('V', $uid) . $message;
        return hash_hmac('sha256', $content, $appCertificate, true);
    }

    protected function encodeToken(string $appId, string $signature, string $message): string
    {
        $content = pack('a32', $appId) . $signature . $message;
        return base64_encode($content);
    }

    public function getAppId(): string
    {
        return $this->appId;
    }
}
