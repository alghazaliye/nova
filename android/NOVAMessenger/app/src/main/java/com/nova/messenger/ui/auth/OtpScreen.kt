package com.nova.messenger.ui.auth

import android.provider.Settings
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.firebase.messaging.FirebaseMessaging

// =====================================================
// WhatsApp-style step 2: Enter the 6-digit OTP code
// =====================================================

@Composable
fun OtpScreen(
    phone: String,
    onNavigateToProfile: () -> Unit,
    onBackToPhone: () -> Unit,
    viewModel: AuthViewModel = hiltViewModel()
) {
    var otp by remember { mutableStateOf("") }
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current

    LaunchedEffect(uiState) {
        if (uiState is AuthUiState.VerifySuccess) {
            onNavigateToProfile()
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(horizontal = 28.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text("✉️", fontSize = 56.sp)

        Spacer(Modifier.height(20.dp))

        Text(
            text = "رمز التحقق",
            fontSize = 26.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground
        )

        Text(
            text = "تم إرسال رمز التحقق المكوّن من 6 أرقام إلى:\n$phone",
            fontSize = 15.sp,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(top = 8.dp, bottom = 32.dp)
        )

        OutlinedTextField(
            value = otp,
            onValueChange = { if (it.length <= 6) otp = it },
            label = { Text("رمز التحقق (6 أرقام)") },
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
            singleLine = true,
            textStyle = LocalTextStyle.current.copy(
                textAlign = TextAlign.Center,
                fontSize = 24.sp,
                letterSpacing = 8.sp
            )
        )

        Spacer(Modifier.height(16.dp))

        if (uiState is AuthUiState.Error) {
            Text(
                text = (uiState as AuthUiState.Error).message,
                color = MaterialTheme.colorScheme.error,
                fontSize = 14.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(bottom = 8.dp)
            )
        }

        Button(
            onClick = {
                val deviceUuid = Settings.Secure.getString(
                    context.contentResolver,
                    Settings.Secure.ANDROID_ID
                )
                FirebaseMessaging.getInstance().token.addOnSuccessListener { fcmToken ->
                    viewModel.verifyOtp(phone, otp, deviceUuid, fcmToken)
                }.addOnFailureListener {
                    viewModel.verifyOtp(phone, otp, deviceUuid, null)
                }
            },
            modifier = Modifier
                .fillMaxWidth()
                .height(54.dp),
            shape = RoundedCornerShape(16.dp),
            enabled = otp.length == 6 && uiState !is AuthUiState.Loading
        ) {
            if (uiState is AuthUiState.Loading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(22.dp),
                    color = androidx.compose.ui.graphics.Color.White,
                    strokeWidth = 2.dp
                )
            } else {
                Text("تحقق", fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
            }
        }

        Spacer(Modifier.height(16.dp))

        // Re-send code / use a different number
        TextButton(
            onClick = {
                val countryPart = phone.substringBefore(phone.trimStart('+').trimStart { it.isDigit() })
                viewModel.resendOtp(phone.trimStart('+').trimStart { it.isDigit() })
            },
            enabled = uiState !is AuthUiState.Loading
        ) {
            Text("إعادة إرسال الرمز", fontSize = 15.sp)
        }

        TextButton(
            onClick = onBackToPhone,
            enabled = uiState !is AuthUiState.Loading
        ) {
            Text("استخدام رقم آخر", fontSize = 15.sp)
        }
    }
}
