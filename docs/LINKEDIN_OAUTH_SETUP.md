# LinkedIn OAuth Setup Guide

This guide explains how to set up LinkedIn OAuth authentication for local development and production.

## Prerequisites

- LinkedIn Developer Account
- LinkedIn App created in LinkedIn Developer Portal

## LinkedIn App Configuration

1. Go to [LinkedIn Developers](https://www.linkedin.com/developers/)
2. Create a new app or select existing app
3. In "Auth" tab:
   - Add redirect URL: `http://localhost/auth/linkedin/callback` (local)
   - Add redirect URL: `https://yourdomain.com/auth/linkedin/callback` (production)
4. In "Products" tab:
   - Request access to "Sign In with LinkedIn using OpenID Connect"
5. Copy your Client ID and Client Secret

## Environment Configuration

Add to `.env`:

```env
LINKEDIN_CLIENT_ID=your_client_id_here
LINKEDIN_CLIENT_SECRET=your_client_secret_here
LINKEDIN_REDIRECT_URI="${APP_URL}/auth/linkedin/callback"
```

## Testing OAuth Flow

### Test New User Registration

1. Visit `/login`
2. Click "Log in with LinkedIn"
3. Authorize the app on LinkedIn
4. Verify you're logged in and redirected to dashboard
5. Check database: user created with no password, social_account created

### Test Existing User Auto-Link

1. Create user with email `test@example.com`
2. Log out
3. Click "Log in with LinkedIn"
4. Use LinkedIn account with same email `test@example.com`
5. Verify auto-linked and logged in

### Test Manual Linking

1. Log in with email/password
2. Go to Settings > LinkedIn
3. Click "Connect LinkedIn account"
4. Authorize on LinkedIn
5. Verify account linked in settings

### Test Disconnect

1. Ensure user has password set
2. Go to Settings > LinkedIn
3. Click "Disconnect"
4. Confirm in dialog
5. Verify LinkedIn removed but can still log in with password

### Test Disconnect Without Password

1. Create user via LinkedIn only (no password)
2. Go to Settings > LinkedIn
3. Try to disconnect
4. Verify error message requiring password
5. Set password in Settings > Password
6. Retry disconnect - should work

## Troubleshooting

### "Invalid redirect URI" error

- Ensure redirect URI in LinkedIn app matches exactly (including http/https)
- Check `.env` has correct `APP_URL`

### "Email not provided" error

- Ensure app has access to "Sign In with LinkedIn using OpenID Connect"
- Check scopes include 'email'

### User created without email

- LinkedIn user may not have verified email
- Check LinkedIn profile has public email

## Security Notes

- Never commit `.env` with real credentials
- Use different LinkedIn apps for dev/staging/production
- Rotate client secret if compromised
- Access tokens are stored encrypted in database
