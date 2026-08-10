package com.nova.messenger.data.repository

import com.nova.messenger.data.api.ApiClient
import com.nova.messenger.data.model.*
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class MessageRepository @Inject constructor(
    private val apiClient: ApiClient
) {
    suspend fun getConversations(): Result<List<Conversation>> {
        return try {
            val response = apiClient.service.getConversations()
            val body = response.body()
            if (response.isSuccessful && body?.success == true) {
                Result.Success(body.data ?: emptyList())
            } else {
                Result.Error(body?.message ?: "فشل في جلب المحادثات")
            }
        } catch (e: Exception) {
            Result.Error("تعذر الاتصال بالخادم")
        }
    }

    suspend fun getMessages(conversationId: Long, beforeId: Long? = null): Result<List<Message>> {
        return try {
            val response = apiClient.service.getMessages(conversationId, beforeId)
            val body = response.body()
            if (response.isSuccessful && body?.success == true) {
                Result.Success(body.data ?: emptyList())
            } else {
                Result.Error(body?.message ?: "فشل في جلب الرسائل")
            }
        } catch (e: Exception) {
            Result.Error("تعذر الاتصال بالخادم")
        }
    }

    suspend fun sendMessage(
        conversationId: Long,
        text: String,
        replyToId: Long? = null
    ): Result<Message> {
        val clientMessageId = UUID.randomUUID().toString()
        return try {
            val response = apiClient.service.sendMessage(
                conversationId,
                SendMessageRequest(
                    clientMessageId = clientMessageId,
                    type = "text",
                    body = text,
                    replyToMessageId = replyToId
                )
            )
            val body = response.body()
            if (response.isSuccessful && body?.success == true && body.data != null) {
                Result.Success(body.data)
            } else {
                Result.Error(body?.message ?: "فشل في إرسال الرسالة")
            }
        } catch (e: Exception) {
            Result.Error("تعذر إرسال الرسالة. ستتم المحاولة مجدداً عند عودة الاتصال")
        }
    }

    suspend fun markMessageRead(messageId: Long): Result<Unit> {
        return try {
            apiClient.service.markMessageRead(messageId)
            Result.Success(Unit)
        } catch (e: Exception) {
            Result.Error("فشل في تحديث حالة القراءة")
        }
    }

    suspend fun deleteMessage(messageId: Long): Result<Unit> {
        return try {
            val response = apiClient.service.deleteMessage(messageId)
            if (response.isSuccessful) Result.Success(Unit)
            else Result.Error("فشل في حذف الرسالة")
        } catch (e: Exception) {
            Result.Error("تعذر الاتصال بالخادم")
        }
    }
}
