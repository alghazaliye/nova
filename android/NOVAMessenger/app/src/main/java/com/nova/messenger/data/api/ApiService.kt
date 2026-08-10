package com.nova.messenger.data.api

import com.nova.messenger.data.model.*
import retrofit2.Response
import retrofit2.http.*

/**
 * NOVA Messenger - Retrofit API Service
 * All endpoints match the PHP Backend REST API.
 */
interface ApiService {

    // =====================================================
    // Auth
    // =====================================================

    @POST("auth/register")
    suspend fun register(@Body body: RegisterRequest): Response<ApiResponse<Unit>>

    @POST("auth/login")
    suspend fun login(@Body body: LoginRequest): Response<ApiResponse<Unit>>

    @POST("auth/verify-otp")
    suspend fun verifyOtp(@Body body: VerifyOtpRequest): Response<ApiResponse<AuthResponse>>

    @POST("auth/logout")
    suspend fun logout(): Response<ApiResponse<Unit>>

    @GET("auth/me")
    suspend fun me(): Response<ApiResponse<User>>

    @POST("auth/refresh")
    suspend fun refresh(): Response<ApiResponse<TokenResponse>>

    // =====================================================
    // Users
    // =====================================================

    @GET("users/me")
    suspend fun getMe(): Response<ApiResponse<User>>

    @PUT("users/me")
    suspend fun updateMe(@Body body: UpdateProfileRequest): Response<ApiResponse<User>>

    @GET("users/search")
    suspend fun searchUsers(@Query("q") query: String): Response<ApiResponse<List<User>>>

    @GET("users/{id}")
    suspend fun getUser(@Path("id") id: Long): Response<ApiResponse<User>>

    @POST("users/{id}/block")
    suspend fun blockUser(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @DELETE("users/{id}/block")
    suspend fun unblockUser(@Path("id") id: Long): Response<ApiResponse<Unit>>

    // =====================================================
    // Conversations
    // =====================================================

    @GET("conversations")
    suspend fun getConversations(): Response<ApiResponse<List<Conversation>>>

    @POST("conversations")
    suspend fun createConversation(@Body body: CreateConversationRequest): Response<ApiResponse<Conversation>>

    @GET("conversations/{id}")
    suspend fun getConversation(@Path("id") id: Long): Response<ApiResponse<Conversation>>

    @DELETE("conversations/{id}")
    suspend fun deleteConversation(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @POST("conversations/{id}/mute")
    suspend fun muteConversation(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @POST("conversations/{id}/pin")
    suspend fun pinConversation(@Path("id") id: Long): Response<ApiResponse<Unit>>

    // =====================================================
    // Messages
    // =====================================================

    @GET("conversations/{id}/messages")
    suspend fun getMessages(
        @Path("id") conversationId: Long,
        @Query("before_id") beforeId: Long? = null,
        @Query("limit") limit: Int = 30
    ): Response<ApiResponse<List<Message>>>

    @POST("conversations/{id}/messages")
    suspend fun sendMessage(
        @Path("id") conversationId: Long,
        @Body body: SendMessageRequest
    ): Response<ApiResponse<Message>>

    @PUT("messages/{id}")
    suspend fun updateMessage(
        @Path("id") id: Long,
        @Body body: UpdateMessageRequest
    ): Response<ApiResponse<Message>>

    @DELETE("messages/{id}")
    suspend fun deleteMessage(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @POST("messages/{id}/read")
    suspend fun markMessageRead(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @POST("messages/{id}/reaction")
    suspend fun reactToMessage(
        @Path("id") id: Long,
        @Body body: ReactionRequest
    ): Response<ApiResponse<Unit>>

    // =====================================================
    // Stories
    // =====================================================

    @GET("stories")
    suspend fun getStories(): Response<ApiResponse<List<Story>>>

    @POST("stories")
    suspend fun createStory(@Body body: CreateStoryRequest): Response<ApiResponse<Story>>

    @POST("stories/{id}/view")
    suspend fun viewStory(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @DELETE("stories/{id}")
    suspend fun deleteStory(@Path("id") id: Long): Response<ApiResponse<Unit>>

    // =====================================================
    // Calls
    // =====================================================

    @POST("calls")
    suspend fun initiateCall(@Body body: InitiateCallRequest): Response<ApiResponse<Call>>

    @GET("calls")
    suspend fun getCalls(): Response<ApiResponse<List<Call>>>

    @POST("calls/{id}/answer")
    suspend fun answerCall(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @POST("calls/{id}/reject")
    suspend fun rejectCall(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @POST("calls/{id}/end")
    suspend fun endCall(@Path("id") id: Long): Response<ApiResponse<Unit>>

    // =====================================================
    // Notifications
    // =====================================================

    @GET("notifications")
    suspend fun getNotifications(): Response<ApiResponse<NotificationsResponse>>

    @POST("notifications/{id}/read")
    suspend fun markNotificationRead(@Path("id") id: Long): Response<ApiResponse<Unit>>

    @POST("notifications/read-all")
    suspend fun markAllNotificationsRead(): Response<ApiResponse<Unit>>
}
