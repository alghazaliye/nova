package com.nova.messenger.ui.chat

import android.widget.Toast
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.ui.window.Dialog
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.nova.messenger.data.model.Message
import com.nova.messenger.utils.TokenManager
import java.text.SimpleDateFormat
import java.util.Locale
import javax.inject.Inject

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun ChatScreen(
    conversationId: Long,
    onNavigateBack: () -> Unit,
    onNavigateToCall: (callType: String) -> Unit,
    viewModel: ChatViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var messageText by remember { mutableStateOf("") }
    val listState = rememberLazyListState()
    val context = LocalContext.current

    // Message edit dialog
    var editingMessage by remember { mutableStateOf<Message?>(null) }
    var editDraft by remember { mutableStateOf("") }
    var editDialogOpen by remember { mutableStateOf(false) }
    var showDeleteOptions by remember { mutableStateOf<Message?>(null) }

    LaunchedEffect(conversationId) {
        viewModel.loadMessages(conversationId)
    }

    // Scroll to bottom when new messages arrive
    LaunchedEffect((uiState as? ChatUiState.Success)?.messages?.size) {
        val messages = (uiState as? ChatUiState.Success)?.messages
        if (!messages.isNullOrEmpty()) {
            listState.animateScrollToItem(messages.size - 1)
        }
    }

    if (editDialogOpen) {
        AlertDialog(
            onDismissRequest = { editDialogOpen = false },
            title = { Text("تعديل الرسالة") },
            text = {
                OutlinedTextField(
                    value = editDraft,
                    onValueChange = { editDraft = it },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true
                )
            },
            confirmButton = {
                TextButton(
                    enabled = editDraft.isNotBlank(),
                    onClick = {
                        editingMessage?.let { viewModel.editMessage(it.id, editDraft.trim()) }
                        editDialogOpen = false
                        editingMessage = null
                    }
                ) { Text("حفظ") }
            },
            dismissButton = {
                TextButton(onClick = { editDialogOpen = false }) { Text("إلغاء") }
            }
        )
    }

    showDeleteOptions?.let { target ->
        Dialog(onDismissRequest = { showDeleteOptions = null }) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(24.dp))
                    .background(MaterialTheme.colorScheme.surface)
                    .padding(20.dp)
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("حذف الرسالة", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                    Text("كيف تريد حذف هذه الرسالة؟", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    TextButton(onClick = {
                        viewModel.deleteMessage(target.id, forAll = false)
                        Toast.makeText(context, "تم حذف الرسالة لديك", Toast.LENGTH_SHORT).show()
                        showDeleteOptions = null
                    }) { Text("حذف لديّ") }
                    TextButton(
                        onClick = {
                            viewModel.deleteMessage(target.id, forAll = true)
                            Toast.makeText(context, "تم حذف الرسالة لدى الجميع", Toast.LENGTH_SHORT).show()
                            showDeleteOptions = null
                        }
                    ) { Text("حذف لدى الجميع", color = MaterialTheme.colorScheme.error) }
                    TextButton(onClick = { showDeleteOptions = null }) { Text("إلغاء") }
                }
            }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(RoundedCornerShape(12.dp))
                                .background(MaterialTheme.colorScheme.primaryContainer),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                (uiState as? ChatUiState.Success)?.conversationTitle?.firstOrNull()?.toString() ?: "?",
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.primary
                            )
                        }
                        Column {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    (uiState as? ChatUiState.Success)?.conversationTitle ?: "محادثة",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 16.sp
                                )
                                if ((uiState as? ChatUiState.Success)?.isVerified == true) {
                                    Icon(
                                        Icons.Default.Verified,
                                        contentDescription = "حساب موثق",
                                        tint = Color(0xFF2563EB),
                                        modifier = Modifier.size(18.dp).padding(start = 4.dp)
                                    )
                                }
                            }
                            Text("متصل الآن", fontSize = 12.sp, color = Color(0xFF22C55E))
                        }
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "رجوع")
                    }
                },
                actions = {
                    IconButton(onClick = { onNavigateToCall("voice") }) {
                        Icon(Icons.Default.Phone, contentDescription = "مكالمة صوتية")
                    }
                    IconButton(onClick = { onNavigateToCall("video") }) {
                        Icon(Icons.Default.Videocam, contentDescription = "مكالمة فيديو")
                    }
                    IconButton(onClick = { /* More options */ }) {
                        Icon(Icons.Default.MoreVert, contentDescription = "المزيد")
                    }
                }
            )
        },
        bottomBar = {
            MessageComposer(
                text = messageText,
                onTextChange = { messageText = it },
                onSend = {
                    if (messageText.isNotBlank()) {
                        viewModel.sendMessage(conversationId, messageText.trim())
                        messageText = ""
                    }
                }
            )
        }
    ) { paddingValues ->
        Box(modifier = Modifier.padding(paddingValues)) {
            when (val state = uiState) {
                is ChatUiState.Loading -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator()
                    }
                }
                is ChatUiState.Success -> {
                    LazyColumn(
                        state = listState,
                        contentPadding = PaddingValues(horizontal = 14.dp, vertical = 10.dp),
                        verticalArrangement = Arrangement.spacedBy(6.dp),
                        modifier = Modifier.fillMaxSize()
                    ) {
                        items(state.messages, key = { it.id }) { message ->
                            MessageBubble(
                                message = message,
                                isMe = message.senderId == state.myUserId,
                                isEditable = message.senderId == state.myUserId && message.deletedAt == null,
                                onLongPress = {
                                    showDeleteOptions = it
                                },
                                onEditRequest = {
                                    editingMessage = it
                                    editDraft = it.body ?: ""
                                    editDialogOpen = true
                                }
                            )
                        }
                    }
                }
                is ChatUiState.Error -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(state.message, color = MaterialTheme.colorScheme.error)
                    }
                }
                else -> {}
            }
        }
    }
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
private fun MessageBubble(
    message: Message,
    isMe: Boolean,
    isEditable: Boolean,
    onLongPress: (Message) -> Unit,
    onEditRequest: (Message) -> Unit
) {
    val bubbleColor = if (isMe) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.surfaceVariant
    val textColor   = if (isMe) Color.White else MaterialTheme.colorScheme.onSurface
    val alignment   = if (isMe) Alignment.End else Alignment.Start
    val shape       = if (isMe) {
        RoundedCornerShape(18.dp, 18.dp, 4.dp, 18.dp)
    } else {
        RoundedCornerShape(18.dp, 18.dp, 18.dp, 4.dp)
    }

    if (message.deletedAt != null) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = if (isMe) Arrangement.End else Arrangement.Start
        ) {
            Text(
                text = "🚫 تم حذف هذه الرسالة",
                fontSize = 13.sp,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp)
            )
        }
        return
    }

    val showMenu = isMe

    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = alignment
    ) {
        Box(
            modifier = Modifier
                .widthIn(max = 280.dp)
                .clip(shape)
                .background(bubbleColor)
                .padding(horizontal = 14.dp, vertical = 10.dp)
                .combinedClickable(
                    onClick = {},
                    onLongClick = { if (showMenu) onLongPress(message) }
                )
        ) {
            Column {
                Text(
                    text = message.body ?: "",
                    color = textColor,
                    fontSize = 15.sp,
                    lineHeight = 22.sp
                )
                Row(
                    modifier = Modifier.align(Alignment.End).padding(top = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(4.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    if (message.editedAt != null) {
                        Text(
                            text = "(معدلة)",
                            fontSize = 10.sp,
                            color = if (isMe) Color.White.copy(alpha = 0.7f) else MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                    Text(
                        text = message.createdAt.take(16).takeLast(5),
                        fontSize = 10.sp,
                        color = if (isMe) Color.White.copy(alpha = 0.7f) else MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    if (isMe) {
                        Text(
                            text = when (message.status) {
                                "read"      -> "✓✓"
                                "delivered" -> "✓✓"
                                "sent"      -> "✓"
                                else        -> "⏳"
                            },
                            fontSize = 11.sp,
                            color = if (message.status == "read") Color(0xFF60A5FA) else Color.White.copy(alpha = 0.7f)
                        )
                    }
                }
                if (isEditable) {
                    Row(
                        modifier = Modifier
                            .align(Alignment.End)
                            .padding(top = 4.dp),
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        TextButton(
                            onClick = { onEditRequest(message) },
                            contentPadding = PaddingValues(horizontal = 6.dp, vertical = 0.dp)
                        ) {
                            Icon(Icons.Default.Edit, contentDescription = "تعديل", modifier = Modifier.size(13.dp))
                            Text("تعديل", fontSize = 11.sp)
                        }
                        TextButton(
                            onClick = { onLongPress(message) },
                            contentPadding = PaddingValues(horizontal = 6.dp, vertical = 0.dp)
                        ) {
                            Icon(Icons.Default.Delete, contentDescription = "حذف", modifier = Modifier.size(13.dp))
                            Text("حذف", fontSize = 11.sp, color = MaterialTheme.colorScheme.error)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun MessageComposer(
    text: String,
    onTextChange: (String) -> Unit,
    onSend: () -> Unit
) {
    Surface(
        tonalElevation = 3.dp,
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .padding(horizontal = 10.dp, vertical = 8.dp)
                .navigationBarsPadding(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            IconButton(onClick = { /* Attachment */ }) {
                Icon(Icons.Default.AttachFile, contentDescription = "إرفاق", tint = MaterialTheme.colorScheme.onSurfaceVariant)
            }

            OutlinedTextField(
                value = text,
                onValueChange = onTextChange,
                placeholder = { Text("اكتب رسالة...") },
                modifier = Modifier.weight(1f),
                shape = RoundedCornerShape(24.dp),
                maxLines = 4
            )

            if (text.isBlank()) {
                IconButton(onClick = { /* Voice record */ }) {
                    Icon(Icons.Default.Mic, contentDescription = "رسالة صوتية", tint = MaterialTheme.colorScheme.primary)
                }
            } else {
                IconButton(
                    onClick = onSend,
                    modifier = Modifier
                        .size(46.dp)
                        .clip(RoundedCornerShape(14.dp))
                        .background(MaterialTheme.colorScheme.primary)
                ) {
                    Icon(Icons.Default.Send, contentDescription = "إرسال", tint = Color.White)
                }
            }
        }
    }
}
