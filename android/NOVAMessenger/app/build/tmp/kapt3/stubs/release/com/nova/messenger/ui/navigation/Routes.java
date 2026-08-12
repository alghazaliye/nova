package com.nova.messenger.ui.navigation;

@kotlin.Metadata(mv = {1, 9, 0}, k = 1, xi = 48, d1 = {"\u0000\u001c\n\u0002\u0018\u0002\n\u0002\u0010\u0000\n\u0002\b\u0002\n\u0002\u0010\u000e\n\u0002\b\u000e\n\u0002\u0010\t\n\u0002\b\u0007\b\u00c7\u0002\u0018\u00002\u00020\u0001B\u0007\b\u0002\u00a2\u0006\u0002\u0010\u0002J\u0016\u0010\u0011\u001a\u00020\u00042\u0006\u0010\u0012\u001a\u00020\u00132\u0006\u0010\u0014\u001a\u00020\u0004J\u000e\u0010\u0015\u001a\u00020\u00042\u0006\u0010\u0016\u001a\u00020\u0013J\u000e\u0010\u0017\u001a\u00020\u00042\u0006\u0010\u0018\u001a\u00020\u0004J\u000e\u0010\u0019\u001a\u00020\u00042\u0006\u0010\u0018\u001a\u00020\u0004R\u000e\u0010\u0003\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0005\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0006\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0007\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\b\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\t\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\n\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u000b\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\f\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\r\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u000e\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u000f\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000R\u000e\u0010\u0010\u001a\u00020\u0004X\u0086T\u00a2\u0006\u0002\n\u0000\u00a8\u0006\u001a"}, d2 = {"Lcom/nova/messenger/ui/navigation/Routes;", "", "()V", "CALL", "", "CALLS", "CHAT", "CONTACTS", "HOME", "LOGIN", "NOTIFICATIONS", "OTP", "PROFILE", "PROFILE_SETUP", "SETTINGS", "SPLASH", "STORIES", "call", "userId", "", "callType", "chat", "conversationId", "otp", "phone", "profileSetup", "app_release"})
public final class Routes {
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String SPLASH = "splash";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String LOGIN = "login";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String OTP = "otp/{phone}";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String PROFILE_SETUP = "profile_setup/{phone}";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String HOME = "home";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String CHAT = "chat/{conversationId}";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String CALL = "call/{userId}/{callType}";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String PROFILE = "profile";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String SETTINGS = "settings";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String CALLS = "calls";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String STORIES = "stories";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String CONTACTS = "contacts";
    @org.jetbrains.annotations.NotNull()
    public static final java.lang.String NOTIFICATIONS = "notifications";
    @org.jetbrains.annotations.NotNull()
    public static final com.nova.messenger.ui.navigation.Routes INSTANCE = null;
    
    private Routes() {
        super();
    }
    
    @org.jetbrains.annotations.NotNull()
    public final java.lang.String otp(@org.jetbrains.annotations.NotNull()
    java.lang.String phone) {
        return null;
    }
    
    @org.jetbrains.annotations.NotNull()
    public final java.lang.String profileSetup(@org.jetbrains.annotations.NotNull()
    java.lang.String phone) {
        return null;
    }
    
    @org.jetbrains.annotations.NotNull()
    public final java.lang.String chat(long conversationId) {
        return null;
    }
    
    @org.jetbrains.annotations.NotNull()
    public final java.lang.String call(long userId, @org.jetbrains.annotations.NotNull()
    java.lang.String callType) {
        return null;
    }
}