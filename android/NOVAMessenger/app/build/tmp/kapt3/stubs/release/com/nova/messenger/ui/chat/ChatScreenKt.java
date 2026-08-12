package com.nova.messenger.ui.chat;

@kotlin.Metadata(mv = {1, 9, 0}, k = 2, xi = 48, d1 = {"\u0000:\n\u0000\n\u0002\u0010\u0002\n\u0000\n\u0002\u0010\t\n\u0000\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0002\u0010\u000e\n\u0002\u0018\u0002\n\u0002\b\u0003\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0000\n\u0002\u0010\u000b\n\u0002\b\b\u001aK\u0010\u0000\u001a\u00020\u00012\u0006\u0010\u0002\u001a\u00020\u00032\f\u0010\u0004\u001a\b\u0012\u0004\u0012\u00020\u00010\u00052!\u0010\u0006\u001a\u001d\u0012\u0013\u0012\u00110\b\u00a2\u0006\f\b\t\u0012\b\b\n\u0012\u0004\b\b(\u000b\u0012\u0004\u0012\u00020\u00010\u00072\b\b\u0002\u0010\f\u001a\u00020\rH\u0007\u001aH\u0010\u000e\u001a\u00020\u00012\u0006\u0010\u000f\u001a\u00020\u00102\u0006\u0010\u0011\u001a\u00020\u00122\u0006\u0010\u0013\u001a\u00020\u00122\u0012\u0010\u0014\u001a\u000e\u0012\u0004\u0012\u00020\u0010\u0012\u0004\u0012\u00020\u00010\u00072\u0012\u0010\u0015\u001a\u000e\u0012\u0004\u0012\u00020\u0010\u0012\u0004\u0012\u00020\u00010\u0007H\u0003\u001a2\u0010\u0016\u001a\u00020\u00012\u0006\u0010\u0017\u001a\u00020\b2\u0012\u0010\u0018\u001a\u000e\u0012\u0004\u0012\u00020\b\u0012\u0004\u0012\u00020\u00010\u00072\f\u0010\u0019\u001a\b\u0012\u0004\u0012\u00020\u00010\u0005H\u0003\u00a8\u0006\u001a"}, d2 = {"ChatScreen", "", "conversationId", "", "onNavigateBack", "Lkotlin/Function0;", "onNavigateToCall", "Lkotlin/Function1;", "", "Lkotlin/ParameterName;", "name", "callType", "viewModel", "Lcom/nova/messenger/ui/chat/ChatViewModel;", "MessageBubble", "message", "Lcom/nova/messenger/data/model/Message;", "isMe", "", "isEditable", "onLongPress", "onEditRequest", "MessageComposer", "text", "onTextChange", "onSend", "app_release"})
public final class ChatScreenKt {
    
    @kotlin.OptIn(markerClass = {androidx.compose.foundation.ExperimentalFoundationApi.class})
    @androidx.compose.runtime.Composable()
    public static final void ChatScreen(long conversationId, @org.jetbrains.annotations.NotNull()
    kotlin.jvm.functions.Function0<kotlin.Unit> onNavigateBack, @org.jetbrains.annotations.NotNull()
    kotlin.jvm.functions.Function1<? super java.lang.String, kotlin.Unit> onNavigateToCall, @org.jetbrains.annotations.NotNull()
    com.nova.messenger.ui.chat.ChatViewModel viewModel) {
    }
    
    @kotlin.OptIn(markerClass = {androidx.compose.foundation.ExperimentalFoundationApi.class})
    @androidx.compose.runtime.Composable()
    private static final void MessageBubble(com.nova.messenger.data.model.Message message, boolean isMe, boolean isEditable, kotlin.jvm.functions.Function1<? super com.nova.messenger.data.model.Message, kotlin.Unit> onLongPress, kotlin.jvm.functions.Function1<? super com.nova.messenger.data.model.Message, kotlin.Unit> onEditRequest) {
    }
    
    @androidx.compose.runtime.Composable()
    private static final void MessageComposer(java.lang.String text, kotlin.jvm.functions.Function1<? super java.lang.String, kotlin.Unit> onTextChange, kotlin.jvm.functions.Function0<kotlin.Unit> onSend) {
    }
}