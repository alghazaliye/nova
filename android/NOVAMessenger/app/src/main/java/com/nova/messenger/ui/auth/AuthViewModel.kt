package com.nova.messenger.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nova.messenger.data.repository.AuthRepository
import com.nova.messenger.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class AuthUiState {
    object Idle    : AuthUiState()
    object Loading : AuthUiState()
    object OtpSent : AuthUiState()
    // Name/email profile setup is done after OTP verification (WhatsApp-style)
    object VerifySuccess : AuthUiState()
    object Success : AuthUiState()
    data class Error(val message: String) : AuthUiState()
}

@HiltViewModel
class AuthViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow<AuthUiState>(AuthUiState.Idle)
    val uiState: StateFlow<AuthUiState> = _uiState

    fun requestOtp(phone: String, countryCode: String? = null) {
        if (phone.isBlank()) {
            _uiState.value = AuthUiState.Error("يرجى إدخال رقم الهاتف")
            return
        }

        viewModelScope.launch {
            _uiState.value = AuthUiState.Loading
            when (val result = authRepository.requestOtp(phone, countryCode)) {
                is Result.Success -> _uiState.value = AuthUiState.OtpSent
                is Result.Error   -> _uiState.value = AuthUiState.Error(result.message)
            }
        }
    }

    // Re-send OTP (WhatsApp-style "use a different number" flow)
    fun resendOtp(phone: String, countryCode: String? = null) = requestOtp(phone, countryCode)

    fun verifyOtp(phone: String, otp: String, deviceUuid: String, fcmToken: String?) {
        viewModelScope.launch {
            _uiState.value = AuthUiState.Loading
            when (val result = authRepository.verifyOtp(phone, otp, deviceUuid, fcmToken)) {
                is Result.Success -> _uiState.value = AuthUiState.VerifySuccess
                is Result.Error   -> _uiState.value = AuthUiState.Error(result.message)
            }
        }
    }

    fun updateProfile(name: String, email: String?, onDone: (Boolean) -> Unit) {
        viewModelScope.launch {
            _uiState.value = AuthUiState.Loading
            val result = authRepository.updateProfile(name, email)
            when (result) {
                is Result.Success -> {
                    _uiState.value = AuthUiState.Success
                    onDone(true)
                }
                is Result.Error -> {
                    _uiState.value = AuthUiState.Error(result.message)
                    onDone(false)
                }
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            authRepository.logout()
        }
    }
}
