package com.nova.messenger.ui.chat

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nova.messenger.data.model.Message
import com.nova.messenger.data.repository.MessageRepository
import com.nova.messenger.data.repository.Result
import com.nova.messenger.utils.TokenManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.delay
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
        val isVerified: Boolean = false,
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
    private var conversationTitle: String = "محادثة"
    private var isVerified: Boolean = false

    fun loadMessages(conversationId: Long) {
        currentConversationId = conversationId
        viewModelScope.launch {
            _uiState.value = ChatUiState.Loading
            when (val result = messageRepository.getConversationInfo(conversationId)) {
                is Result.Success -> {
                    conversationTitle = result.data.title
                    isVerified = result.data.isVerified
                }
                is Result.Error -> { /* fallback to default title */ }
                else -> {}
            }
            when (val result = messageRepository.getMessages(conversationId)) {
                is Result.Success -> {
                    currentMessages = result.data.toMutableList()
                    emitSuccess()
                    startSync()
                }
                is Result.Error -> {
                    _uiState.value = ChatUiState.Error(result.message)
                }
                else -> {}
            }
        }
    }

    fun sendMessage(conversationId: Long, text: String) {
        viewModelScope.launch {
            when (val result = messageRepository.sendMessage(conversationId, text)) {
                is Result.Success -> {
                    currentMessages.add(result.data)
                    emitSuccess()
                }
                is Result.Error -> { /* error shown via toast at UI level */ }
                else -> {}
            }
        }
    }

    fun editMessage(messageId: Long, newBody: String) {
        viewModelScope.launch {
            when (val result = messageRepository.editMessage(messageId, newBody)) {
                is Result.Success -> {
                    val index = currentMessages.indexOfFirst { it.id == messageId }
                    if (index >= 0) currentMessages[index] = result.data
                    emitSuccess()
                }
                is Result.Error -> {}
                else -> {}
            }
        }
    }

    fun deleteMessage(messageId: Long, forAll: Boolean = false) {
        viewModelScope.launch {
            when (messageRepository.deleteMessage(messageId, forAll)) {
                is Result.Success -> {
                    val index = currentMessages.indexOfFirst { it.id == messageId }
                    if (index >= 0) {
                        currentMessages[index] = currentMessages[index].copy(deletedAt = "now")
                    }
                    emitSuccess()
                    if (forAll) {
                        // Full refresh to sync server state
                        when (val result = messageRepository.getMessages(currentConversationId)) {
                            is Result.Success -> {
                                currentMessages = result.data.toMutableList()
                                emitSuccess()
                            }
                            else -> {}
                        }
                    }
                }
                is Result.Error -> {}
                else -> {}
            }
        }
    }

    private fun emitSuccess() {
        _uiState.value = ChatUiState.Success(
            messages = currentMessages.toList(),
            conversationTitle = conversationTitle,
            isVerified = isVerified,
            myUserId = tokenManager.getUserId() ?: 0L
        )
    }

    private fun startSync() {
        viewModelScope.launch {
            while (true) {
                delay(5000)
                when (val result = messageRepository.getMessages(currentConversationId)) {
                    is Result.Success -> {
                        val currentKeys = currentMessages.map { it.id to it.status }
                        val serverKeys = result.data.map { it.id to it.status }
                        if (currentKeys != serverKeys) {
                            currentMessages = result.data.toMutableList()
                            emitSuccess()
                        }
                    }
                    else -> {}
                }
            }
        }
    }
}
