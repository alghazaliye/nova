package com.nova.messenger.data.api;

/**
 * NOVA Messenger - Retrofit API Service
 * All endpoints match the PHP Backend REST API.
 */
@kotlin.Metadata(mv = {1, 9, 0}, k = 1, xi = 48, d1 = {"\u0000\u00be\u0001\n\u0002\u0018\u0002\n\u0002\u0010\u0000\n\u0000\n\u0002\u0018\u0002\n\u0002\u0018\u0002\n\u0002\u0010\u0002\n\u0000\n\u0002\u0010\t\n\u0002\b\u0003\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\u0018\u0002\n\u0002\b\u0003\n\u0002\u0010$\n\u0002\u0010\u000e\n\u0002\u0010\u000b\n\u0002\b\u0004\n\u0002\u0010 \n\u0002\u0018\u0002\n\u0002\b\u0004\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0003\n\u0002\u0010\b\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\b\u0006\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\b\t\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0006\n\u0002\u0018\u0002\n\u0002\b\u0005\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\u0018\u0002\n\u0002\b\u0003\bf\u0018\u00002\u00020\u0001J$\u0010\u0002\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ$\u0010\t\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ$\u0010\n\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u000b0\u00040\u00032\b\b\u0001\u0010\f\u001a\u00020\rH\u00a7@\u00a2\u0006\u0002\u0010\u000eJ$\u0010\u000f\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00100\u00040\u00032\b\b\u0001\u0010\f\u001a\u00020\u0011H\u00a7@\u00a2\u0006\u0002\u0010\u0012J$\u0010\u0013\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ:\u0010\u0014\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u00072\u0014\b\u0003\u0010\f\u001a\u000e\u0012\u0004\u0012\u00020\u0016\u0012\u0004\u0012\u00020\u00170\u0015H\u00a7@\u00a2\u0006\u0002\u0010\u0018J$\u0010\u0019\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ$\u0010\u001a\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ \u0010\u001b\u001a\u0014\u0012\u0010\u0012\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u001d0\u001c0\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJ$\u0010\u001f\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u000b0\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ \u0010 \u001a\u0014\u0012\u0010\u0012\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u000b0\u001c0\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJ\u001a\u0010!\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\"0\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJ@\u0010#\u001a\u0014\u0012\u0010\u0012\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020$0\u001c0\u00040\u00032\b\b\u0001\u0010%\u001a\u00020\u00072\n\b\u0003\u0010&\u001a\u0004\u0018\u00010\u00072\b\b\u0003\u0010\'\u001a\u00020(H\u00a7@\u00a2\u0006\u0002\u0010)J\u001a\u0010*\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020+0\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJB\u0010,\u001a \u0012\u001c\u0012\u001a\u0012\u0016\u0012\u0014\u0012\u0010\u0012\u000e\u0012\u0004\u0012\u00020\u0016\u0012\u0004\u0012\u00020\u00010\u00150\u001c0\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u00072\n\b\u0003\u0010-\u001a\u0004\u0018\u00010\u0016H\u00a7@\u00a2\u0006\u0002\u0010.J \u0010/\u001a\u0014\u0012\u0010\u0012\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00100\u001c0\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJ$\u00100\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\"0\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ$\u00101\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u001d0\u00040\u00032\b\b\u0001\u0010\f\u001a\u000202H\u00a7@\u00a2\u0006\u0002\u00103J$\u00104\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\f\u001a\u000205H\u00a7@\u00a2\u0006\u0002\u00106J\u001a\u00107\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJ\u001a\u00108\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJ$\u00109\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ$\u0010:\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ\u001a\u0010;\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\"0\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJ$\u0010<\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ$\u0010=\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ.\u0010>\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u00072\b\b\u0001\u0010\f\u001a\u00020?H\u00a7@\u00a2\u0006\u0002\u0010@J\u001a\u0010A\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020B0\u00040\u0003H\u00a7@\u00a2\u0006\u0002\u0010\u001eJ$\u0010C\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\f\u001a\u00020DH\u00a7@\u00a2\u0006\u0002\u0010EJ$\u0010F\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ*\u0010G\u001a\u0014\u0012\u0010\u0012\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\"0\u001c0\u00040\u00032\b\b\u0001\u0010H\u001a\u00020\u0016H\u00a7@\u00a2\u0006\u0002\u0010IJ.\u0010J\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020$0\u00040\u00032\b\b\u0001\u0010%\u001a\u00020\u00072\b\b\u0001\u0010\f\u001a\u00020KH\u00a7@\u00a2\u0006\u0002\u0010LJ:\u0010M\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u00072\u0014\b\u0001\u0010\f\u001a\u000e\u0012\u0004\u0012\u00020\u0016\u0012\u0004\u0012\u00020\u00010\u0015H\u00a7@\u00a2\u0006\u0002\u0010\u0018J$\u0010N\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u001d0\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ$\u0010O\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\bJ$\u0010P\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\"0\u00040\u00032\b\b\u0001\u0010\f\u001a\u00020QH\u00a7@\u00a2\u0006\u0002\u0010RJ.\u0010S\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020$0\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u00072\b\b\u0001\u0010\f\u001a\u00020TH\u00a7@\u00a2\u0006\u0002\u0010UJ$\u0010V\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020W0\u00040\u00032\b\b\u0001\u0010\f\u001a\u00020XH\u00a7@\u00a2\u0006\u0002\u0010YJ$\u0010Z\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00050\u00040\u00032\b\b\u0001\u0010\u0006\u001a\u00020\u0007H\u00a7@\u00a2\u0006\u0002\u0010\b\u00a8\u0006["}, d2 = {"Lcom/nova/messenger/data/api/ApiService;", "", "answerCall", "Lretrofit2/Response;", "Lcom/nova/messenger/data/model/ApiResponse;", "", "id", "", "(JLkotlin/coroutines/Continuation;)Ljava/lang/Object;", "blockUser", "createConversation", "Lcom/nova/messenger/data/model/Conversation;", "body", "Lcom/nova/messenger/data/model/CreateConversationRequest;", "(Lcom/nova/messenger/data/model/CreateConversationRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "createStory", "Lcom/nova/messenger/data/model/Story;", "Lcom/nova/messenger/data/model/CreateStoryRequest;", "(Lcom/nova/messenger/data/model/CreateStoryRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "deleteConversation", "deleteMessage", "", "", "", "(JLjava/util/Map;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "deleteStory", "endCall", "getCalls", "", "Lcom/nova/messenger/data/model/Call;", "(Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "getConversation", "getConversations", "getMe", "Lcom/nova/messenger/data/model/User;", "getMessages", "Lcom/nova/messenger/data/model/Message;", "conversationId", "beforeId", "limit", "", "(JLjava/lang/Long;ILkotlin/coroutines/Continuation;)Ljava/lang/Object;", "getNotifications", "Lcom/nova/messenger/data/model/NotificationsResponse;", "getSignals", "since", "(JLjava/lang/String;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "getStories", "getUser", "initiateCall", "Lcom/nova/messenger/data/model/InitiateCallRequest;", "(Lcom/nova/messenger/data/model/InitiateCallRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "login", "Lcom/nova/messenger/data/model/LoginRequest;", "(Lcom/nova/messenger/data/model/LoginRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "logout", "markAllNotificationsRead", "markMessageRead", "markNotificationRead", "me", "muteConversation", "pinConversation", "reactToMessage", "Lcom/nova/messenger/data/model/ReactionRequest;", "(JLcom/nova/messenger/data/model/ReactionRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "refresh", "Lcom/nova/messenger/data/model/TokenResponse;", "register", "Lcom/nova/messenger/data/model/RegisterRequest;", "(Lcom/nova/messenger/data/model/RegisterRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "rejectCall", "searchUsers", "query", "(Ljava/lang/String;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "sendMessage", "Lcom/nova/messenger/data/model/SendMessageRequest;", "(JLcom/nova/messenger/data/model/SendMessageRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "sendSignal", "showCall", "unblockUser", "updateMe", "Lcom/nova/messenger/data/model/UpdateProfileRequest;", "(Lcom/nova/messenger/data/model/UpdateProfileRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "updateMessage", "Lcom/nova/messenger/data/model/UpdateMessageRequest;", "(JLcom/nova/messenger/data/model/UpdateMessageRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "verifyOtp", "Lcom/nova/messenger/data/model/AuthResponse;", "Lcom/nova/messenger/data/model/VerifyOtpRequest;", "(Lcom/nova/messenger/data/model/VerifyOtpRequest;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "viewStory", "app_release"})
public abstract interface ApiService {
    
    @retrofit2.http.POST(value = "auth/register")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object register(@retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.RegisterRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "auth/login")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object login(@retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.LoginRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "auth/verify-otp")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object verifyOtp(@retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.VerifyOtpRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.AuthResponse>>> $completion);
    
    @retrofit2.http.POST(value = "auth/logout")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object logout(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.GET(value = "auth/me")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object me(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.User>>> $completion);
    
    @retrofit2.http.POST(value = "auth/refresh")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object refresh(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.TokenResponse>>> $completion);
    
    @retrofit2.http.GET(value = "users/me")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getMe(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.User>>> $completion);
    
    @retrofit2.http.PUT(value = "users/me")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object updateMe(@retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.UpdateProfileRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.User>>> $completion);
    
    @retrofit2.http.GET(value = "users/search")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object searchUsers(@retrofit2.http.Query(value = "q")
    @org.jetbrains.annotations.NotNull()
    java.lang.String query, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<java.util.List<com.nova.messenger.data.model.User>>>> $completion);
    
    @retrofit2.http.GET(value = "users/{id}")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getUser(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.User>>> $completion);
    
    @retrofit2.http.POST(value = "users/{id}/block")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object blockUser(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.DELETE(value = "users/{id}/block")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object unblockUser(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.GET(value = "conversations")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getConversations(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<java.util.List<com.nova.messenger.data.model.Conversation>>>> $completion);
    
    @retrofit2.http.POST(value = "conversations")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object createConversation(@retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.CreateConversationRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.Conversation>>> $completion);
    
    @retrofit2.http.GET(value = "conversations/{id}")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getConversation(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.Conversation>>> $completion);
    
    @retrofit2.http.DELETE(value = "conversations/{id}")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object deleteConversation(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "conversations/{id}/mute")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object muteConversation(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "conversations/{id}/pin")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object pinConversation(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.GET(value = "conversations/{id}/messages")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getMessages(@retrofit2.http.Path(value = "id")
    long conversationId, @retrofit2.http.Query(value = "before_id")
    @org.jetbrains.annotations.Nullable()
    java.lang.Long beforeId, @retrofit2.http.Query(value = "limit")
    int limit, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<java.util.List<com.nova.messenger.data.model.Message>>>> $completion);
    
    @retrofit2.http.POST(value = "conversations/{id}/messages")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object sendMessage(@retrofit2.http.Path(value = "id")
    long conversationId, @retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.SendMessageRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.Message>>> $completion);
    
    @retrofit2.http.PUT(value = "messages/{id}")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object updateMessage(@retrofit2.http.Path(value = "id")
    long id, @retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.UpdateMessageRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.Message>>> $completion);
    
    @retrofit2.http.DELETE(value = "messages/{id}")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object deleteMessage(@retrofit2.http.Path(value = "id")
    long id, @retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    java.util.Map<java.lang.String, java.lang.Boolean> body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "messages/{id}/read")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object markMessageRead(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "messages/{id}/reaction")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object reactToMessage(@retrofit2.http.Path(value = "id")
    long id, @retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.ReactionRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.GET(value = "stories")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getStories(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<java.util.List<com.nova.messenger.data.model.Story>>>> $completion);
    
    @retrofit2.http.POST(value = "stories")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object createStory(@retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.CreateStoryRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.Story>>> $completion);
    
    @retrofit2.http.POST(value = "stories/{id}/view")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object viewStory(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.DELETE(value = "stories/{id}")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object deleteStory(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "calls")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object initiateCall(@retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.model.InitiateCallRequest body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.Call>>> $completion);
    
    @retrofit2.http.GET(value = "calls")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getCalls(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<java.util.List<com.nova.messenger.data.model.Call>>>> $completion);
    
    @retrofit2.http.GET(value = "calls/{id}")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object showCall(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.Call>>> $completion);
    
    @retrofit2.http.POST(value = "calls/{id}/answer")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object answerCall(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "calls/{id}/reject")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object rejectCall(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "calls/{id}/end")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object endCall(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "calls/{id}/signal")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object sendSignal(@retrofit2.http.Path(value = "id")
    long id, @retrofit2.http.Body()
    @org.jetbrains.annotations.NotNull()
    java.util.Map<java.lang.String, ? extends java.lang.Object> body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.GET(value = "calls/{id}/signals")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getSignals(@retrofit2.http.Path(value = "id")
    long id, @retrofit2.http.Query(value = "since")
    @org.jetbrains.annotations.Nullable()
    java.lang.String since, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<java.util.List<java.util.Map<java.lang.String, java.lang.Object>>>>> $completion);
    
    @retrofit2.http.GET(value = "notifications")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object getNotifications(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<com.nova.messenger.data.model.NotificationsResponse>>> $completion);
    
    @retrofit2.http.POST(value = "notifications/{id}/read")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object markNotificationRead(@retrofit2.http.Path(value = "id")
    long id, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    @retrofit2.http.POST(value = "notifications/read-all")
    @org.jetbrains.annotations.Nullable()
    public abstract java.lang.Object markAllNotificationsRead(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super retrofit2.Response<com.nova.messenger.data.model.ApiResponse<kotlin.Unit>>> $completion);
    
    /**
     * NOVA Messenger - Retrofit API Service
     * All endpoints match the PHP Backend REST API.
     */
    @kotlin.Metadata(mv = {1, 9, 0}, k = 3, xi = 48)
    public static final class DefaultImpls {
    }
}