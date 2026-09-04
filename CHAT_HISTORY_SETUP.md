# Chat History Setup Guide

## Overview
You now have a complete chat history system that:
- **Stores** all your conversations with the AI chatbot in your database
- **Displays** your chat history on a dedicated page
- **Tracks** timestamps for each conversation
- **Requires** user login for security

## Setup Instructions

### Step 1: Create the Chat History Table

Run the migration SQL file to create the chat history table in your database:

1. Open **phpMyAdmin** (usually at `http://localhost/phpmyadmin`)
2. Select your `threadpresshub` database
3. Go to the **SQL** tab
4. Copy and paste the contents of `migrate_chat_history.sql`
5. Click **Execute**

Alternatively, you can run this SQL directly:

```sql
CREATE TABLE IF NOT EXISTS `chat_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `user_message` text NOT NULL,
  `bot_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `chat_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2: Files Modified

The following files have been updated:

#### **includes/gemini_api.php**
- Added database connection
- Now saves every chat message to the `chat_history` table
- Only saves if user is logged in
- Includes both user message and bot response

#### **chat_history.php** (NEW)
- View your chat history
- Shows conversations in chronological order
- Displays user messages and bot responses
- Shows timestamp for each message
- Requires user login
- Accessible from your profile page

### Step 3: How to Use

#### **To Chat and Save History:**
1. Make sure you're **logged in**
2. Click the **chatbot icon** on any page
3. Send a message to the AI assistant
4. The conversation is automatically saved to your database

#### **To View Your Chat History:**
1. Make sure you're **logged in**
2. Visit: `http://yoursite.com/chat_history.php`
3. Or click the link in your profile page
4. Browse all your past conversations

### Step 4: Database Structure

The `chat_history` table has the following columns:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Unique ID for each message pair |
| `user_id` | INT | Foreign key to the users table |
| `user_message` | TEXT | The message sent by the user |
| `bot_response` | TEXT | The response from the AI chatbot |
| `created_at` | TIMESTAMP | When the conversation happened |

### Step 5: Features

✅ **Automatic Logging** - Every chat is saved automatically  
✅ **User-Specific** - Each user only sees their own chat history  
✅ **Secure** - Requires login to view chatbot history  
✅ **Formatted Display** - Easy-to-read chat interface  
✅ **Timestamps** - Know when each conversation occurred  
✅ **Total Count** - See how many conversations you've had  

### Step 6: Important Notes

- Chat history is **only saved** when a user is **logged in**
- If you chat without logging in, the message won't be saved
- Each user can only see **their own** chat history
- Deleting a user account will also delete their chat history (cascading delete)
- Chat history is stored in plain text in the database

## Troubleshooting

**Q: Chat history is not being saved**
- A: Make sure you are **logged in** before chatting
- A: Check that the database table was created successfully

**Q: I can't access the chat history page**
- A: Make sure you are **logged in** (you'll be redirected to login.php if not)
- A: Check that `chat_history.php` is in your root directory

**Q: Only seeing blank responses**
- A: Make sure the Gemini API is working properly
- A: Check your API key in `includes/gemini_api.php`

## Files You Need to Create/Modify

1. ✅ `migrate_chat_history.sql` - Database migration file (created)
2. ✅ `includes/gemini_api.php` - Updated to save messages (modified)
3. ✅ `chat_history.php` - New page to view chat history (created)

## Next Steps

1. Run the SQL migration to create the table
2. Test by logging in and chatting with the bot
3. Visit the chat history page to see your conversations
4. Share the chat_history.php URL with your profile page if desired

Enjoy your new chat history feature! 🎉
