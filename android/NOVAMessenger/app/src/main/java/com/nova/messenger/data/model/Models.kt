package com.nova.messenger.data.model

import com.google.gson.annotations.SerializedName

// =====================================================
// API Response Wrapper
// =====================================================

data class ApiResponse<T>(
    val success: Boolean,
    val message: String,
    val data: T? = null,
    @SerializedName("error_code") val errorCode: String? = null,
    val errors: Map<String, String>? = null
)

// =====================================================
// Auth Models
// =====================================================

// WhatsApp-style: first step only needs the phone number (+ optional country code)
data class RegisterRequest(
    val phone: String,
    @SerializedName("country_code") val countryCode: String? = null
)

data class LoginRequest(
    val phone: String
)

// WhatsApp-style: OTP verification does not require a name (profile setup comes next)
data class VerifyOtpRequest(
    val phone: String,
    val otp: String,
    val name: String? = null,
    @SerializedName("device_uuid") val deviceUuid: String,
    @SerializedName("fcm_token") val fcmToken: String? = null
)

data class AuthResponse(
    val token: String,
    val user: User
)

data class TokenResponse(
    val token: String
)

// =====================================================
// User Models
// =====================================================

data class User(
    val id: Long,
    val uuid: String,
    val phone: String,
    val email: String? = null,
    val name: String,
    val username: String? = null,
    val bio: String? = null,
    val avatar: String? = null,
    @SerializedName("status_text") val statusText: String? = null,
    @SerializedName("is_online") val isOnline: Boolean = false,
    @SerializedName("last_seen") val lastSeen: String? = null,
    @SerializedName("is_verified") val isVerified: Boolean = false,
    @SerializedName("created_at") val createdAt: String
)

data class UpdateProfileRequest(
    val name: String? = null,
    val username: String? = null,
    val bio: String? = null,
    @SerializedName("status_text") val statusText: String? = null,
    val email: String? = null
)

// =====================================================
// Conversation Models
// =====================================================

data class Conversation(
    val id: Long,
    val uuid: String,
    val type: String, // "private" | "group"
    val title: String?,
    val avatar: String?,
    @SerializedName("is_muted") val isMuted: Boolean = false,
    @SerializedName("is_pinned") val isPinned: Boolean = false,
    @SerializedName("unread_count") val unreadCount: Int = 0,
    @SerializedName("last_message_body") val lastMessageBody: String? = null,
    @SerializedName("last_message_at") val lastMessageAt: String? = null,
    @SerializedName("other_user") val otherUser: User? = null,
    @SerializedName("updated_at") val updatedAt: String
)

data class CreateConversationRequest(
    val type: String = "private",
    @SerializedName("user_id") val userId: Long? = null,
    val title: String? = null,
    val members: List<Long>? = null
)

// =====================================================
// Message Models
// =====================================================

data class Message(
    val id: Long,
    val uuid: String,
    @SerializedName("conversation_id") val conversationId: Long,
    @SerializedName("sender_id") val senderId: Long,
    @SerializedName("reply_to_message_id") val replyToMessageId: Long? = null,
    val type: String, // text | image | video | audio | voice | file | location | contact | system
    val body: String? = null,
    @SerializedName("file_id") val fileId: Long? = null,
    @SerializedName("client_message_id") val clientMessageId: String,
    val status: String, // sending | sent | delivered | read | failed | deleted
    @SerializedName("sender_name") val senderName: String,
    @SerializedName("sender_avatar") val senderAvatar: String? = null,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("deleted_at") val deletedAt: String? = null,
    @SerializedName("edited_at") val editedAt: String? = null,
    @SerializedName("edited_by") val editedBy: Long? = null
)

data class SendMessageRequest(
    @SerializedName("client_message_id") val clientMessageId: String,
    val type: String = "text",
    val body: String? = null,
    @SerializedName("reply_to_message_id") val replyToMessageId: Long? = null,
    @SerializedName("file_id") val fileId: Long? = null
)

data class UpdateMessageRequest(val body: String)

data class ReactionRequest(val reaction: String)

// =====================================================
// Story Models
// =====================================================

data class Story(
    val id: Long,
    val uuid: String,
    @SerializedName("user_id") val userId: Long,
    val type: String,
    val text: String? = null,
    val privacy: String,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("expires_at") val expiresAt: String,
    @SerializedName("user_name") val userName: String,
    @SerializedName("user_avatar") val userAvatar: String? = null,
    @SerializedName("view_count") val viewCount: Int = 0,
    @SerializedName("viewed_by_me") val viewedByMe: Int = 0
)

data class CreateStoryRequest(
    val type: String = "text",
    val text: String? = null,
    @SerializedName("file_id") val fileId: Long? = null,
    val privacy: String = "contacts"
)

// =====================================================
// Call Models
// =====================================================

data class Call(
    val id: Long,
    val uuid: String,
    @SerializedName("caller_id") val callerId: Long,
    @SerializedName("call_type") val callType: String, // voice | video
    val status: String, // calling | ringing | answered | missed | rejected | ended | failed
    @SerializedName("started_at") val startedAt: String? = null,
    @SerializedName("ended_at") val endedAt: String? = null,
    val duration: Int? = null,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("caller_name") val callerName: String? = null,
    @SerializedName("caller_avatar") val callerAvatar: String? = null
)

data class InitiateCallRequest(
    @SerializedName("callee_id") val calleeId: Long,
    @SerializedName("call_type") val callType: String = "voice"
)

// =====================================================
// Notification Models
// =====================================================

data class Notification(
    val id: Long,
    val type: String,
    val title: String,
    val body: String? = null,
    @SerializedName("is_read") val isRead: Boolean = false,
    @SerializedName("created_at") val createdAt: String
)

data class NotificationsResponse(
    val notifications: List<Notification>,
    @SerializedName("unread_count") val unreadCount: Int
)

// =====================================================
// Realtime Events
// =====================================================

data class RealtimeEvent(
    val event: String,
    val data: Map<String, Any>
)
