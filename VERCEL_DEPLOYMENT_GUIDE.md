# 🚀 Vercel Deployment Guide - Saraswati Hardware Store Management & Billing System

This project is now fully configured for deployment on **Vercel** serverless functions using the PHP runtime (`vercel-php`).

---

## 📋 What was Configured for Vercel

1. **`vercel.json`**:
   - Uses `vercel-php@0.9.0` (PHP 8.5) serverless runtime.
   - Configured edge filesystem static routing so CSS, JS, and icons are served at lightning speed via Vercel's global CDN.
   - Seamlessly routes all clean URLs and PHP endpoints to `api/index.php`.

2. **Serverless Router (`api/index.php`)**:
   - Preserves all query parameters (`$_GET` & `$_REQUEST`) across serverless rewrites (e.g. invoice printing, product searches, filters).
   - Dynamically remaps `$_SERVER['SCRIPT_NAME']` & `$_SERVER['PHP_SELF']` to keep active menu highlights working.
   - Built-in aliases for clean URLs (`/login`, `/dashboard`, `/new_bill`, etc.).
   - Includes custom styled 404 handler.

3. **Hybrid Database Engine (`config/database.php`)**:
   - **Zero-Config Instant Mode (SQLite)**: If no MySQL credentials are set, it automatically initializes a self-contained SQLite database in Vercel's writable `/tmp` directory with all tables and demo inventory. **Deploys immediately without needing any database setup!**
   - **Production Cloud MySQL Mode**: Supports standard environment variables (`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`) or a single `DATABASE_URL` / `MYSQL_URL` string (e.g. TiDB Cloud, Aiven, PlanetScale, Railway, Supabase). Auto-initializes schema on first connection.

4. **Persistent Serverless Authentication**:
   - Serverless functions are stateless and spin up in separate containers.
   - An HMAC-SHA256 cryptographically signed cookie (`hw_auth_token`) ensures users stay logged in across cold starts and multiple Lambda container instances without random logouts.

5. **Dynamic Frontend URL Resolution**:
   - `window.APP_BASE_URL` is automatically passed to frontend scripts (`assets/js/app.js` and `customer/cart.php`) so POS billing API calls and invoice printing work on any Vercel domain.

---

## 🛠️ How to Deploy to Vercel

### Option A: Deploy via GitHub (Recommended & Easiest)

1. **Commit and Push your changes to GitHub**:
   ```bash
   git add .
   git commit -m "Configure project for Vercel serverless deployment"
   git push origin master
   ```

2. **Import into Vercel**:
   - Go to [vercel.com](https://vercel.com) and log in.
   - Click **Add New...** -> **Project**.
   - Select your GitHub repository (`chanchalparhad-web/saraswati-hardware-billing-and-managment-system`).
   - Leave Framework Preset as **Other** (Vercel automatically detects `vercel.json`).
   - Click **Deploy**!

---

### Option B: Deploy via Vercel CLI

If you have Node.js installed:

1. Install Vercel CLI (or use `npx`):
   ```bash
   npm install -g vercel
   ```

2. Run the deploy command from the project root:
   ```bash
   vercel
   ```
   *(Follow the command-line prompts to log in and select your project).*

3. For production deployment:
   ```bash
   vercel --prod
   ```

---

## 🗄️ Database Setup (Optional for Cloud MySQL)

The app works **immediately out-of-the-box** using the built-in SQLite engine in `/tmp`.

If you prefer a permanent cloud MySQL database for production:
1. Create a free MySQL database on any of these providers:
   - [TiDB Cloud (Free Serverless Tier)](https://tidbcloud.com)
   - [Aiven for MySQL (Free Tier)](https://aiven.io)
   - [Railway (Free Tier)](https://railway.app)
   - [Clever Cloud](https://www.clever-cloud.com)
2. In your Vercel Dashboard, go to **Settings** -> **Environment Variables**, and add either:

#### Method 1: Single Connection URL
| Key | Example Value |
| :--- | :--- |
| `DATABASE_URL` | `mysql://user:password@gateway01.us-east-1.tidbcloud.com:4000/hardware_store_db` |

#### Method 2: Individual Variables
| Key | Value |
| :--- | :--- |
| `DB_HOST` | `your-db-host.com` |
| `DB_PORT` | `3306` (or `4000` for TiDB) |
| `DB_NAME` | `your_db_name` |
| `DB_USER` | `your_db_username` |
| `DB_PASS` | `your_db_password` |
| `APP_SECRET` | *(Optional, random string for auth cookie signing)* |

---

## 🔑 Default Login Credentials

Once deployed, open your Vercel URL and sign in with:

| Role | Username | Password | Access |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` | Full Access (Dashboard, Inventory, POS, Purchases, Reports, Settings) |
| **Cashier / Staff** | `cashier` | `cashier123` | POS Billing & Basic Management |
| **Customer** | `customer` | `customer123` | Online Catalog, Cart & Order History |

---

## 💻 Running Locally

You can test locally using any of these commands:

- **Built-in PHP Server**:
  ```bash
  npm run dev
  # or: php -S localhost:8000 api/index.php
  ```
- **Node Server**:
  ```bash
  npm start
  ```
- **XAMPP / WampServer**:
  Place in `htdocs` and start Apache + MySQL.
