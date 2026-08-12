package com.nova.messenger.ui.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.nova.messenger.ui.auth.OtpScreen
import com.nova.messenger.ui.auth.PhoneScreen
import com.nova.messenger.ui.auth.ProfileScreen
import com.nova.messenger.ui.auth.SplashScreen
import com.nova.messenger.ui.call.CallScreen
import com.nova.messenger.ui.chat.ChatScreen
import com.nova.messenger.ui.home.HomeScreen

// =====================================================
// NOVA Messenger - Navigation Routes
// =====================================================

object Routes {
    const val SPLASH        = "splash"
    const val LOGIN         = "login"
    const val OTP           = "otp/{phone}"
    const val PROFILE_SETUP = "profile_setup/{phone}"
    const val HOME          = "home"
    const val CHAT          = "chat/{conversationId}"
    const val CALL          = "call/{userId}/{callType}"
    const val PROFILE       = "profile"
    const val SETTINGS      = "settings"
    const val CALLS         = "calls"
    const val STORIES       = "stories"
    const val CONTACTS      = "contacts"
    const val NOTIFICATIONS = "notifications"

    fun otp(phone: String)                     = "otp/$phone"
    fun profileSetup(phone: String)            = "profile_setup/$phone"
    fun chat(conversationId: Long)             = "chat/$conversationId"
    fun call(userId: Long, callType: String)   = "call/$userId/$callType"
}

@Composable
fun NovaNavGraph() {
    val navController = rememberNavController()

    NavHost(
        navController = navController,
        startDestination = Routes.SPLASH
    ) {
        composable(Routes.SPLASH) {
            SplashScreen(
                onNavigateToHome  = { navController.navigate(Routes.HOME)  { popUpTo(Routes.SPLASH) { inclusive = true } } },
                onNavigateToLogin = { navController.navigate(Routes.LOGIN) { popUpTo(Routes.SPLASH) { inclusive = true } } }
            )
        }

        composable(Routes.LOGIN) {
            PhoneScreen(
                onNavigateToOtp = { phone ->
                    navController.navigate(Routes.otp(phone))
                }
            )
        }

        composable(
            route = Routes.OTP,
            arguments = listOf(
                navArgument("phone") { type = NavType.StringType }
            )
        ) { backStackEntry ->
            OtpScreen(
                phone = backStackEntry.arguments?.getString("phone") ?: "",
                onNavigateToProfile = {
                    navController.navigate(Routes.profileSetup(
                        backStackEntry.arguments?.getString("phone") ?: ""
                    ))
                },
                onBackToPhone = { navController.popBackStack() }
            )
        }

        composable(
            route = Routes.PROFILE_SETUP,
            arguments = listOf(
                navArgument("phone") { type = NavType.StringType }
            )
        ) { backStackEntry ->
            ProfileScreen(
                phone = backStackEntry.arguments?.getString("phone") ?: "",
                onNavigateToHome = {
                    navController.navigate(Routes.HOME) {
                        popUpTo(Routes.LOGIN) { inclusive = true }
                    }
                }
            )
        }

        composable(Routes.HOME) {
            HomeScreen(
                onNavigateToChat = { convId ->
                    navController.navigate(Routes.chat(convId))
                },
                onNavigateToProfile = { navController.navigate(Routes.PROFILE) },
                onNavigateToSettings = { navController.navigate(Routes.SETTINGS) }
            )
        }

        composable(
            route = Routes.CHAT,
            arguments = listOf(navArgument("conversationId") { type = NavType.LongType })
        ) { backStackEntry ->
            ChatScreen(
                conversationId = backStackEntry.arguments?.getLong("conversationId") ?: 0L,
                onNavigateBack = { navController.popBackStack() },
                onNavigateToCall = { callType ->
                    val conversationId = backStackEntry.arguments?.getLong("conversationId") ?: 0L
                    // In private chats the other user id is fetched via conversation info;
                    // here we pass the conversation id as the callee target handled server-side.
                    navController.navigate(Routes.call(conversationId, callType))
                }
            )
        }

        composable(
            route = Routes.CALL,
            arguments = listOf(
                navArgument("userId") { type = NavType.LongType },
                navArgument("callType") { type = NavType.StringType }
            )
        ) { backStackEntry ->
            CallScreen(
                targetId = backStackEntry.arguments?.getLong("userId") ?: 0L,
                callType = backStackEntry.arguments?.getString("callType") ?: "voice",
                onCallEnded = { navController.popBackStack() }
            )
        }
    }
}
