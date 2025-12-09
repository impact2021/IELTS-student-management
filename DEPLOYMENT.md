# Deployment Guide

This guide covers deploying the IELTS Membership System to production environments.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Configuration](#environment-configuration)
3. [Database Setup](#database-setup)
4. [Deployment Options](#deployment-options)
5. [Security Checklist](#security-checklist)
6. [Monitoring and Maintenance](#monitoring-and-maintenance)

## Prerequisites

- Node.js 16.x or higher
- npm or yarn
- Database (SQLite for development, PostgreSQL/MySQL recommended for production)
- Reverse proxy (nginx or Apache)
- SSL certificate (Let's Encrypt recommended)

## Environment Configuration

### 1. Create Production Environment File

```bash
cp .env.example .env
```

### 2. Configure Environment Variables

Edit `.env` with production values:

```bash
# Server Configuration
PORT=3000
NODE_ENV=production

# JWT Secret - MUST BE CHANGED!
# Generate with: node -e "console.log(require('crypto').randomBytes(64).toString('hex'))"
JWT_SECRET=your-very-long-and-secure-random-secret-key-here

# Database
DB_PATH=/var/lib/ielts-membership/membership.db

# CORS - Set to your actual domain
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
```

### 3. Generate Secure JWT Secret

```bash
node -e "console.log(require('crypto').randomBytes(64).toString('hex'))"
```

Copy the output and use it as your `JWT_SECRET`.

## Database Setup

### SQLite (Simple Deployment)

SQLite is fine for small-to-medium deployments (< 100 concurrent users).

```bash
# Ensure database directory exists
mkdir -p /var/lib/ielts-membership
chmod 750 /var/lib/ielts-membership

# Run the application - it will create the database automatically
npm start
```

### PostgreSQL (Recommended for Production)

For production with many users, migrate to PostgreSQL:

1. Install PostgreSQL
2. Create database and user:

```sql
CREATE DATABASE ielts_membership;
CREATE USER ielts_user WITH ENCRYPTED PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE ielts_membership TO ielts_user;
```

3. Update database configuration (requires code modification to use pg library)

### MySQL (Alternative)

Similar to PostgreSQL, MySQL is suitable for production deployments.

## Deployment Options

### Option 1: Traditional VPS/Server

#### Install Dependencies

```bash
cd /var/www/ielts-membership
npm install --production
```

#### Create Systemd Service

Create `/etc/systemd/system/ielts-membership.service`:

```ini
[Unit]
Description=IELTS Membership System
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/ielts-membership
Environment=NODE_ENV=production
ExecStart=/usr/bin/node /var/www/ielts-membership/src/server.js
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl enable ielts-membership
sudo systemctl start ielts-membership
sudo systemctl status ielts-membership
```

#### Configure Nginx Reverse Proxy

Create `/etc/nginx/sites-available/ielts-membership`:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/ielts-membership /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### Get SSL Certificate

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### Option 2: Docker Deployment

Create `Dockerfile`:

```dockerfile
FROM node:18-alpine

WORKDIR /app

COPY package*.json ./
RUN npm ci --production

COPY . .

EXPOSE 3000

CMD ["node", "src/server.js"]
```

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "3000:3000"
    environment:
      - NODE_ENV=production
      - JWT_SECRET=${JWT_SECRET}
      - DB_PATH=/data/membership.db
    volumes:
      - ./data:/data
    restart: unless-stopped
```

Deploy:

```bash
docker-compose up -d
```

### Option 3: Cloud Platforms

#### Heroku

```bash
heroku create your-app-name
heroku config:set JWT_SECRET=your-secret-here
heroku config:set NODE_ENV=production
git push heroku main
```

#### DigitalOcean App Platform

1. Connect your GitHub repository
2. Set environment variables in the dashboard
3. Deploy automatically on push

#### AWS Elastic Beanstalk

```bash
eb init
eb create ielts-membership-prod
eb deploy
```

## Security Checklist

Before deploying to production:

- [ ] Change `JWT_SECRET` to a secure random value
- [ ] Set `NODE_ENV=production`
- [ ] Configure proper CORS origins
- [ ] Enable HTTPS/SSL
- [ ] Set up firewall rules (only allow ports 80, 443, and SSH)
- [ ] Keep Node.js and dependencies updated
- [ ] Implement regular database backups
- [ ] Set up monitoring and alerting
- [ ] Review and test rate limiting settings
- [ ] Disable directory listing on static files
- [ ] Set secure HTTP headers (X-Frame-Options, etc.)
- [ ] Implement logging for security events
- [ ] Use a non-root user to run the application
- [ ] Restrict database file permissions
- [ ] Set up fail2ban for SSH protection

## Monitoring and Maintenance

### Logging

Add a process manager like PM2 for better logging:

```bash
npm install -g pm2
pm2 start src/server.js --name ielts-membership
pm2 logs ielts-membership
pm2 monit
```

### Database Backup

#### Automated SQLite Backup

Create `/usr/local/bin/backup-ielts-db.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/ielts-membership"
DB_PATH="/var/lib/ielts-membership/membership.db"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR
sqlite3 $DB_PATH ".backup '$BACKUP_DIR/membership_$DATE.db'"

# Keep only last 30 days of backups
find $BACKUP_DIR -name "membership_*.db" -mtime +30 -delete
```

Add to crontab:

```bash
0 2 * * * /usr/local/bin/backup-ielts-db.sh
```

### Monitoring

Use tools like:

- **PM2**: Process management and monitoring
- **New Relic**: Application performance monitoring
- **Sentry**: Error tracking
- **Prometheus + Grafana**: Metrics and dashboards
- **UptimeRobot**: Uptime monitoring

### Health Check Endpoint

Add to `src/app.js`:

```javascript
app.get('/health', (req, res) => {
  res.status(200).json({ 
    status: 'healthy',
    timestamp: new Date().toISOString()
  });
});
```

### Updating the Application

```bash
# Pull latest code
git pull origin main

# Install dependencies
npm install --production

# Restart service
sudo systemctl restart ielts-membership

# Or with PM2
pm2 restart ielts-membership
```

## Scaling

### Horizontal Scaling

Use a load balancer (nginx, HAProxy, or cloud load balancer) to distribute traffic across multiple instances:

```nginx
upstream ielts_backend {
    least_conn;
    server 10.0.1.10:3000;
    server 10.0.1.11:3000;
    server 10.0.1.12:3000;
}

server {
    location / {
        proxy_pass http://ielts_backend;
    }
}
```

### Database Scaling

1. Move to PostgreSQL or MySQL
2. Set up read replicas
3. Implement connection pooling
4. Consider caching layer (Redis)

## Troubleshooting

### Application Won't Start

Check logs:
```bash
sudo journalctl -u ielts-membership -f
```

### Database Locked

Ensure only one process is accessing the database, or switch to PostgreSQL.

### High Memory Usage

- Enable Node.js garbage collection
- Monitor for memory leaks
- Consider increasing server resources

### Performance Issues

- Add Redis for caching
- Optimize database queries
- Enable compression
- Implement CDN for static assets

## Support

For issues and questions:
- Check the logs first
- Review the API documentation
- Open an issue on GitHub
- Contact support team

## Additional Resources

- [Node.js Production Best Practices](https://github.com/goldbergyoni/nodebestpractices)
- [Express Security Best Practices](https://expressjs.com/en/advanced/best-practice-security.html)
- [SQLite Performance Tuning](https://www.sqlite.org/pragma.html)
