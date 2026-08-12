package com.nova.messenger.data.repository;

@javax.inject.Singleton()
@kotlin.Metadata(mv = {1, 9, 0}, k = 1, xi = 48, d1 = {"\u0000L\n\u0002\u0018\u0002\n\u0002\u0010\u0000\n\u0000\n\u0002\u0018\u0002\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\u0010\u0002\n\u0000\n\u0002\u0010\t\n\u0000\n\u0002\u0010\u000b\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0000\n\u0002\u0010\u000e\n\u0002\b\u0002\n\u0002\u0018\u0002\n\u0002\b\u0003\n\u0002\u0010 \n\u0002\u0018\u0002\n\u0002\b\u000b\b\u0007\u0018\u00002\u00020\u0001:\u0001\"B\u000f\b\u0007\u0012\u0006\u0010\u0002\u001a\u00020\u0003\u00a2\u0006\u0002\u0010\u0004J&\u0010\u0005\u001a\b\u0012\u0004\u0012\u00020\u00070\u00062\u0006\u0010\b\u001a\u00020\t2\b\b\u0002\u0010\n\u001a\u00020\u000bH\u0086@\u00a2\u0006\u0002\u0010\fJ$\u0010\r\u001a\b\u0012\u0004\u0012\u00020\u000e0\u00062\u0006\u0010\b\u001a\u00020\t2\u0006\u0010\u000f\u001a\u00020\u0010H\u0086@\u00a2\u0006\u0002\u0010\u0011J\u001c\u0010\u0012\u001a\b\u0012\u0004\u0012\u00020\u00130\u00062\u0006\u0010\u0014\u001a\u00020\tH\u0086@\u00a2\u0006\u0002\u0010\u0015J\u001a\u0010\u0016\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u00180\u00170\u0006H\u0086@\u00a2\u0006\u0002\u0010\u0019J.\u0010\u001a\u001a\u000e\u0012\n\u0012\b\u0012\u0004\u0012\u00020\u000e0\u00170\u00062\u0006\u0010\u0014\u001a\u00020\t2\n\b\u0002\u0010\u001b\u001a\u0004\u0018\u00010\tH\u0086@\u00a2\u0006\u0002\u0010\u001cJ\u001c\u0010\u001d\u001a\b\u0012\u0004\u0012\u00020\u00070\u00062\u0006\u0010\b\u001a\u00020\tH\u0086@\u00a2\u0006\u0002\u0010\u0015J0\u0010\u001e\u001a\b\u0012\u0004\u0012\u00020\u000e0\u00062\u0006\u0010\u0014\u001a\u00020\t2\u0006\u0010\u001f\u001a\u00020\u00102\n\b\u0002\u0010 \u001a\u0004\u0018\u00010\tH\u0086@\u00a2\u0006\u0002\u0010!R\u000e\u0010\u0002\u001a\u00020\u0003X\u0082\u0004\u00a2\u0006\u0002\n\u0000\u00a8\u0006#"}, d2 = {"Lcom/nova/messenger/data/repository/MessageRepository;", "", "apiClient", "Lcom/nova/messenger/data/api/ApiClient;", "(Lcom/nova/messenger/data/api/ApiClient;)V", "deleteMessage", "Lcom/nova/messenger/data/repository/Result;", "", "messageId", "", "forAll", "", "(JZLkotlin/coroutines/Continuation;)Ljava/lang/Object;", "editMessage", "Lcom/nova/messenger/data/model/Message;", "body", "", "(JLjava/lang/String;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "getConversationInfo", "Lcom/nova/messenger/data/repository/MessageRepository$ConversationInfo;", "conversationId", "(JLkotlin/coroutines/Continuation;)Ljava/lang/Object;", "getConversations", "", "Lcom/nova/messenger/data/model/Conversation;", "(Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "getMessages", "beforeId", "(JLjava/lang/Long;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "markMessageRead", "sendMessage", "text", "replyToId", "(JLjava/lang/String;Ljava/lang/Long;Lkotlin/coroutines/Continuation;)Ljava/lang/Object;", "ConversationInfo", "app_release"})
public final class MessageRepository {
    @org.jetbrains.annotations.NotNull()
    private final com.nova.messenger.data.api.ApiClient apiClient = null;
    
    @javax.inject.Inject()
    public MessageRepository(@org.jetbrains.annotations.NotNull()
    com.nova.messenger.data.api.ApiClient apiClient) {
        super();
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object getConversationInfo(long conversationId, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<com.nova.messenger.data.repository.MessageRepository.ConversationInfo>> $completion) {
        return null;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object getConversations(@org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<? extends java.util.List<com.nova.messenger.data.model.Conversation>>> $completion) {
        return null;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object getMessages(long conversationId, @org.jetbrains.annotations.Nullable()
    java.lang.Long beforeId, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<? extends java.util.List<com.nova.messenger.data.model.Message>>> $completion) {
        return null;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object sendMessage(long conversationId, @org.jetbrains.annotations.NotNull()
    java.lang.String text, @org.jetbrains.annotations.Nullable()
    java.lang.Long replyToId, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<com.nova.messenger.data.model.Message>> $completion) {
        return null;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object markMessageRead(long messageId, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<kotlin.Unit>> $completion) {
        return null;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object deleteMessage(long messageId, boolean forAll, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<kotlin.Unit>> $completion) {
        return null;
    }
    
    @org.jetbrains.annotations.Nullable()
    public final java.lang.Object editMessage(long messageId, @org.jetbrains.annotations.NotNull()
    java.lang.String body, @org.jetbrains.annotations.NotNull()
    kotlin.coroutines.Continuation<? super com.nova.messenger.data.repository.Result<com.nova.messenger.data.model.Message>> $completion) {
        return null;
    }
    
    @kotlin.Metadata(mv = {1, 9, 0}, k = 1, xi = 48, d1 = {"\u0000 \n\u0002\u0018\u0002\n\u0002\u0010\u0000\n\u0000\n\u0002\u0010\u000e\n\u0000\n\u0002\u0010\u000b\n\u0002\b\n\n\u0002\u0010\b\n\u0002\b\u0002\b\u0087\b\u0018\u00002\u00020\u0001B\u0015\u0012\u0006\u0010\u0002\u001a\u00020\u0003\u0012\u0006\u0010\u0004\u001a\u00020\u0005\u00a2\u0006\u0002\u0010\u0006J\t\u0010\n\u001a\u00020\u0003H\u00c6\u0003J\t\u0010\u000b\u001a\u00020\u0005H\u00c6\u0003J\u001d\u0010\f\u001a\u00020\u00002\b\b\u0002\u0010\u0002\u001a\u00020\u00032\b\b\u0002\u0010\u0004\u001a\u00020\u0005H\u00c6\u0001J\u0013\u0010\r\u001a\u00020\u00052\b\u0010\u000e\u001a\u0004\u0018\u00010\u0001H\u00d6\u0003J\t\u0010\u000f\u001a\u00020\u0010H\u00d6\u0001J\t\u0010\u0011\u001a\u00020\u0003H\u00d6\u0001R\u0011\u0010\u0004\u001a\u00020\u0005\u00a2\u0006\b\n\u0000\u001a\u0004\b\u0004\u0010\u0007R\u0011\u0010\u0002\u001a\u00020\u0003\u00a2\u0006\b\n\u0000\u001a\u0004\b\b\u0010\t\u00a8\u0006\u0012"}, d2 = {"Lcom/nova/messenger/data/repository/MessageRepository$ConversationInfo;", "", "title", "", "isVerified", "", "(Ljava/lang/String;Z)V", "()Z", "getTitle", "()Ljava/lang/String;", "component1", "component2", "copy", "equals", "other", "hashCode", "", "toString", "app_release"})
    public static final class ConversationInfo {
        @org.jetbrains.annotations.NotNull()
        private final java.lang.String title = null;
        private final boolean isVerified = false;
        
        public ConversationInfo(@org.jetbrains.annotations.NotNull()
        java.lang.String title, boolean isVerified) {
            super();
        }
        
        @org.jetbrains.annotations.NotNull()
        public final java.lang.String getTitle() {
            return null;
        }
        
        public final boolean isVerified() {
            return false;
        }
        
        @org.jetbrains.annotations.NotNull()
        public final java.lang.String component1() {
            return null;
        }
        
        public final boolean component2() {
            return false;
        }
        
        @org.jetbrains.annotations.NotNull()
        public final com.nova.messenger.data.repository.MessageRepository.ConversationInfo copy(@org.jetbrains.annotations.NotNull()
        java.lang.String title, boolean isVerified) {
            return null;
        }
        
        @java.lang.Override()
        public boolean equals(@org.jetbrains.annotations.Nullable()
        java.lang.Object other) {
            return false;
        }
        
        @java.lang.Override()
        public int hashCode() {
            return 0;
        }
        
        @java.lang.Override()
        @org.jetbrains.annotations.NotNull()
        public java.lang.String toString() {
            return null;
        }
    }
}