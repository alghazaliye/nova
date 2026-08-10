package com.nova.messenger.data.fcm

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.nova.messenger.MainActivity
import com.nova.messenger.R

/**
 * NOVA Messenger - Firebase Cloud Messaging Service
 * Handles incoming push notifications.
 * FCM Server Key is stored ONLY on the PHP Backend, never in the APK.
 */
class NovaFirebaseMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        // Token is sent to the server during login/verify-otp
        // Store it locally for re-registration if needed
        getSharedPreferences("nova_fcm", Context.MODE_PRIVATE)
            .edit()
            .putString("fcm_token", token)
            .apply()
    }

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)

        val data         = remoteMessage.data
        val notification = remoteMessage.notification
        val type         = data["type"] ?: "message"

        when (type) {
            "new_message" -> showMessageNotification(
                title   = notification?.title ?: data["sender_name"] ?: "رسالة جديدة",
                body    = notification?.body  ?: data["body"] ?: "لديك رسالة جديدة",
                convId  = data["conversation_id"]?.toLongOrNull() ?: 0L
            )
            "call_incoming" -> showCallNotification(
                callerName = data["caller_name"] ?: "مكالمة واردة",
                callId     = data["call_id"]?.toLongOrNull() ?: 0L,
                callType   = data["call_type"] ?: "voice"
            )
            else -> showGeneralNotification(
                title = notification?.title ?: "NOVA Messenger",
                body  = notification?.body  ?: ""
            )
        }
    }

    private fun showMessageNotification(title: String, body: String, convId: Long) {
        val intent = Intent(this, MainActivity::class.java).apply {
            putExtra("conversation_id", convId)
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }
        val pendingIntent = PendingIntent.getActivity(
            this, convId.toInt(), intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notification = NotificationCompat.Builder(this, "nova_messages")
            .setSmallIcon(android.R.drawable.ic_dialog_email)
            .setContentTitle(title)
            .setContentText(body)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .build()

        val manager = getSystemService(NotificationManager::class.java)
        manager.notify(convId.toInt(), notification)
    }

    private fun showCallNotification(callerName: String, callId: Long, callType: String) {
        val typeLabel = if (callType == "video") "مكالمة فيديو" else "مكالمة صوتية"

        val notification = NotificationCompat.Builder(this, "nova_calls")
            .setSmallIcon(android.R.drawable.ic_menu_call)
            .setContentTitle("$typeLabel واردة")
            .setContentText("من $callerName")
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .build()

        val manager = getSystemService(NotificationManager::class.java)
        manager.notify(callId.toInt() + 10000, notification)
    }

    private fun showGeneralNotification(title: String, body: String) {
        val notification = NotificationCompat.Builder(this, "nova_general")
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(body)
            .setAutoCancel(true)
            .build()

        val manager = getSystemService(NotificationManager::class.java)
        manager.notify(System.currentTimeMillis().toInt(), notification)
    }
}
