package com.nova.messenger.data.repository;

@javax.inject.Singleton()
@kotlin.Metadata(mv = {1, 9, 0}, k = 1, xi = 48, d1 = {"\u0000B\n\u0002\u0018\u0002\n\u0002\u0010\u0000\n\u0000\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0010\u000b\n\u0000\n\u0002\u0018\u0002\n\u0002\u0010\u0002\n\u0002\b\u0003\n\u0002\u0010\u000e\n\u0002\b\u0003\n\u0002\u0018\u0002\n\u0002\b\u0003\n\u0002\u0018\u0002\n\u0002\b\u0005\b\u0007\u0018\u00002\u00020\u0001B\u0017\b\u0007\u0012\u0006\u0010\u0002\u001a\u00020\u0003\u0012\u0006\u0010\u0004\u001a\u00020\u0005\u00a2\u0006\u0002\u0010\u0006J\u0006\u0010\u0007\u001a\u00020\bJ\u0014\u0010\t\u001a\b\u0012\u0004\u0012\u00020\u000b0\nH\u0086@\u00a2\u0006\u0002\u0010\fJ(\u0010\r\u001a\b\u0012\u0004\u0012\u00020\u000b0\n2\u0006\u0010\u000e\u001a\u00020\u000f2\n\b\u0002\u0010\u0010\u001a\u0004\u0018\u00010\u000fH\u0086@\u00a2\u0006\u0002\u0010\u0011J&\u0010\u0012\u001a\b\u0012\u0004\u0012\u00020\u00130\n2\u0006\u0010\u0014\u001a\u00020\u000f2\b\u0010\u0015\u001a\u0004\u0018\u00010\u000fH\u0086@\u00a2\u0006\u0002\u0010\u0011JB\u0010\u0016\u001a\b\u0012\u0004\u0012\u00020\u00170\n2\u0006\u0010\u000e\u001a\u00020\u000f2\u0006\u0010\u0018\u001a\u00020\u000f2\u0006\u0010\u0019\u001a\u00020\u000f2\b\u0010\u001a\u001a\u0004\u0018\u00010\u000f2\n\b\u0002\u0010\u0014\u001a\u0004\u0018\u00010\u000fH\u0086@\u00a2\u0006\u0002\u0010\u001bR\u000e\u0010\u0002\u001a\u00020\u0003X\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0004\u001a\u00020\u0005X\u0082\u0004\u00a2\u0006\u0002\n\u0000\u00a8\u0006\u001c"}, d2 = {"Lcom/nova/messenger/data/repository/AuthRepository;", "", "apiClient", "Lcom/nova/messenger/data/api/ApiClient;", "tokenManager", "Lcom/nova/messenger/utils/TokenManager;", "(Lcom/nova/messenger/data/api/ApiClient;Lcom/nova/messenger/utils/TokenManager;)V", "isLoggedIn", "", "logout", "Lcom/nova/messenger/data/repository/Result;", "", "(Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "requestOtp", "phone", "", "countryCode", "(Ljava/lang/String;Ljava/lang/String;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "updateProfile", "Lcom/nova/messenger/data/model/User;", "name", "email", "verifyOtp", "Lcom/nova/messenger/data/model/AuthResponse;", "otp", "deviceUuid", "fcmToken", "(Ljava/lang/String;Ljava/lang/String;Ljava/lang/String;Ljava/lang/String;Ljava/lang/String;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "app_release"})
public final class AuthRepository {
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.data.api.ApiClient apiClient = null;
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.utils.TokenManager tokenManager = null;
    
    @javax.inject.Inject()
    public AuthRepository(@org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.api.ApiClient apiClient, @org.jetbrains.annotations.NotNull()
    com.nova.messenger.utils.TokenManager tokenManager) {
        super();
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object requestOtp(@org.jetbrains.annotations.NotNull()
    java.lang.String phone, @org.jetbrains.annotations.Nullable()
    java.lang.String countryCode, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<kotlin.Unit>> $completion) {
        return null;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object verifyOtp(@org.jetbrains.annotations.NotNull()
    java.lang.String phone, @org.jetbrains.annotations.NotNull()
    java.lang.String otp, @org.jetbrains.annotations.NotNull()
    java.lang.String deviceUuid, @org.jetbrains.annotations.Nullable()
    java.lang.String fcmToken, @org.jetbrains.annotations.Nullable()
    java.lang.String name, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<com.nova.messenger.data.model.AuthResponse>> $completion) {
        return null;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object logout(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<kotlin.Unit>> $completion) {
        return null;
    }
    
    public final boolean isLoggedIn() {
        return false;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object updateProfile(@org.jetbrains.annotations.NotNull()
    java.lang.String name, @org.jetbrains.annotations.Nullable()
    java.lang.String email, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<com.nova.messenger.data.model.User>> $completion) {
        return null;
    }
}