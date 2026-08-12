package com.nova.messenger.ui.call;

@kotlin.Metadata(mv = {1, 9, 0}, k = 1, xi = 48, d1 = {"\u0000J\n\u0002\u0018\u0002\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\u0018\u0002\n\u0000\n\u0002\u0010\t\n\u0002\b\u0003\n\u0002\u0018\u0002\n\u0002\b\u0003\n\u0002\u0010\u0002\n\u0002\b\u0003\n\u0002\u0010\u000e\n\u0002\b\u0007\n\u0002\u0018\u0002\n\u0002\b\u0002\b\u0007\u0018\u00002\u00020\u0001B\u0017\b\u0007\u0012\u0006\u0010\u0002\u001a\u00020\u0003\u0012\u0006\u0010\u0004\u001a\u00020\u0005\u00a2\u0006\u0002\u0010\u0006J\u0006\u0010\u0012\u001a\u00020\u0013J\u0016\u0010\u0014\u001a\u00020\u00132\u0006\u0010\u0015\u001a\u00020\u000b2\u0006\u0010\u0016\u001a\u00020\u0017J\u000e\u0010\u0018\u001a\u00020\u00132\u0006\u0010\u0019\u001a\u00020\u000bJ\u000e\u0010\u001a\u001a\u00020\u00132\u0006\u0010\u0019\u001a\u00020\u000bJ\b\u0010\u001b\u001a\u00020\u0013H\u0002J\b\u0010\u001c\u001a\u00020\u0013H\u0002J\u000e\u0010\u001d\u001a\u00020\u00132\u0006\u0010\u001e\u001a\u00020\u001fJ\u000e\u0010 \u001a\u00020\u00132\u0006\u0010\u001e\u001a\u00020\u001fR\u0014\u0010\u0007\u001a\b\u0012\u0004\u0012\u00020\t0\bX\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0002\u001a\u00020\u0003X\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u000e\u0010\n\u001a\u00020\u000bX\u0082\u000e\u00a2\u0006\u0002\n\u0000R\u000e\u0010\f\u001a\u00020\u000bX\u0082\u000e\u00a2\u0006\u0002\n\u0000R\u000e\u0010\r\u001a\u00020\u000bX\u0082\u000e\u00a2\u0006\u0002\n\u0000R\u0017\u0010\u000e\u001a\b\u0012\u0004\u0012\u00020\t0\u000f\u00a2\u0006\b\n\u0000\u001a\u0004\b\u0010\u0010\u0011R\u000e\u0010\u0004\u001a\u00020\u0005X\u0082\u0004\u00a2\u0006\u0002\n\u0000\u00a8\u0006!"}, d2 = {"Lcom/nova/messenger/ui/call/CallViewModel;", "Landroidx/lifecycle/ViewModel;", "apiClient", "Lcom/nova/messenger/data/api/ApiClient;", "tokenManager", "Lcom/nova/messenger/utils/TokenManager;", "(Lcom/nova/messenger/data/api/ApiClient;Lcom/nova/messenger/utils/TokenManager;)V", "_state", "Lkotlinx/coroutines/flow/MutableStateFlow;", "Lcom/nova/messenger/ui/call/CallViewModelState;", "callStartTime", "", "currentCallId", "myUserId", "state", "Lkotlinx/coroutines/flow/StateFlow;", "getState", "()Lkotlinx/coroutines/flow/StateFlow;", "endCall", "", "initiateCall", "targetUserId", "callType", "", "joinIncomingCall", "callId", "rejectIncomingCall", "startPolling", "startTimer", "toggleMute", "context", "Landroid/content/Context;", "toggleSpeaker", "app_release"})
@dagger.hilt.android.lifecycle.HiltViewModel()
public final class CallViewModel extends androidx.lifecycle.ViewModel {
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.data.api.ApiClient apiClient = null;
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.utils.TokenManager tokenManager = null;
    @org.jetbrains.annotations.NotNull()
    private final kotlinx.coroutines.flow.MutableStateFlow<com.nova.messenger.ui.call.CallViewModelState> _state = null;
    @org.jetbrains.annotations.NotNull()
    private final kotlinx.coroutines.flow.StateFlow<com.nova.messenger.ui.call.CallViewModelState> state = null;
    private long currentCallId = 0L;
    private long callStartTime = 0L;
    private long myUserId = 0L;
    
    @javax.inject.Inject()
    public CallViewModel(@org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.api.ApiClient apiClient, @org.jetbrains.annotations.NotNull()
    com.nova.messenger.utils.TokenManager tokenManager) {
        super();
    }
    
    @org.jetbrains.annotations.NotNull()
    public final kotlinx.coroutines.flow.StateFlow<com.nova.messenger.ui.call.CallViewModelState> getState() {
        return null;
    }
    
    public final void initiateCall(long targetUserId, @org.jetbrains.annotations.NotNull()
    java.lang.String callType) {
    }
    
    public final void joinIncomingCall(long callId) {
    }
    
    public final void rejectIncomingCall(long callId) {
    }
    
    public final void endCall() {
    }
    
    public final void toggleMute(@org.jetbrains.annotations.NotNull()
    android.content.Context context) {
    }
    
    public final void toggleSpeaker(@org.jetbrains.annotations.NotNull()
    android.content.Context context) {
    }
    
    private final void startPolling() {
    }
    
    private final void startTimer() {
    }
}