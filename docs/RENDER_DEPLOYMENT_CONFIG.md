# render.yaml - Render.com Configuration for DLI Support Platform

services:
  # Main Laravel Application
  - type: web
    name: dli-support-platform
    env: docker
    dockerfilePath: ./Dockerfile
    plan: free  # or starter/standard for production
    region: oregon  # or singapore, frankfurt
    buildCommand: echo "Building with Docker..."
    healthCheckPath: /api/health
    
    # Environment Variables
    envVars:
      # Deployment Configuration
      - key: DEPLOYMENT_TYPE
        value: deploy-staging
      
      # Application Settings
      - key: APP_NAME
        value: DLI Support Platform
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - key: APP_URL
        sync: false  # Will be auto-set by Render
      - key: APP_FRONTEND_URL
        value: https://your-frontend-app.onrender.com
      
      # Database Configuration (Render will provide DATABASE_URL)
      - key: DB_CONNECTION
        value: pgsql  # Render uses PostgreSQL
      - key: DATABASE_URL
        fromDatabase:
          name: dli-support-db
          property: connectionString
      
      # Email Configuration (Mailtrap for development)
      - key: MAIL_MAILER
        value: smtp
      - key: MAIL_HOST
        value: smtp.mailtrap.io
      - key: MAIL_PORT
        value: 2525
      - key: MAIL_USERNAME
        value: your_mailtrap_username  # Replace with your Mailtrap username
      - key: MAIL_PASSWORD
        value: your_mailtrap_password  # Replace with your Mailtrap password
      - key: MAIL_ENCRYPTION
        value: tls
      - key: MAIL_FROM_ADDRESS
        value: noreply@dlisupport.com
      - key: MAIL_FROM_NAME
        value: DLI Support Platform
      
      # Email Feature Toggles
      - key: SEND_WELCOME_EMAILS
        value: true
      - key: SEND_BULK_OPERATION_REPORTS
        value: true
      - key: SEND_ADMIN_NOTIFICATIONS
        value: true
      - key: AUTO_GENERATE_PASSWORDS
        value: true
      
      # Queue Configuration
      - key: QUEUE_CONNECTION
        value: database
      - key: QUEUE_EMAILS
        value: emails
      - key: QUEUE_BULK_EMAILS
        value: bulk-emails
      - key: QUEUE_ADMIN_REPORTS
        value: admin-reports
      
      # Cache & Session
      - key: CACHE_DRIVER
        value: file
      - key: SESSION_DRIVER
        value: database
      - key: SESSION_LIFETIME
        value: 120
      
      # Security Settings
      - key: TEMPORARY_PASSWORD_EXPIRY_DAYS
        value: 7
      - key: MAX_LOGIN_ATTEMPTS
        value: 5
      - key: LOCKOUT_DURATION
        value: 30
      
      # File Upload Settings
      - key: MAX_CSV_FILE_SIZE_KB
        value: 10240
      - key: MAX_BULK_USERS_PER_IMPORT
        value: 1000
      - key: BULK_EMAIL_BATCH_SIZE
        value: 10
      
      # Laravel Key (generate with: php artisan key:generate --show)
      - key: APP_KEY
        value: base64:your-generated-app-key-here
      
      # CORS Configuration
      - key: CORS_ALLOWED_ORIGINS
        value: https://your-frontend-app.onrender.com
      - key: SANCTUM_STATEFUL_DOMAINS
        value: your-frontend-app.onrender.com

  # PostgreSQL Database
  - type: pserv
    name: dli-support-db
    env: postgres
    plan: free  # 256MB RAM, 1GB storage
    databaseName: dli_student_db
    databaseUser: dli_user
    ipAllowList: []  # Empty = allow all

# Alternative: Use external database services
# If you prefer MySQL or need more storage, you can use:
# - PlanetScale (MySQL)
# - Supabase (PostgreSQL) 
# - Railway (MySQL/PostgreSQL)
# - Neon (PostgreSQL)

---
# .env.render - Example environment file for Render deployment
# Copy these to your Render service environment variables

APP_NAME="DLI Support Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app-name.onrender.com
APP_FRONTEND_URL=https://your-frontend-app.onrender.com

# Deployment
DEPLOYMENT_TYPE=deploy-staging

# Database (Render auto-provides DATABASE_URL for PostgreSQL)
DB_CONNECTION=pgsql
# DATABASE_URL will be automatically set by Render

# Email Configuration (Mailtrap for testing)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@dlisupport.com"
MAIL_FROM_NAME="DLI Support Platform"

# Email Feature Controls
SEND_WELCOME_EMAILS=true
SEND_BULK_OPERATION_REPORTS=true
SEND_ADMIN_NOTIFICATIONS=true
AUTO_GENERATE_PASSWORDS=true

# Queue & Cache
QUEUE_CONNECTION=database
CACHE_DRIVER=file
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Security
TEMPORARY_PASSWORD_EXPIRY_DAYS=7
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_DURATION=30

# File Uploads
MAX_CSV_FILE_SIZE_KB=10240
MAX_BULK_USERS_PER_IMPORT=1000
BULK_EMAIL_BATCH_SIZE=10

# CORS
CORS_ALLOWED_ORIGINS=https://your-frontend-app.onrender.com
SANCTUM_STATEFUL_DOMAINS=your-frontend-app.onrender.com

# Laravel Key (generate with: php artisan key:generate --show)
APP_KEY=base64:your-generated-app-key-here

---
# Quick Render Deployment Steps:

# 1. Push your code to GitHub/GitLab
# 2. Connect repository to Render
# 3. Add environment variables from above
# 4. Deploy!

# Your app will be available at:
# https://your-app-name.onrender.com

# Email testing interface (Mailtrap):
# https://mailtrap.io/inboxes

# Health check:
# https://your-app-name.onrender.com/api/health

# API documentation:
# https://your-app-name.onrender.com/api/docs