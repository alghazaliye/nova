package com.nova.messenger.ui.chat

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nova.messenger.data.model.Message
import com.nova.messenger.data.repository.MessageRepository
import com.nova.messenger.data.repository.Result
import com.nova.messenger.utils.TokenManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class ChatUiState {
    object Idle    : ChatUiState()
    object Loading : ChatUiState()
    data class Success(
        val messages: List<Message>,
        val conversationTitle: String,
        val myUserId: Long
    ) : ChatUiState()
    data class Error(val message: String) : ChatUiState()
}

@HiltViewModel
class ChatViewModel @Inject constructor(
    private val messageRepository: MessageRepository,
    private val tokenManager: TokenManager
) : ViewModel() {

    private val _uiState = MutableStateFlow<ChatUiState>(ChatUiState.Idle)
    val uiState: StateFlow<ChatUiState> = _uiState

    private var currentConversationId: Long = 0L
    private var currentMessages: MutableList<Message> = mutableListOf()

    fun loadMessages(conversationId: Long) {
        currentConversationId = conversationId
        viewModelScope.launch {
            _uiState.value = ChatUiState.Loading
            when (val result = messageRepository.getMessages(conversationId)) {
                is Result.Success -> {
                    currentMessages = result.data.toMutableList()
                    _uiState.value = ChatUiState.Success(
                        messages = currentMessages.toList(),
                        conversationTitle = "محادثة",
                        myUserId = tokenManager.getUserId() ?: 0L
                    )
                }
                is Result.Error -> {
                    _uiState.value = ChatUiState.Error(result.message)
                }
            }
        }
    }

    fun sendMessage(conversationId: Long, text: String) {
        viewModelScope.launch {
            when (val result = messageRepository.sendMessage(conversationId, text)) {
                is Result.Success -> {
                    currentMessages.add(result.data)
                    val currentState = _uiState.value
                    if (currentState is ChatUiState.Success) {
                        _uiState.value = currentState.copy(messages = currentMessages.toList())
                    }
                }
                is Result.Error -> {
                    // Show error toast or retry mechanism
                }
            }
        }
    }
}
