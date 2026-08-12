<?php
/**
 * NOVA Messenger - Firebase Cloud Messaging (FCM) Helper
 * Handles sending push notifications to users
 */

declare(strict_types=1);

class FCMHelper
{
    private static ?string $serverKey = null;
    private static ?string $projectId = null;
    private const FCM_API_URL = 'https://fcm.googleapis.com/fcm/send';

    /**
     * Initialize FCM with credentials from environment
     */
    public static function initialize(): void
    {
        self::$serverKey = $_ENV['FCM_SERVER_KEY'] ?? null;
        self::$projectId = $_ENV['FCM_PROJECT_ID'] ?? null;
    }

    /**
     * Check if FCM is enabled and configured
     */
    public static function isEnabled(): bool
    {
        return !empty(self::$serverKey) && !empty(self::$projectId);
    }

    /**
     * Send notification to a single device
     */
        public static function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null,
        bool $highPriority = false
    ): bool {
        if (!self::isEnabled()) {
            error_log('FCM is not enabled or configured');
            return false;
        }
        if ($highPriority) {
            // Data-only high-priority message for instant WebRTC signaling delivery
            return self::sendRequest([
                'to' => $deviceToken,
                'data' => array_map(fn($v) => is_string($v) ? $v : json_encode($v), $data),
                'priority' => 'high',
                'content_available' => true,
            ]);
        }

        $payload = [
            'to' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'priority' => 'high',
            ],
            'data' => array_map(fn($v) => is_string($v) ? $v : json_encode($v), $data),
        ];

        if ($imageUrl) {
            $payload['notification']['image'] = $imageUrl;
        }

        return self::sendRequest($payload);
    }

    /**
     * Send notification to multiple devices
     */
    public static function sendToDevices(
        array $deviceTokens,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        $results = [];
        foreach ($deviceTokens as $token) {
            $results[$token] = self::sendToDevice($token, $title, $body, $data, $imageUrl);
        }
        return $results;
    }

    /**
     * Send notification to a topic
     */
    public static function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): bool {
        if (!self::isEnabled()) {
            error_log('FCM is not enabled or configured');
            return false;
        }

        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'priority' => 'high',
            ],
            'data' => $data,
        ];

        if ($imageUrl) {
            $payload['notification']['image'] = $imageUrl;
        }

        return self::sendRequest($payload);
    }

    /**
     * Send a message notification (for new messages)
     */
    public static function sendMessageNotification(
        string $deviceToken,
        string $senderName,
        string $messagePreview,
        string $conversationId,
        ?string $senderAvatar = null
    ): bool {
        return self::sendToDevice(
            $deviceToken,
            $senderName,
            $messagePreview,
            [
                'type' => 'message',
                'conversation_id' => $conversationId,
                'action' => 'open_conversation',
            ],
            $senderAvatar
        );
    }

    /**
     * Send a call notification
     */
    public static function sendCallNotification(
        string $deviceToken,
        string $callerName,
        string $callId,
        ?string $callerAvatar = null
    ): bool {
        return self::sendToDevice(
            $deviceToken,
            'مكالمة واردة',
            "اتصال من $callerName",
            [
                'type' => 'call',
                'call_id' => $callId,
                'action' => 'answer_call',
            ],
            $callerAvatar
        );
    }

    /**
     * Send a high-priority WebRTC call signaling data message
     */
    public static function sendCallSignalNotification(
        string $deviceToken,
        string $callId,
        string $signalType,
        string $payloadJson,
        ?string $senderName = null
    ): bool {
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            $payload = ['raw' => $payloadJson];
        }

        return self::sendToDevice(
            $deviceToken,
            $senderName ? "إشارة مكالمة من $senderName" : 'إشارة مكالمة',
            '',
            array_merge(['type' => 'call_signal', 'call_id' => $callId, 'signal_type' => $signalType], $payload),
            null,
            true
        );
    }

    /**
     * Send a story notification
     */
    public static function sendStoryNotification(
        string $deviceToken,
        string $authorName,
        string $storyId,
        ?string $authorAvatar = null
    ): bool {
        return self::sendToDevice(
            $deviceToken,
            'حالة جديدة',
            "$authorName أضاف حالة جديدة",
            [
                'type' => 'story',
                'story_id' => $storyId,
                'action' => 'open_story',
            ],
            $authorAvatar
        );
    }

    /**
     * Send a group notification
     */
    public static function sendGroupNotification(
        string $deviceToken,
        string $groupName,
        string $message,
        string $groupId,
        ?string $groupAvatar = null
    ): bool {
        return self::sendToDevice(
            $deviceToken,
            $groupName,
            $message,
            [
                'type' => 'group_message',
                'group_id' => $groupId,
                'action' => 'open_group',
            ],
            $groupAvatar
        );
    }

    /**
     * Send the actual HTTP request to FCM
     */
    private static function sendRequest(array $payload): bool
    {
        $ch = curl_init(self::FCM_API_URL);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: key=' . self::$serverKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
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

        $result = json_decode($response, true);
        
        if (isset($result['failure']) && $result['failure'] > 0) {
            error_log("FCM Delivery Failed: " . json_encode($result));
            return false;
        }

        return true;
    }
}

// Initialize FCM on load
FCMHelper::initialize();
