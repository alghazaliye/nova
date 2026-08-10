package com.nova.messenger.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight

// =====================================================
// NOVA Messenger Design System
// Based on index.html color palette
// =====================================================

// Light Colors (from index.html :root)
val LightBackground    = Color(0xFFF5F7FB)
val LightSurface       = Color(0xFFFFFFFF)
val LightSurface2      = Color(0xFFEEF2F7)
val LightText          = Color(0xFF101828)
val LightMuted         = Color(0xFF667085)
val LightAccent        = Color(0xFF5B5CE2)
val LightAccent2       = Color(0xFF7C3AED)
val LightBubble        = Color(0xFFE9E8FF)
val LightMine          = Color(0xFF5B5CE2)

// Dark Colors (from index.html [data-theme=dark])
val DarkBackground     = Color(0xFF080D18)
val DarkSurface        = Color(0xFF111827)
val DarkSurface2       = Color(0xFF1B2535)
val DarkText           = Color(0xFFF3F4F6)
val DarkMuted          = Color(0xFF98A2B3)
val DarkAccent         = Color(0xFF8B7CF7)
val DarkAccent2        = Color(0xFFA78BFA)
val DarkBubble         = Color(0xFF202A3B)
val DarkMine           = Color(0xFF6758E8)

private val LightColorScheme = lightColorScheme(
    primary          = LightAccent,
    onPrimary        = Color.White,
    primaryContainer = LightBubble,
    secondary        = LightAccent2,
    background       = LightBackground,
    surface          = LightSurface,
    surfaceVariant   = LightSurface2,
    onBackground     = LightText,
    onSurface        = LightText,
    outline          = Color(0xFFE5E7EB),
)

private val DarkColorScheme = darkColorScheme(
    primary          = DarkAccent,
    onPrimary        = Color.White,
    primaryContainer = DarkBubble,
    secondary        = DarkAccent2,
    background       = DarkBackground,
    surface          = DarkSurface,
    surfaceVariant   = DarkSurface2,
    onBackground     = DarkText,
    onSurface        = DarkText,
    outline          = Color(0xFF263244),
)

@Composable
fun NovaMessengerTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme

    MaterialTheme(
        colorScheme = colorScheme,
        content     = content
    )
}
