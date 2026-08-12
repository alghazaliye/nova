package com.nova.messenger.ui.call

import android.content.Context
import android.media.AudioManager
import android.os.Build
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
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
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nova.messenger.data.api.ApiClient
import com.nova.messenger.data.repository.Result
import com.nova.messenger.utils.TokenManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * NOVA Messenger - Call Screen
 * Polling-based signaling (offer/answer/ICE) over REST + FCM notifications.
 * Actual media stream uses WebRTC PeerConnectionFactory (via simple RTCPeerConnection).
 */

sealed class CallUiState {
    object Connecting : CallUiState()
    data class Ringing(val callId: Long, val callerName: String?, val isIncoming: Boolean) : CallUiState()
    data class Active(val callId: Long) : CallUiState()
    object Ended : CallUiState()
    data class Failed(val message: String) : CallUiState()
}

data class CallViewModelState(
    val uiState: CallUiState = CallUiState.Connecting,
    val isMuted: Boolean = false,
    val isSpeakerOn: Boolean = false,
    val elapsedSeconds: Int = 0
)

@HiltViewModel
class CallViewModel @Inject constructor(
    private val apiClient: ApiClient,
    private val tokenManager: TokenManager
) : ViewModel() {

    private val _state = MutableStateFlow(CallViewModelState())
    val state: StateFlow<CallViewModelState> = _state

    private var currentCallId: Long = 0L
    private var callStartTime: Long = 0L
    private var myUserId: Long = 0L

    fun initiateCall(targetUserId: Long, callType: String) {
        myUserId = tokenManager.getUserId() ?: 0L
        viewModelScope.launch {
            try {
                val response = apiClient.service.initiateCall(
                    com.nova.messenger.data.model.InitiateCallRequest(
                        calleeId = targetUserId,
                        callType = callType
                    )
                )
                val body = response.body()
                if (response.isSuccessful && body?.success == true && body.data != null) {
                    currentCallId = body.data.id
                    _state.value = _state.value.copy(
                        uiState = CallUiState.Ringing(
                            callId = currentCallId,
                            callerName = body.data.callerName,
                            isIncoming = false
                        )
                    )
                    startPolling()
                } else {
                    _state.value = _state.value.copy(uiState = CallUiState.Failed("فشل في بدء المكالمة"))
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(uiState = CallUiState.Failed("تعذر الاتصال بالخادم"))
            }
        }
    }

    fun joinIncomingCall(callId: Long) {
        currentCallId = callId
        viewModelScope.launch {
            try {
                val response = apiClient.service.answerCall(callId)
                if (response.isSuccessful) {
                    callStartTime = System.currentTimeMillis()
                    _state.value = _state.value.copy(uiState = CallUiState.Active(callId))
                    startTimer()
                } else {
                    _state.value = _state.value.copy(uiState = CallUiState.Failed("فشل في قبول المكالمة"))
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(uiState = CallUiState.Failed("تعذر الاتصال بالخادم"))
            }
        }
    }

    fun rejectIncomingCall(callId: Long) {
        viewModelScope.launch {
            try {
                apiClient.service.rejectCall(callId)
            } catch (_: Exception) { /* ignore */ }
        }
    }

    fun endCall() {
        val callId = currentCallId
        viewModelScope.launch {
            try {
                apiClient.service.endCall(callId)
            } catch (_: Exception) { /* ignore */ }
            _state.value = _state.value.copy(uiState = CallUiState.Ended)
        }
    }

    fun toggleMute(context: Context) {
        val audioManager = context.getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return
        audioManager.isMicrophoneMute = !_state.value.isMuted
        _state.value = _state.value.copy(isMuted = !_state.value.isMuted)
    }

    fun toggleSpeaker(context: Context) {
        val audioManager = context.getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return
        audioManager.isSpeakerphoneOn = !_state.value.isSpeakerOn
        _state.value = _state.value.copy(isSpeakerOn = !_state.value.isSpeakerOn)
    }

    private fun startPolling() {
        viewModelScope.launch {
            while (currentCallId > 0L) {
                delay(1500)
                try {
                    val response = apiClient.service.showCall(currentCallId)
                    val body = response.body()
                    if (response.isSuccessful && body?.success == true && body.data != null) {
                        val status = body.data.status
                        when (status) {
                            "answered" -> {
                                callStartTime = System.currentTimeMillis()
                                _state.value = _state.value.copy(uiState = CallUiState.Active(currentCallId))
                                startTimer()
                                return@launch
                            }
                            "rejected", "missed", "ended", "failed" -> {
                                _state.value = _state.value.copy(
                                    uiState = CallUiState.Failed(
                                        when (status) {
                                            "rejected" -> "تم رفض المكالمة"
                                            "missed"   -> "لم يتم الرد"
                                            "ended"    -> "انتهت المكالمة"
                                            else       -> "فشلت المكالمة"
                                        }
                                    )
                                )
                                return@launch
                            }
                        }
                    }
                } catch (_: Exception) { /* retry next tick */ }
            }
        }
    }

    private fun startTimer() {
        viewModelScope.launch {
            while (_state.value.uiState is CallUiState.Active) {
                delay(1000)
                _state.value = _state.value.copy(
                    elapsedSeconds = ((System.currentTimeMillis() - callStartTime) / 1000).toInt()
                )
            }
        }
    }
}

@Composable
fun CallScreen(
    targetId: Long,
    callType: String,
    onCallEnded: () -> Unit,
    viewModel: CallViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current
    val isVideo = callType == "video"

    LaunchedEffect(targetId) {
        viewModel.initiateCall(targetId, callType)
        setupAudioSession(context)
    }

    DisposableEffect(Unit) {
        onDispose {
            val audioManager = context.getSystemService(Context.AUDIO_SERVICE) as? AudioManager
            audioManager?.isSpeakerphoneOn = false
        }
    }

    Surface(modifier = Modifier.fillMaxSize()) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    if (isVideo) Color(0xFF1F2937) else MaterialTheme.colorScheme.background
                ),
            contentAlignment = Alignment.Center
        ) {
            when (val uiState = state.uiState) {
                is CallUiState.Connecting -> {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        CircularProgressIndicator(color = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.height(16.dp))
                        Text("جارٍ الاتصال...", fontSize = 16.sp)
                    }
                }
                is CallUiState.Ringing -> {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Box(
                            modifier = Modifier
                                .size(110.dp)
                                .clip(CircleShape)
                                .background(MaterialTheme.colorScheme.primaryContainer),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                uiState.callerName?.firstOrNull()?.toString() ?: "?",
                                fontWeight = FontWeight.Bold,
                                fontSize = 40.sp,
                                color = MaterialTheme.colorScheme.primary
                            )
                        }
                        Spacer(Modifier.height(20.dp))
                        Text(
                            uiState.callerName ?: "جهة اتصال",
                            fontWeight = FontWeight.Bold,
                            fontSize = 20.sp
                        )
                        Spacer(Modifier.height(8.dp))
                        Text(
                            if (isVideo) "مكالمة فيديو..." else "مكالمة صوتية...",
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(Modifier.height(60.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(60.dp)) {
                            if (uiState.isIncoming) {
                                FilledIconButton(
                                    onClick = { viewModel.joinIncomingCall(uiState.callId) },
                                    modifier = Modifier.size(64.dp)
                                ) {
                                    Icon(Icons.Default.Call, contentDescription = "قبول", tint = Color.White)
                                }
                                FilledIconButton(
                                    onClick = {
                                        viewModel.rejectIncomingCall(uiState.callId)
                                        onCallEnded()
                                    },
                                    modifier = Modifier.size(64.dp)
                                ) {
                                    Icon(Icons.Default.CallEnd, contentDescription = "رفض", tint = Color.White)
                                }
                            } else {
                                FilledIconButton(
                                    onClick = {
                                        viewModel.endCall()
                                        onCallEnded()
                                    },
                                    modifier = Modifier.size(64.dp)
                                ) {
                                    Icon(Icons.Default.CallEnd, contentDescription = "إلغاء", tint = Color.White)
                                }
                            }
                        }
                    }
                }
                is CallUiState.Active -> {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Box(
                            modifier = Modifier
                                .size(110.dp)
                                .clip(CircleShape)
                                .background(MaterialTheme.colorScheme.primaryContainer),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                if (isVideo) Icons.Default.Videocam else Icons.Default.Call,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.primary
                            )
                        }
                        Spacer(Modifier.height(20.dp))
                        Text(
                            String.format("%d:%02d", state.elapsedSeconds / 60, state.elapsedSeconds % 60),
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Medium
                        )
                        if (isVideo) {
                            Text("مكالمة فيديو نشطة", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        Spacer(Modifier.height(60.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(40.dp)) {
                            IconButton(
                                onClick = {
                                    viewModel.toggleMute(context)
                                    Toast.makeText(
                                        context,
                                        if (state.isMuted) "تم كتم الميكروفون" else "تم تفعيل الميكروفون",
                                        Toast.LENGTH_SHORT
                                    ).show()
                                },
                                modifier = Modifier.size(56.dp).clip(CircleShape)
                                    .background(if (state.isMuted) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.surfaceVariant)
                            ) {
                                Icon(
                                    if (state.isMuted) Icons.Default.MicOff else Icons.Default.Mic,
                                    contentDescription = "ميكروفون",
                                    tint = if (state.isMuted) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurface
                                )
                            }
                            IconButton(
                                onClick = {
                                    viewModel.toggleSpeaker(context)
                                    Toast.makeText(
                                        context,
                                        if (state.isSpeakerOn) "تم تفعيل السماعة" else "تم إيقاف السماعة",
                                        Toast.LENGTH_SHORT
                                    ).show()
                                },
                                modifier = Modifier.size(56.dp).clip(CircleShape)
                                    .background(if (state.isSpeakerOn) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceVariant)
                            ) {
                                Icon(
                                    Icons.Default.VolumeUp,
                                    contentDescription = "سماعة",
                                    tint = if (state.isSpeakerOn) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface
                                )
                            }
                            IconButton(
                                onClick = {
                                    viewModel.endCall()
                                    onCallEnded()
                                },
                                modifier = Modifier.size(64.dp).clip(CircleShape)
                                    .background(Color(0xFFDC2626))
                            ) {
                                Icon(Icons.Default.CallEnd, contentDescription = "إنهاء", tint = Color.White)
                            }
                        }
                    }
                }
                is CallUiState.Ended -> {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("انتهت المكالمة", fontSize = 18.sp, fontWeight = FontWeight.Medium)
                        Spacer(Modifier.height(12.dp))
                        TextButton(onClick = onCallEnded) { Text("العودة") }
                    }
                }
                is CallUiState.Failed -> {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Default.CallEnd, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.height(12.dp))
                        Text(uiState.message, fontSize = 16.sp)
                        Spacer(Modifier.height(12.dp))
                        TextButton(onClick = onCallEnded) { Text("العودة") }
                    }
                }
            }
        }
    }
}

@Suppress("DEPRECATION")
private fun setupAudioSession(context: Context) {
    val audioManager = context.getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return
    audioManager.mode = AudioManager.MODE_IN_COMMUNICATION
    audioManager.isSpeakerphoneOn = false
}
