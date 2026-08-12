package com.nova.messenger.ui.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel

// =====================================================
// WhatsApp-style step 1: Country code + Phone number
// =====================================================

data class Country(val name: String, val code: String, val dialCode: String)

val COMMON_COUNTRIES = listOf(
    Country("السعودية", "SA", "+966"),
    Country("الإمارات", "AE", "+971"),
    Country("الكويت", "KW", "+965"),
    Country("قطر", "QA", "+974"),
    Country("البحرين", "BH", "+973"),
    Country("عُمان", "OM", "+968"),
    Country("مصر", "EG", "+20"),
    Country("الأردن", "JO", "+962"),
    Country("العراق", "IQ", "+964"),
    Country("اليمن", "YE", "+967"),
    Country("سوريا", "SY", "+963"),
    Country("لبنان", "LB", "+961"),
    Country("فلسطين", "PS", "+970"),
    Country("ليبيا", "LY", "+218"),
    Country("تونس", "TN", "+216"),
    Country("الجزائر", "DZ", "+213"),
    Country("المغرب", "MA", "+212"),
    Country("السودان", "SD", "+249"),
    Country("تركيا", "TR", "+90"),
    Country("الهند", "IN", "+91"),
    Country("المملكة المتحدة", "GB", "+44"),
    Country("الولايات المتحدة", "US", "+1")
)

@Composable
fun PhoneScreen(
    onNavigateToOtp: (phone: String) -> Unit,
    viewModel: AuthViewModel = hiltViewModel()
) {
    var selectedIndex by remember { mutableIntStateOf(0) }
    var phone by remember { mutableStateOf("") }
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(uiState) {
        if (uiState is AuthUiState.OtpSent) {
            val fullPhone = COMMON_COUNTRIES[selectedIndex].dialCode + phone.trimStart('0')
            onNavigateToOtp(fullPhone)
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
        // Logo
        Box(
            modifier = Modifier
                .size(80.dp)
                .background(
                    brush = Brush.linearGradientBrush(
                        colors = listOf(
                            MaterialTheme.colorScheme.primary,
                            MaterialTheme.colorScheme.secondary
                        )
                    ),
                    shape = RoundedCornerShape(24.dp)
                ),
            contentAlignment = Alignment.Center
        ) {
            Text(
                "N",
                fontSize = 36.sp,
                fontWeight = FontWeight.Black,
                color = androidx.compose.ui.graphics.Color.White
            )
        }

        Spacer(Modifier.height(24.dp))

        Text(
            text = "NOVA Messenger",
            fontSize = 28.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground
        )

        Text(
            text = "أدخل رقم هاتفك لتلقي رمز التحقق",
            fontSize = 15.sp,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(top = 8.dp, bottom = 40.dp)
        )

        // Country code dropdown
        var expanded by remember { mutableStateOf(false) }
        ExposedDropdownMenuBox(
            expanded = expanded,
            onExpandedChange = { expanded = !expanded },
            modifier = Modifier.fillMaxWidth()
        ) {
            OutlinedTextField(
                value = COMMON_COUNTRIES[selectedIndex].dialCode + " " + COMMON_COUNTRIES[selectedIndex].name,
                onValueChange = {},
                readOnly = true,
                label = { Text("رمز الدولة") },
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor(),
                shape = RoundedCornerShape(16.dp),
                trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded) },
                singleLine = true
            )
            ExposedDropdownMenu(
                expanded = expanded,
                onDismissRequest = { expanded = false }
            ) {
                COMMON_COUNTRIES.forEachIndexed { index, country ->
                    DropdownMenuItem(
                        text = { Text("${country.dialCode}  ${country.name}") },
                        onClick = {
                            selectedIndex = index
                            expanded = false
                        }
                    )
                }
            }
        }

        Spacer(Modifier.height(16.dp))

        // Phone field
        OutlinedTextField(
            value = phone,
            onValueChange = { phone = it.filter { ch -> ch.isDigit() }.take(15) },
            label = { Text("رقم الهاتف") },
            placeholder = { Text("5XXXXXXXX") },
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            singleLine = true
        )

        Spacer(Modifier.height(24.dp))

        // Error message
        if (uiState is AuthUiState.Error) {
            Text(
                text = (uiState as AuthUiState.Error).message,
                color = MaterialTheme.colorScheme.error,
                fontSize = 14.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(bottom = 12.dp)
            )
        }

        // Submit Button
        Button(
            onClick = {
                viewModel.requestOtp(
                    phone.trim(),
                    COMMON_COUNTRIES[selectedIndex].dialCode.trimStart('+')
                )
            },
            modifier = Modifier
                .fillMaxWidth()
                .height(54.dp),
            shape = RoundedCornerShape(16.dp),
            enabled = phone.trim().length >= 7 && uiState !is AuthUiState.Loading
        ) {
            if (uiState is AuthUiState.Loading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(22.dp),
                    color = androidx.compose.ui.graphics.Color.White,
                    strokeWidth = 2.dp
                )
            } else {
                Text("إرسال رمز التحقق", fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

// Helper extension for gradient brush
fun Brush.Companion.linearGradientBrush(colors: List<androidx.compose.ui.graphics.Color>) =
    linearGradient(colors)

