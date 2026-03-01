# Smart Influencing

**Pure HTML + CSS + JavaScript Frontend | PHP + MySQL Backend**

Frontend:
- Pure HTML pages: `client/public/`
  - `home.html` - Marketing homepage
  - `brand-suite.html` - Brand features
  - `creator-suite.html` - Creator features
  - `pricing.html` - Pricing page
  - `pages/brand-auth.html` - Brand authentication
  - `pages/influencer-auth.html` - Influencer authentication
  - `pages/brand-dashboard.html` - Brand dashboard
  - `pages/influencer-dashboard.html` - Creator dashboard
- Pure CSS styling: `client/public/styles.css` (no SCSS compilation needed)
- JavaScript: `client/public/js/` (main.js, brand-auth.js, influencer-auth.js, etc.)

Backend:
- PHP API: `php/api.php`
- MySQL schema: `php/database.sql`

**No dependencies required!**
- ❌ No Node.js / npm
- ❌ No React / Vite
- ❌ No SCSS / Sass
- ❌ No Python
- ✅ Pure HTML + CSS + JavaScript
- ✅ PHP + MySQL

## Quick Start

### 1. Apache Setup (Required for PHP)

Since PHP files are outside your default web root, configure Apache VirtualHost:

#### Option A: Apache VirtualHost (Recommended)

1. Open your Apache VirtualHost configuration file
2. Add:

```apache
<VirtualHost *:80>
    ServerName smart.local
    DocumentRoot "C:/Users/your-user/path/to/web1/client/public"
    <Directory "C:/Users/your-user/path/to/web1/client/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Open `C:\Windows\System32\drivers\etc\hosts` and add:
```
127.0.0.1 smart.local
```

4. Restart Apache
5. Open: `http://smart.local/`

#### Option B: Apache Alias (if keeping default web root)

In Apache config:
```apache
Alias /web1/php "C:/Users/your-user/path/to/web1/php"
Alias /web1 "C:/Users/your-user/path/to/web1/client/public"
```

Then open: `http://localhost/web1/`

### 2. Database Setup

1. Start Apache + MySQL
2. Open your MySQL admin tool (or MySQL CLI)
3. Create database: `smart_influencing`
4. Import `php/database.sql`
5. Update `php/config.php` with your credentials (default: root / blank password)

### 3. Open the Website

```
http://smart.local/
or
http://localhost/web1/
```

Done! No build step, no dependencies, just pure HTML + CSS + JavaScript.

## Project Structure

```
web1/
├── client/
│   └── public/
│       ├── index.html → redirects to home.html
│       ├── home.html ✨
│       ├── brand-suite.html ✨
│       ├── creator-suite.html ✨
│       ├── pricing.html ✨
│       ├── styles.css ✨ (All styling, no SCSS)
│       ├── js/
│       │   ├── main.js (API integration)
│       │   ├── brand-auth.js
│       │   └── influencer-auth.js
│       └── pages/
│           ├── brand-auth.html
│           ├── influencer-auth.html
│           ├── brand-dashboard.html
│           └── influencer-dashboard.html
├── php/
│   ├── api.php (REST API)
│   ├── config.php (Database config)
│   └── database.sql (MySQL schema)
└── tools/
    └── README.md (For reference - no longer needed)
```

## Features

### Marketing Pages (Pure HTML + CSS)
- Responsive design (mobile-first)
- Modern gradient buttons
- Glassmorphism effects
- Animated elements
- No JavaScript framework overhead

### Authentication Pages
- Brand login/register
- Influencer login/register
- Form validation
- Session management via JWT tokens

### Backend API (`php/api.php`)
- REST endpoints for all operations
- JWT-based authentication
- 30+ routes for:
  - Authentication (brand/influencer login, register)
  - Profiles (view, edit)
  - Campaigns (create, list, update, delete)
  - Applications (apply, review, update progress)
  - Messaging (chat, notifications)
  - Shortlist (save creators)

### Database (`php/database.sql`)
8 tables:
- `brands` - Brand accounts
- `influencers` - Creator accounts
- `campaigns` - Job postings
- `applications` - Creator applications
- `wallets` - Earnings tracking
- `chats` - Direct messaging
- `notifications` - In-app alerts
- `brand_shortlists` - Favorites

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5 + CSS3 + JavaScript |
| Styling | Pure CSS (variables, grid, flexbox) |
| Icons | Font Awesome 6.0 |
| Fonts | Google Fonts (Manrope, Sora) |
| Backend | PHP 8.1+ (strict types) |
| Database | MySQL 8.0 |
| Authentication | JWT tokens (HS256) |
| Server | Apache + PHP FPM |

## API Routes

### Authentication
- `POST /brand/login` - Brand login
- `POST /brand/register` - Brand sign-up
- `POST /influencer/login` - Influencer login
- `POST /influencer/register` - Influencer sign-up

### Campaigns
- `GET /campaign` - List all campaigns
- `POST /campaign/create` - Create campaign
- `PUT /campaign/update/:id` - Edit campaign
- `POST /campaign/apply` - Apply to campaign
- `DELETE /campaigns/:id` - Delete campaign

### Applications
- `GET /campaign/applicants/:id` - View applications
- `PUT /campaign/application/:id` - Accept/reject application
- `PUT /campaign/application-progress/:id` - Update progress

### Messaging
- `GET /chat/list/:userId` - Get conversations
- `POST /chat/get` - Create chat
- `GET /chat/messages/:chatId` - Get messages
- `POST /chat/message` - Send message

### Notifications
- `GET /notification/list/:userId` - Get notifications
- `PUT /notification/read/:id` - Mark as read

*And more...* (see `php/api.php` for complete list)

## No Build Step Needed

Just open the HTML files in your browser!

```
✅ Direct file serving
✅ No npm install
✅ No webpack/Vite build
✅ No SCSS compilation
✅ No Python scripts
✅ No asset optimization
```

Everything is pure, vanilla, and production-ready.

## Configuration

### Database Connection (`php/config.php`)

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'smart_influencing');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_KEY', 'your-secret-key-here');
```

### Allowed Origins (CORS)

Default: `http://localhost`, `http://127.0.0.1`

For production, set via environment variable:
```bash
export APP_ALLOWED_ORIGINS="https://example.com,https://www.example.com"
```

## Theme Colors

All colors in `styles.css`:
```css
--primary: #014f86 (Deep Blue)
--primary-2: #2a9d8f (Teal)
--accent: #e76f51 (Orange)
--accent-2: #f4a261 (Warm Orange)
--ink: #0b1620 (Dark)
--ink-soft: #415162 (Gray)
```

## Responsive Breakpoints

- **Desktop:** Full width (1180px container)
- **Tablet:** 1120px and below (single column grids)
- **Mobile:** 760px and below (optimized for touch)

## Browser Support

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers (iOS Safari, Chrome Android)

## Status

✅ Complete and production-ready
✅ All features working
✅ Responsive design
✅ Zero external dependencies beyond Google Fonts & Font Awesome
✅ PHP backend with MySQL
✅ JWT authentication
✅ Real-time notifications
✅ Messaging system
✅ Campaign management
