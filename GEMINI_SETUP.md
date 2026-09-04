# Gemini AI Chatbot Integration Guide

## Overview
Your Thread and Press Hub chatbot is now powered by Google's Gemini AI, providing intelligent, context-aware responses to customer inquiries.

## Setup Instructions

### 1. Get Your Gemini API Key

1. Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Sign in with your Google account
3. Click **"Create API Key"**
4. Copy the generated API key

### 2. Configure the API Key

#### Option A: Environment Variable (Recommended for Production)
Add to your server environment variables:
```
GEMINI_API_KEY=your_api_key_here
```

Then update [includes/config.php](includes/config.php):
```php
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
```

#### Option B: Direct Configuration (Quick Setup)
1. Open [includes/config.php](includes/config.php)
2. Replace `YOUR_GEMINI_API_KEY_HERE` with your actual API key:
   ```php
   define('GEMINI_API_KEY', 'your_api_key_here');
   ```

### 3. Test the Integration

1. Start your local server (XAMPP)
2. Open your website in a browser
3. Click the chatbot icon
4. Send a test message like "Hello"
5. The bot should respond using Gemini AI

## Features

✅ **AI-Powered Responses** - Uses Google's Gemini model for intelligent answers
✅ **Store Context** - Customized to your Thread and Press Hub business
✅ **24/7 Support** - Instant responses to customer questions
✅ **Fallback System** - Pre-defined responses if API is unavailable
✅ **Error Handling** - Graceful degradation with helpful error messages

## Technical Architecture

### Files Added/Modified

1. **includes/gemini_api.php** (New)
   - Backend API handler for Gemini requests
   - Receives messages from chatbot
   - Returns AI-generated responses

2. **js/chatbot.js** (Updated)
   - Now calls Gemini API instead of static responses
   - Async/await for smooth user experience
   - Typing indicator while waiting for response
   - Fallback to predefined responses if API fails

3. **includes/config.php** (Updated)
   - Added `GEMINI_API_KEY` configuration
   - Stores your API authentication

## API Configuration

### Request Format
```php
POST includes/gemini_api.php
Body: {
    "message": "User's message here",
    "api_key": "GEMINI_API_KEY"
}
```

### Response Format
```json
{
    "success": true,
    "message": "AI generated response here"
}
```

### Error Handling
If API fails or key is missing:
```json
{
    "error": "Error description"
}
```

## Customization

### Change the System Prompt
The chatbot's personality and store context is defined in `includes/gemini_api.php`:

```php
$systemPrompt = "You are a helpful customer support chatbot..."
```

Edit this to customize the bot's behavior.

### Adjust Temperature & Response Length
In `includes/gemini_api.php`, modify:

```php
'generationConfig' => [
    'temperature' => 0.7,      // 0 = precise, 1 = creative
    'topK' => 40,              // Token diversity
    'topP' => 0.95,            // Probability threshold
    'maxOutputTokens' => 1024  // Max response length
]
```

## Pricing & Limits

- **Free Tier**: 60 requests/minute, 1500 requests/day
- **Pricing**: Check [Google AI Studio pricing](https://makersuite.google.com/app/apikey)
- **Rate Limits**: Implement rate limiting in production

## Security Notes

⚠️ **Important**: 
- Keep your API key confidential
- Use environment variables in production
- Never commit API keys to version control
- Implement rate limiting to prevent abuse
- Add user authentication if needed

## Monitoring & Troubleshooting

### Check Browser Console
- Open DevTools (F12)
- Go to Console tab
- Look for any error messages

### Check Server Logs
- Monitor `includes/gemini_api.php` for curl errors
- Verify API key is valid
- Check internet connectivity

### Common Issues

**"API key not configured"**
- Verify API key is set in config.php
- Ensure environment variable is set if using that method

**"API request failed"**
- Check internet connection
- Verify API key is valid at Google AI Studio
- Check if you've exceeded rate limits

**Fallback responses showing**
- API is temporarily unavailable
- Check the browser console for errors
- Verify API key and network connection

## Testing Checklist

- [ ] API key is configured in config.php
- [ ] Files are uploaded: gemini_api.php, updated chatbot.js and config.php
- [ ] Browser console shows no errors
- [ ] Chatbot responds to test messages
- [ ] Fallback responses work if API is down

## Support

If you encounter issues:
1. Check the troubleshooting section above
2. Verify your Google API key is valid
3. Check browser console for error messages
4. Ensure gemini_api.php is in the includes folder
5. Verify chatbot HTML elements exist (chatbot-widget, chat-messages, etc.)

## Future Enhancements

Consider adding:
- [ ] Message history/conversation context
- [ ] User rating system for responses
- [ ] Analytics tracking
- [ ] Rate limiting & abuse prevention
- [ ] Multi-language support
- [ ] Integration with order database
- [ ] Admin analytics dashboard
