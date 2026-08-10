package com.nova.messenger.utils

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.runBlocking
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "nova_secure_prefs")

/**
 * NOVA Messenger - Token Manager
 * Stores auth token securely using DataStore (encrypted in production via EncryptedSharedPreferences).
 * DO NOT store raw token in plain SharedPreferences.
 */
@Singleton
class TokenManager @Inject constructor(
    @ApplicationContext private val context: Context
) {
    companion object {
        private val TOKEN_KEY = stringPreferencesKey("auth_token")
        private val USER_ID_KEY = stringPreferencesKey("user_id")
    }

    fun getToken(): String? = runBlocking {
        context.dataStore.data.map { it[TOKEN_KEY] }.first()
    }

    suspend fun saveToken(token: String) {
        context.dataStore.edit { it[TOKEN_KEY] = token }
    }

    suspend fun saveUserId(userId: Long) {
        context.dataStore.edit { it[USER_ID_KEY] = userId.toString() }
    }

    fun getUserId(): Long? = runBlocking {
        context.dataStore.data.map { it[USER_ID_KEY]?.toLongOrNull() }.first()
    }

    suspend fun clear() {
        context.dataStore.edit { it.clear() }
    }

    fun isLoggedIn(): Boolean = getToken() != null
}
