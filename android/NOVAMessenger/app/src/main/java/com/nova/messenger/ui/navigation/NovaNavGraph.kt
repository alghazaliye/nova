package com.nova.messenger.ui.navigation

import androidx.compose.runtime.Composable
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.nova.messenger.ui.auth.LoginScreen
import com.nova.messenger.ui.auth.OtpScreen
import com.nova.messenger.ui.auth.SplashScreen
import com.nova.messenger.ui.chat.ChatScreen
import com.nova.messenger.ui.home.HomeScreen

// =====================================================
// NOVA Messenger - Navigation Routes
// =====================================================

object Routes {
    const val SPLASH        = "splash"
    const val LOGIN         = "login"
    const val OTP           = "otp/{phone}/{name}"
    const val HOME          = "home"
    const val CHAT          = "chat/{conversationId}"
    const val PROFILE       = "profile"
    const val SETTINGS      = "settings"
    const val CALLS         = "calls"
    const val STORIES       = "stories"
    const val CONTACTS      = "contacts"
    const val NOTIFICATIONS = "notifications"

    fun otp(phone: String, name: String)       = "otp/$phone/$name"
    fun chat(conversationId: Long)             = "chat/$conversationId"
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
            LoginScreen(
                onNavigateToOtp = { phone, name ->
                    navController.navigate(Routes.otp(phone, name))
                }
            )
        }

        composable(
            route = Routes.OTP,
            arguments = listOf(
                navArgument("phone") { type = NavType.StringType },
                navArgument("name")  { type = NavType.StringType }
            )
        ) { backStackEntry ->
            OtpScreen(
                phone = backStackEntry.arguments?.getString("phone") ?: "",
                name  = backStackEntry.arguments?.getString("name")  ?: "",
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
                onNavigateBack = { navController.popBackStack() }
            )
        }
    }
}
