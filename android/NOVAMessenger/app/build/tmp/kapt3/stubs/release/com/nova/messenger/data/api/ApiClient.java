package com.nova.messenger.data.api;

/**
 * NOVA Messenger - API Client
 * Single Retrofit instance with Auth interceptor.
 */
@javax.inject.Singleton()
@kotlin.Metadata(mv = {1, 9, 0}, k = 1, xi = 48, d1 = {"\u0000,\n\u0002\u0018\u0002\n\u0002\u0010\u0000\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0003\b\u0007\u0018\u00002\u00020\u0001B\u000f\b\u0007\u0012\u0006\u0010\u0002\u001a\u00020\u0003\u00a2\u0006\u0002\u0010\u0004R\u000e\u0010\u0005\u001a\u00020\u0006X\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0007\u001a\u00020\bX\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u000e\u0010\t\u001a\u00020\nX\u0082\u0004\u00a2\u0006\u0002\n\u0000R\u0011\u0010\u000b\u001a\u00020\f\u00a2\u0006\b\n\u0000\u001a\u0004\b\r\u0010\u000eR\u000e\u0010\u0002\u001a\u00020\u0003X\u0082\u0004\u00a2\u0006\u0002\n\u0000\u00a8\u0006\u000f"}, d2 = {"Lcom/nova/messenger/data/api/ApiClient;", "", "tokenManager", "Lcom/nova/messenger/utils/TokenManager;", "(Lcom/nova/messenger/utils/TokenManager;)V", "authInterceptor", "Lokhttp3/Interceptor;", "loggingInterceptor", "Lokhttp3/logging/HttpLoggingInterceptor;", "okHttpClient", "Lokhttp3/OkHttpClient;", "service", "Lcom/nova/messenger/data/api/ApiService;", "getService", "()Lcom/nova/messenger/data/api/ApiService;", "app_release"})
public final class ApiClient {
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.utils.TokenManager tokenManager = null;
    @org.jetbrains.annotations.NotNull()
    private final okhttp3.Interceptor authInterceptor = null;
    @org.jetbrains.annotations.NotNull()
    private final okhttp3.logging.HttpLoggingInterceptor loggingInterceptor = null;
    @org.jetbrains.annotations.NotNull()
    private final okhttp3.OkHttpClient okHttpClient = null;
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.data.api.ApiService service = null;
    
    @javax.inject.Inject()
    public ApiClient(@org.jetbrains.annotations.NotNull()
    com.nova.messenger.utils.TokenManager tokenManager) {
        super();
    }
    
    @org.jetbrains.annotations.NotNull()
    public final com.nova.messenger.data.api.ApiService getService() {
        return null;
    }
}