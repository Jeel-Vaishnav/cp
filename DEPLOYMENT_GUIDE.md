# Campus Connect — Complete Cloud Deployment Guide (Netlify + Render + TiDB Cloud)

This guide walks you through deploying **Campus Connect** live to the internet:
* **Frontend**: Hosted on [Netlify](https://www.netlify.com/) (Fast global CDN, custom domains, free SSL).
* **Backend**: Hosted on [Render](https://render.com/) (Free Docker container running PHP 8.2 + Apache).
* **Database**: Hosted on [TiDB Cloud](https://tidbcloud.com/) (Free 5GB Serverless MySQL-compatible database, 0 credit card needed).

---

## Architecture Overview

```
 [User Browser]
       │
       ▼
 [Netlify Frontend (HTML/CSS/JS)]
       │
       │ Proxies `/api/*` requests seamlessly (Zero CORS)
       ▼
 [Render PHP 8.2 Backend]
       │
       │ PDO MySQL with TLS/SSL
       ▼
 [TiDB Cloud Serverless MySQL Database]
```

---

## Step 1: Set Up Free Cloud MySQL Database (TiDB Cloud)

1. Go to [https://tidbcloud.com](https://tidbcloud.com) and create a free account (Sign up with Google or GitHub).
2. Click **Create Cluster** and choose **Serverless** (Free 5GB, no credit card required).
3. Choose a region closest to you (e.g. AWS / us-east-1 or ap-south-1) and click **Create**.
4. In the cluster dashboard, click **Connect**:
   * Select **Language: PHP (PDO)** or General MySQL.
   * Note down your credentials:
     * **Host**: `gateway01.us-east-1.prod.aws.tidbcloud.com` (example)
     * **Port**: `4000`
     * **User**: `xxxxxx.root`
     * **Password**: `your_generated_password`
     * **Database**: `test` (or `campus_connect`)
5. In the left navigation, click **SQL Editor**:
   * Open the file `database/campus_connect.sql` from your project in your code editor.
   * Copy the entire SQL script contents.
   * Paste it into the TiDB Cloud SQL Editor and click **Run**.
   * Verify that all tables (`users`, `complaints`, `faculties`, `technicians`, etc.) and initial seed data are successfully created.

---

## Step 2: Push Your Code to GitHub

If you haven't already pushed your code to GitHub:
1. Initialize/commit all files:
   ```bash
   git add .
   git commit -m "Add cloud deployment configuration (Docker, Render, Netlify)"
   ```
2. Push to your GitHub repository:
   ```bash
   git push origin main
   ```

---

## Step 3: Deploy Backend on Render (Free PHP Web Service)

1. Go to [https://render.com](https://render.com) and sign in.
2. Click **New +** > **Web Service**.
3. Connect your GitHub repository.
4. Render will detect the `Dockerfile` automatically:
   * **Name**: `campus-connect-api`
   * **Region**: Choose the same or closest region to your TiDB cluster.
   * **Instance Type**: **Free**.
5. Scroll down to **Environment Variables** and add the following:

   | Key | Value | Notes |
   | :--- | :--- | :--- |
   | `PORT` | `80` | Port for Apache |
   | `DB_HOST` | `<your-tidb-host>` | From Step 1 |
   | `DB_PORT` | `4000` | Port 4000 for TiDB |
   | `DB_NAME` | `test` (or `campus_connect`) | Your TiDB database name |
   | `DB_USER` | `<your-tidb-user>` | From Step 1 |
   | `DB_PASS` | `<your-tidb-password>` | From Step 1 |
   | `DB_SSL` | `true` | Required for TiDB Cloud |

6. Under **Advanced**, set **Health Check Path** to:
   ```
   /api/health.php
   ```
7. Click **Create Web Service**.
8. Wait 2-3 minutes for the build to finish. Once live, Render will give you a public URL, for example:
   ```
   https://campus-connect-api.onrender.com
   ```
9. Test your backend by visiting:
   `https://campus-connect-api.onrender.com/api/health.php`
   It should return:
   ```json
   {
       "status": "ok",
       "database": "connected"
   }
   ```

---

## Step 4: Connect Frontend on Netlify

1. Open your local `netlify.toml` file in the project root.
2. Uncomment the redirect lines at the bottom and replace `YOUR_BACKEND_URL` with your actual Render URL:
   ```toml
   [[redirects]]
     from = "/api/*"
     to = "https://campus-connect-api.onrender.com/api/:splat"
     status = 200
     force = true
   ```
3. Commit and push this change to GitHub:
   ```bash
   git add netlify.toml
   git commit -m "Configure Netlify API proxy to Render backend"
   git push origin main
   ```
4. Go to [https://app.netlify.com](https://app.netlify.com) and sign in.
5. Click **Add new site** > **Import an existing project** > **GitHub**.
6. Select your `Campus - Connect` repository:
   * **Build command**: *(Leave blank)*
   * **Publish directory**: `.` *(current directory)*
7. Click **Deploy Campus Connect**.
8. Netlify will publish your site in seconds and provide a live URL (e.g., `https://campus-connect-xyz.netlify.app`).

---

## Step 5: Test the Live Deployment

1. Open your Netlify site URL in any browser.
2. Go to **Login** (`login.html`) and test logging in with the default seeded users:
   * **Student**: G.R. `1001` / Password: `password`
   * **Faculty**: ID `FAC101` / Password: `password`
   * **Technician**: ID `TECH101` / Password: `password`
   * **Admin**: Username `admin` / Password: `admin123`
3. Try submitting a complaint, tracking status, or uploading an attachment to verify the database and API functionality are working end-to-end!
