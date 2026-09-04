# Contact Form System - Complete Guide

## 📋 Overview

The contact form system is a **separate database solution** for managing customer inquiries. It isolates contact messages from the main application database and provides an admin panel for responses.

**Database:** `threadpresshub_contact`  
**Tables:** 3 (contact_messages, contact_categories, contact_messages_responses)  
**Admin Panel:** `/admin/contact-management.php`

---

## 🚀 Setup & Installation

### Step 1: Import Database Migration

**Option A: Using phpMyAdmin (Recommended)**
```
1. Open http://localhost/phpmyadmin
2. Left sidebar → Click "threadpresshub" database
3. Top menu → Click "Import" tab
4. Click "Choose File" button
5. Select: migrate_contact_database.sql (from project root)
6. Click blue "Import" button at bottom
```

**Option B: Using Command Line**
```bash
cd C:\xampp\htdocs\thread-and-presshub
mysql -u root < migrate_contact_database.sql
```

**What Gets Created:**
- ✅ Database: `threadpresshub_contact` (if doesn't exist)
- ✅ Table: `contact_messages` (15 columns with proper indexing)
- ✅ Table: `contact_categories` (10 predefined categories)
- ✅ Table: `contact_messages_responses` (admin response tracking)
- ✅ 3 sample test messages (for reference)

### Step 2: Verify Installation

Check in phpMyAdmin:
1. Left sidebar → `threadpresshub_contact` database should appear
2. Click it → You should see 3 tables listed
3. Click `contact_categories` → 10 rows should be visible

---

## 🧪 Testing

### Test 1: Submit Contact Form

1. **Go to:** `/contact.php` (customer-facing page)
2. **Fill the form:**
   - Name: `Test Customer`
   - Email: `test@example.com`
   - Phone: `09123456789` (optional)
   - Subject: `Test Message`
   - Message: `This is a test message`
   - Category: Select any (e.g., "General Inquiry")
   - Priority: Select any (e.g., "Normal")
3. **Click:** "Send Message" button
4. **Verify:** See success message ✅

### Test 2: Check Admin Panel (List View)

1. **Login** as admin at `/login.php`
2. **Go to:** `/admin/dashboard.php`
3. **Click:** "Contact Messages" card
4. **Or direct:** `/admin/contact-management.php`
5. **Verify you see:**
   - Stats boxes (Total, New, Responded, Closed)
   - Filters (by Status and Category)
   - List of messages with your test message

### Test 3: Check Database

1. **Open phpMyAdmin**
2. **Navigate to:** `threadpresshub_contact` → `contact_messages`
3. **Click** "Browse" tab
4. **Verify:** Your test message appears with:
   - All fields populated (name, email, subject, message, etc.)
   - Status: `new`
   - Created timestamp
   - Priority and category saved correctly

### Test 4: Update Message Status

1. **In admin panel:** Click on your test message
2. **In detail view:**
   - Update Status: `new` → `read`
   - Add a Response: "Thank you for contacting us!"
   - Add Internal Notes: "Customer inquiry received"
3. **Click:** "Save & Update"
4. **Verify:**
   - Status bar updates to "Read"
   - Success message appears
   - Response and notes are saved

### Test 5: Verify Email Fields

1. **In admin panel:** Click message
2. **Verify displayed:**
   - Customer name
   - Email address (clickable mailto)
   - Phone number (if provided)
   - Category and priority badges
   - Timestamp

---

## 🔧 Files & Structure

### Customer-Facing Files
- **`contact.php`** — Contact form page with validation
  - Accepts: name, email, phone, subject, message, category, priority
  - Validates: All required fields, email format, message length
  - Saves to: `threadpresshub_contact.contact_messages`

### Admin Files
- **`admin/contact-management.php`** — Admin dashboard
  - Views: List all messages OR single message detail
  - Actions: Update status, send response, add notes, delete
  - Filters: By status and category
  - Stats: Dashboard with message counts

### Configuration Files
- **`includes/contact-config.php`** — Database connection & helper functions
  - Functions:
    - `getContactDB()` — Get database connection
    - `insertContactMessage()` — Save form submission
    - `getContactMessage()` — Fetch single message
    - `getContactCategories()` — List all categories
    - `updateContactMessageStatus()` — Change status
    - `addContactResponse()` — Store admin response

### Database Migration
- **`migrate_contact_database.sql`** — Creates schema and sample data
  - 3 tables with proper relationships
  - 10 predefined categories
  - Proper indexing for performance

---

## 📊 Database Schema

### contact_messages Table
```sql
id (int) - Primary key
name (varchar) - Customer name
email (varchar) - Customer email
phone (varchar) - Phone number (optional)
subject (varchar) - Message subject
message (longtext) - Full message
category (varchar) - Inquiry category
priority (enum) - urgent/high/normal/low
status (enum) - new/read/responded/closed
admin_notes (longtext) - Internal admin notes
assigned_to (int) - Admin user ID
ip_address (varchar) - Customer IP
user_agent (varchar) - Browser info
created_at (datetime) - Submission time
updated_at (datetime) - Last modification
```

### contact_categories Table
```sql
id (int) - Primary key
name (varchar) - Category name
description (text) - Why this category
created_at (datetime)
```

### contact_messages_responses Table
```sql
id (int) - Primary key
contact_id (int) - Links to contact_messages
admin_id (int) - Which admin replied
response (longtext) - The response text
created_at (datetime) - When replied
```

---

## 🔐 Security Features

✅ **Prepared Statements** — Prevents SQL injection  
✅ **Input Validation** — Client & server-side checks  
✅ **Email Validation** — Proper format checking  
✅ **Admin-Only Access** — Contact panel requires authentication  
✅ **CSRF Protection** — Standard PHP sessions  
✅ **XSS Prevention** — HTML escaping on output  
✅ **IP Logging** — Tracks customer source  

---

## 🎯 Common Tasks

### Add New Category
In phpMyAdmin:
```sql
INSERT INTO threadpresshub_contact.contact_categories 
(name, description) VALUES ('New Category', 'Description here');
```

### Delete Old Messages
In phpMyAdmin:
```sql
DELETE FROM threadpresshub_contact.contact_messages 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Mark All as Read
In phpMyAdmin:
```sql
UPDATE threadpresshub_contact.contact_messages 
SET status = 'read' WHERE status = 'new';
```

### Export All Messages
In phpMyAdmin:
1. Navigate to `threadpresshub_contact.contact_messages`
2. Click "Export" tab
3. Select "SQL" format
4. Click "Go"

---

## ❌ Troubleshooting

### Error: "Unknown database 'threadpresshub_contact'"
**Solution:** Import `migrate_contact_database.sql`

### Error: "Access denied" on admin panel
**Solution:** Login as admin first at `/login.php`

### Error: "Table contact_categories doesn't exist"
**Solution:** Check migration was imported (see Step 1)

### Form not saving
1. Check in phpMyAdmin that tables exist
2. Verify `admin_notes` column exists in schema
3. Check contact.php includes `contact-config.php`

### Admin panel shows no messages
- Check status filter (default shows all)
- Check in phpMyAdmin directly
- Verify category dropdown works

---

## 📱 Features

✨ **Customer Features**
- Modern responsive form
- Multiple input fields (name, email, phone, subject, message)
- Category selection
- Priority selection
- Form validation with error messages
- Success confirmation

✨ **Admin Features**
- Dashboard stats (Total, New, Responded, Closed)
- Filter by status and category
- Sort by priority
- Single message detail view
- Update message status
- Send response to customer
- Add internal admin notes
- Delete messages
- Beautiful Bootstrap UI
- Mobile responsive

---

## 🌐 Deployment Checklist

Before going live:

- [ ] Import `migrate_contact_database.sql` into production database
- [ ] Test contact form submission (check admin panel receives it)
- [ ] Test admin panel access (verify requires login)
- [ ] Test status workflow (new → read → responded → closed)
- [ ] Verify email addresses display correctly
- [ ] Test filters work (status and category)
- [ ] Check responsive design on mobile
- [ ] Verify database backups include `threadpresshub_contact`
- [ ] Add Contact link to footer/navigation if needed
- [ ] Train admins on how to use contact panel

---

## 📞 Support

If you need to:

**Add more categories:**
Update `contact_categories` table in phpMyAdmin

**Change form fields:**
Edit validation in `contact.php` lines 20-40

**Customize email templates:**
Update in `includes/contact-config.php` (add email function)

**Modify admin UI:**
Edit `admin/contact-management.php` CSS and Bootstrap markup

---

**System Status:** ✅ Ready for Production

Last Updated: March 10, 2026
