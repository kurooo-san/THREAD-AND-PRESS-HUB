# Gemini AI Chatbot - Quick Start

## What Was Done ✅

Your Thread and Press Hub chatbot is now integrated with **Google's Gemini AI**!

### Files Created:
1. **includes/gemini_api.php** - Backend API handler

### Files Updated:
1. **includes/config.php** - Added Gemini API key configuration
2. **js/chatbot.js** - Integrated with Gemini API

## 3 Steps to Activate

### Step 1: Get API Key
- Go to: https://makersuite.google.com/app/apikey
- Click "Create API Key"
- Copy your key

### Step 2: Add Key to Config
Open `includes/config.php` and replace:
```php
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
```
with:
```php
define('GEMINI_API_KEY', 'your_actual_api_key_here');
```

### Step 3: Test
- Reload your website
- Click chatbot icon
- Send a message
- AI responds! 🎉

## Features

🤖 **AI-Powered** - Smart responses using Gemini
💬 **Fast** - Real-time conversations  
🛡️ **Fallback** - Works offline with predefined responses
📱 **Mobile** - Works on all devices
♾️ **24/7** - Always available

## What the Bot Can Help With

- Product information & collections
- Pricing & discounts
- Delivery & shipping
- Payment methods
- Order tracking
- Returns & exchanges
- Account management
- General store questions

## System Prompt (Customize in gemini_api.php)

The bot is trained to:
- Help with Thread and Press Hub products
- Answer questions about orders
- Provide store information
- Give friendly, helpful responses
- Use relevant emojis

## Troubleshooting

**Bot not responding?**
- Check API key is correct in config.php
- Verify gemini_api.php is in includes folder
- Check browser console (F12) for errors
- Ensure internet connection

**Getting error messages?**
- "API key not configured" → Add key to config.php
- "API request failed" → Verify key is valid at Google AI Studio
- Chatbot falls back to preset responses if API fails

## File Locations

```
includes/
├── config.php (UPDATE with API key)
├── gemini_api.php (NEW)
js/
├── chatbot.js (UPDATED)
GEMINI_SETUP.md (Complete guide)
```

## Production Tips

For live deployment:
1. Use environment variables for API key (don't hardcode)
2. Add rate limiting
3. Monitor API usage & costs
4. Set up error logging
5. Consider implementing user authentication

## Free Tier Limits

- 60 requests per minute
- 1,500 requests per day

## Need Help?

- Full guide: See GEMINI_SETUP.md
- Google AI: https://makersuite.google.com
- Gemini API Docs: https://ai.google.dev/docs

Enjoy your AI chatbot! 🚀
