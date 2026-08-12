package com.nova.messenger.data.repository

import com.nova.messenger.data.api.ApiClient
import com.nova.messenger.data.model.*
import com.nova.messenger.utils.TokenManager
import javax.inject.Inject
import javax.inject.Singleton

sealed class Result<out T> {
    data class Success<T>(val data: T) : Result<T>()
    data class Error(val message: String, val code: String? = null) : Result<Nothing>()
}

@Singleton
class AuthRepository @Inject constructor(
    private val apiClient: ApiClient,
    private val tokenManager: TokenManager
) {
    suspend fun requestOtp(phone: String, countryCode: String? = null): Result<Unit> {
        return try {
            val response = apiClient.service.register(RegisterRequest(phone, countryCode))
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(Unit)
            } else {
                Result.Error(response.body()?.message ?: "فشل في إرسال رمز التحقق")
            }
        } catch (e: Exception) {
            Result.Error("تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت")
        }
    }

    suspend fun verifyOtp(phone: String, otp: String, deviceUuid: String, fcmToken: String?, name: String? = null): Result<AuthResponse> {
        return try {
            val response = apiClient.service.verifyOtp(
                VerifyOtpRequest(phone, otp, name, deviceUuid, fcmToken)
            )
            val body = response.body()
            if (response.isSuccessful && body?.success == true && body.data != null) {
                tokenManager.saveToken(body.data.token)
                tokenManager.saveUserId(body.data.user.id)
                Result.Success(body.data)
            } else {
                Result.Error(body?.message ?: "رمز التحقق غير صحيح")
            }
        } catch (e: Exception) {
            Result.Error("تعذر الاتصال بالخادم")
        }
    }

    suspend fun logout(): Result<Unit> {
        return try {
            apiClient.service.logout()
            tokenManager.clear()
            Result.Success(Unit)
        } catch (e: Exception) {
            tokenManager.clear()
            Result.Success(Unit) // Clear locally even if server fails
        }
    }

    fun isLoggedIn() = tokenManager.isLoggedIn()

    suspend fun updateProfile(name: String, email: String?): Result<User> {
        return try {
            val response = apiClient.service.updateMe(UpdateProfileRequest(name = name, email = email))
            val body = response.body()
            if (response.isSuccessful && body?.success == true && body.data != null) {
                Result.Success(body.data)
            } else {
                Result.Error(body?.message ?: "فشل في تحديث الملف الشخصي")
            }
        } catch (e: Exception) {
            Result.Error("تعذر الاتصال بالخادم")
        }
    }
}
