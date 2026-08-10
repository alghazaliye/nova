package com.nova.messenger.ui.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nova.messenger.data.model.Conversation
import com.nova.messenger.data.repository.MessageRepository
import com.nova.messenger.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class HomeUiState {
    object Idle    : HomeUiState()
    object Loading : HomeUiState()
    data class Success(val conversations: List<Conversation>) : HomeUiState()
    data class Error(val message: String) : HomeUiState()
}

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val messageRepository: MessageRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow<HomeUiState>(HomeUiState.Idle)
    val uiState: StateFlow<HomeUiState> = _uiState

    fun loadConversations() {
        viewModelScope.launch {
            _uiState.value = HomeUiState.Loading
            when (val result = messageRepository.getConversations()) {
                is Result.Success -> _uiState.value = HomeUiState.Success(result.data)
                is Result.Error   -> _uiState.value = HomeUiState.Error(result.message)
            }
        }
    }
}
