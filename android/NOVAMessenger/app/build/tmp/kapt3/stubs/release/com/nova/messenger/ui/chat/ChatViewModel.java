package com.nova.messenger.ui.chat;

@kotlin.Metadata(mv = {1, 9, 0}, k = 1, xi = 48, d1 = {"\u0000N\n\u0002\u0018\u0002\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\u0018\u0002\n\u0000\n\u0002\u0010\u000e\n\u0000\n\u0002\u0010\t\n\u0000\n\u0002\u0010!\n\u0002\u0018\u0002\n\u0000\n\u0002\u0010\u000b\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0003\n\u0002\u0010\u0002\n\u0002\b\u000b\b\u0007\u0018\u00002\u00020\u0001B\u0017\b\u0007\u0012\u0006\u0010\u0002\u001a\u00020\u0003\u0012\u0006\u0010\u0004\u001a\u00020\u0005\u00a2\u0006\u0002\u0010\u0006J\u0018\u0010\u0017\u001a\u00020\u00182\u0006\u0010\u0019\u001a\u00020\r2\b\b\u0002\u0010\u001a\u001a\u00020\u0012J\u0016\u0010\u001b\u001a\u00020\u00182\u0006\u0010\u0019\u001a\u00020\r2\u0006\u0010\u001c\u001a\u00020\u000bJ\b\u0010\u001d\u001a\u00020\u0018H\u0002J\u000e\u0010\u001e\u001a\u00020\u00182\u0006\u0010\u001f\u001a\u00020\rJ\u0016\u0010 \u001a\u00020\u00182\u0006\u0010\u001f\u001a\u00020\r2\u0006\u0010!\u001a\u00020\u000bJ\b\u0010\"\u001a\u00020\u0018H\u0002R\u0014\u0010\u0007\u001a\b\u0012\u0004\u0012\u00020\t0\bX\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u000e\u0010\n\u001a\u00020\u000bX\u0082\u000e\u00a2\u0006\u0002\n\u0000R\u000e\u0010\f\u001a\u00020\rX\u0082\u000e\u00a2\u0006\u0002\n\u0000R\u0014\u0010\u000e\u001a\b\u0012\u0004\u0012\u00020\u00100\u000fX\u0082\u000e\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0011\u001a\u00020\u0012X\u0082\u000e\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0002\u001a\u00020\u0003X\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0004\u001a\u00020\u0005X\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u0017\u0010\u0013\u001a\b\u0012\u0004\u0012\u00020\t0\u0014\u00a2\u0006\b\n\u0000\u001a\u0004\b\u0015\u0010\u0016\u00a8\u0006#"}, d2 = {"Lcom/nova/messenger/ui/chat/ChatViewModel;", "Landroidx/lifecycle/ViewModel;", "messageRepository", "Lcom/nova/messenger/data/repository/MessageRepository;", "tokenManager", "Lcom/nova/messenger/utils/TokenManager;", "(Lcom/nova/messenger/data/repository/MessageRepository;Lcom/nova/messenger/utils/TokenManager;)V", "_uiState", "Lkotlinx/coroutines/flow/MutableStateFlow;", "Lcom/nova/messenger/ui/chat/ChatUiState;", "conversationTitle", "", "currentConversationId", "", "currentMessages", "", "Lcom/nova/messenger/data/model/Message;", "isVerified", "", "uiState", "Lkotlinx/coroutines/flow/StateFlow;", "getUiState", "()Lkotlinx/coroutines/flow/StateFlow;", "deleteMessage", "", "messageId", "forAll", "editMessage", "newBody", "emitSuccess", "loadMessages", "conversationId", "sendMessage", "text", "startSync", "app_release"})
@dagger.hilt.android.lifecycle.HiltViewModel()
public final class ChatViewModel extends androidx.lifecycle.ViewModel {
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.data.repository.MessageRepository messageRepository = null;
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.utils.TokenManager tokenManager = null;
    @org.jetbrains.annotations.NotNull()
    private final kotlinx.coroutines.flow.MutableStateFlow<com.nova.messenger.ui.chat.ChatUiState> _uiState = null;
    @org.jetbrains.annotations.NotNull()
    private final kotlinx.coroutines.flow.StateFlow<com.nova.messenger.ui.chat.ChatUiState> uiState = null;
    private long currentConversationId = 0L;
    @org.jetbrains.annotations.NotNull()
    private java.util.List<com.nova.messenger.data.model.Message> currentMessages;
    @org.jetbrains.annotations.NotNull()
    private java.lang.String conversationTitle = "\u0645\u062d\u0627\u062f\u062b\u0629";
    private boolean isVerified = false;
    
    @javax.inject.Inject()
    public ChatViewModel(@org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.repository.MessageRepository messageRepository, @org.jetbrains.annotations.NotNull()
    com.nova.messenger.utils.TokenManager tokenManager) {
        super();
    }
    
    @org.jetbrains.annotations.NotNull()
    public final kotlinx.coroutines.flow.StateFlow<com.nova.messenger.ui.chat.ChatUiState> getUiState() {
        return null;
    }
    
    public final void loadMessages(long conversationId) {
    }
    
    public final void sendMessage(long conversationId, @org.jetbrains.annotations.NotNull()
    java.lang.String text) {
    }
    
    public final void editMessage(long messageId, @org.jetbrains.annotations.NotNull()
    java.lang.String newBody) {
    }
    
    public final void deleteMessage(long messageId, boolean forAll) {
    }
    
    private final void emitSuccess() {
    }
    
    private final void startSync() {
    }
}