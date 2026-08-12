<?php
/**
 * NOVA Messenger - Firebase Cloud Messaging (FCM) Helper (v1 API)
 * Uses Firebase Admin SDK Service Account + OAuth2 JWT bearer token.
 */

declare(strict_types=1);

class FCMHelper
{
    private static ?string $projectId = null;
    private static ?string $accessToken = null;
    private static int $tokenExpireAt = 0;
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const FCM_API_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    /**
     * Load service account from config file
     */
    private static function getServiceAccount(): ?array
    {
        $file = __DIR__ . '/../config/nova-firebase-sa.json';
        if (!is_file($file)) {
            $file = $_ENV['FCM_SA_FILE'] ?? '';
        }
        if (empty($file) || !is_file($file)) {
            return null;
        }
        $sa = json_decode(file_get_contents($file), true);
        return is_array($sa) && !empty($sa['client_email']) && !empty($sa['private_key']) ? $sa : null;
    }

    public static function initialize(): void
    {
        $sa = self::getServiceAccount();
        self::$projectId = $sa['project_id'] ?? $_ENV['FCM_PROJECT_ID'] ?? null;
    }

    public static function isEnabled(): bool
    {
        self::initialize();
        return self::getServiceAccount() !== null && !empty(self::$projectId);
    }

    /**
     * Obtain an OAuth2 access token via JWT (valid for ~1 hour)
     */
    public static function getAccessToken(): ?string
    {
        if (self::$accessToken !== null && time() < self::$tokenExpireAt - 60) {
            return self::$accessToken;
        }

        $sa = self::getServiceAccount();
        if (!$sa) return null;

        $now = time();
        $header = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = self::base64Url(json_encode([
            'iss'  => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'  => self::TOKEN_URL,
            'exp'  => $now + 3600,
            'iat'  => $now,
        ]));

        $signature = '';
        openssl_sign("$header.$claims", $signature, $sa['private_key'], 'SHA256');

        $jwt = "$header.$claims." . self::base64Url($signature);

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);
        if ($code !== 200 || empty($data['access_token'])) {
            error_log('FCM token exchange failed: ' . $resp);
            return null;
        }

        self::$accessToken = $data['access_token'];
        self::$tokenExpireAt = $now + ($data['expires_in'] ?? 3600);
        return self::$accessToken;
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null,
        bool $highPriority = false
    ): bool {
        $token = self::getAccessToken();
        if (!$token) return false;

        $message = [
            'token' => $deviceToken,
            'data' => array_map(fn($v) => is_string($v) ? $v : json_encode($v), $data),
        ];

        if ($title !== '' || $body !== '') {
            $notif = [];
            if ($title !== '') $notif['title'] = $title;
            if ($body !== '') $notif['body'] = $body;
            $message['notification'] = $notif;
            $message['android'] = [
                'priority' => 'high',
                'notification' => ['sound' => 'default', 'default_sound' => true],
            ];
            $message['apns'] = [
                'payload' => ['aps' => ['sound' => 'default']],
            ];
        } else {
            $message['android'] = ['priority' => 'high'];
        }

        return self::sendRequest(['message' => $message]);
    }

    public static function sendToDevices(array $deviceTokens, string $title, string $body, array $data = [], ?string $imageUrl = null): array
    {
        $results = [];
        foreach ($deviceTokens as $t) {
            $results[$t] = self::sendToDevice($t, $title, $body, $data, $imageUrl);
        }
        return $results;
    }

    public static function sendToTopic(string $topic, string $title, string $body, array $data = [], ?string $imageUrl = null): bool
    {
        $token = self::getAccessToken();
        if (!$token) return false;

        $message = [
            'topic' => $topic,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => array_map(fn($v) => is_string($v) ? $v : json_encode($v), $data),
        ];
        return self::sendRequest(['message' => $message]);
    }

    public static function sendMessageNotification(string $deviceToken, string $senderName, string $messagePreview, string $conversationId, ?string $senderAvatar = null): bool
    {
        return self::sendToDevice(
            $deviceToken,
            $senderName,
            $messagePreview,
            ['type' => 'message', 'conversation_id' => $conversationId, 'action' => 'open_conversation'],
            $senderAvatar
        );
    }

    public static function sendCallNotification(string $deviceToken, string $callerName, string $callId, ?string $callerAvatar = null): bool
    {
        return self::sendToDevice(
            $deviceToken,
            'مكالمة واردة',
            "اتصال من $callerName",
            ['type' => 'call', 'call_id' => $callId, 'action' => 'answer_call'],
            $callerAvatar
        );
    }

    public static function sendCallSignalNotification(string $deviceToken, string $callId, string $signalType, string $payloadJson, ?string $senderName = null): bool
    {
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            $payload = ['raw' => $payloadJson];
        }
        return self::sendToDevice(
            $deviceToken,
            '',
            '',
            array_merge(['type' => 'call_signal', 'call_id' => $callId, 'signal_type' => $signalType], $payload),
            null,
            true
        );
    }

    public static function sendStoryNotification(string $deviceToken, string $authorName, string $storyId, ?string $authorAvatar = null): bool
    {
        return self::sendToDevice(
            $deviceToken,
            'حالة جديدة',
            "$authorName أضاف حالة جديدة",
            ['type' => 'story', 'story_id' => $storyId, 'action' => 'open_story'],
            $authorAvatar
        );
    }

    public static function sendGroupNotification(string $deviceToken, string $groupName, string $message, string $groupId, ?string $groupAvatar = null): bool
    {
        return self::sendToDevice(
            $deviceToken,
            $groupName,
            $message,
            ['type' => 'group_message', 'group_id' => $groupId, 'action' => 'open_group'],
            $groupAvatar
        );
    }

    private static function sendRequest(array $payload): bool
    {
        $token = self::getAccessToken();
        if (!$token || empty(self::$projectId)) return false;

        $url = sprintf(self::FCM_API_URL, self::$projectId);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("FCM Request Error: $error");
            return false;
        }
        if ($httpCode !== 200) {
            error_log("FCM Response Error (HTTP $httpCode): $response");
            return false;
        }
        return true;
    }
}

FCMHelper::initialize();
