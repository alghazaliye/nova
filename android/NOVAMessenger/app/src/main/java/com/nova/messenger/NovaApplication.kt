package com.nova.messenger

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class NovaApplication : Application() {

    override fun onCreate() {
        super.onCreate()
        createNotificationChannels()
    }

    private fun createNotificationChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(NotificationManager::class.java)

            // Messages channel
            NotificationChannel(
                "nova_messages",
                "الرسائل",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "إشعارات الرسائل الجديدة"
                enableVibration(true)
                manager.createNotificationChannel(this)
            }

            // Calls channel
            NotificationChannel(
                "nova_calls",
                "المكالمات",
                NotificationManager.IMPORTANCE_MAX
            ).apply {
                description = "إشعارات المكالمات الواردة"
                manager.createNotificationChannel(this)
            }

            // General channel
            NotificationChannel(
                "nova_general",
                "عام",
                NotificationManager.IMPORTANCE_DEFAULT
            ).apply {
                description = "إشعارات عامة"
                manager.createNotificationChannel(this)
            }
        }
    }
}
