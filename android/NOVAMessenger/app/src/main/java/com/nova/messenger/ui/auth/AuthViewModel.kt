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
    object Success : AuthUiState()
    data class Error(val message: String) : AuthUiState()
}

@HiltViewModel
class AuthViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow<AuthUiState>(AuthUiState.Idle)
    val uiState: StateFlow<AuthUiState> = _uiState

    fun requestOtp(phone: String, name: String) {
        if (phone.isBlank()) {
            _uiState.value = AuthUiState.Error("يرجى إدخال رقم الهاتف")
            return
        }
        if (name.isBlank()) {
            _uiState.value = AuthUiState.Error("يرجى إدخال اسمك")
            return
        }

        viewModelScope.launch {
            _uiState.value = AuthUiState.Loading
            when (val result = authRepository.requestOtp(phone, name)) {
                is Result.Success -> _uiState.value = AuthUiState.OtpSent
                is Result.Error   -> _uiState.value = AuthUiState.Error(result.message)
            }
        }
    }

    fun verifyOtp(phone: String, otp: String, name: String, deviceUuid: String, fcmToken: String?) {
        viewModelScope.launch {
            _uiState.value = AuthUiState.Loading
            when (val result = authRepository.verifyOtp(phone, otp, name, deviceUuid, fcmToken)) {
                is Result.Success -> _uiState.value = AuthUiState.Success
                is Result.Error   -> _uiState.value = AuthUiState.Error(result.message)
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            authRepository.logout()
        }
    }
}
