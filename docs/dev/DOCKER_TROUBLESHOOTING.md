# Docker Hanging Issue - Quick Fix

## Problem

Docker commands hang and never return:
- `docker ps` hangs
- `docker-compose` commands hang
- No errors, just infinite wait

## Root Cause

Docker Desktop daemon is hung/frozen. This happens when:
1. Docker Desktop ran out of resources
2. Socket file is stale
3. Docker Desktop needs restart

## Solution

### Step 1: Quit Docker Desktop Completely

**Option A - Using Menu:**
1. Click Docker icon in menu bar (top right)
2. Click "Quit Docker Desktop"
3. Wait 10 seconds

**Option B - Force Quit:**
1. Press `Cmd + Option + Esc`
2. Select "Docker Desktop"
3. Click "Force Quit"

**Option C - Command Line:**
```bash
# Kill Docker Desktop process
killall Docker
killall com.docker.backend
pkill -9 Docker
```

### Step 2: Clean Up Stale Socket (if needed)

```bash
# Remove the Colima socket reference
unset DOCKER_HOST

# Add to your ~/.zshrc to make permanent
echo 'unset DOCKER_HOST' >> ~/.zshrc
```

### Step 3: Start Docker Desktop

1. Open Docker Desktop app
2. Wait for "Docker Desktop is running" message
3. Check status:
   ```bash
   docker ps
   ```

Should return immediately with container list.

### Step 4: Restart Your Containers

```bash
cd /Users/michael.lynn/code/symfony/hipaa

# Start containers
docker-compose up -d

# Verify they're running
docker-compose ps
```

### Step 5: Run Knowledge Base Command

```bash
docker-compose exec php bin/console app:verify-knowledge-base
```

Should show your 110 indexed documents.

## Permanent Fix for DOCKER_HOST Issue

Your shell has `DOCKER_HOST` pointing to Colima. Remove it:

```bash
# Check current setting
echo $DOCKER_HOST

# If it shows Colima path, unset it permanently:
echo 'unset DOCKER_HOST' >> ~/.zshrc

# Reload shell
source ~/.zshrc

# Verify it's gone
echo $DOCKER_HOST
# (should be empty)
```

## Quick Health Check

After restarting Docker Desktop:

```bash
# 1. Docker should respond instantly
docker ps

# 2. Your containers should be running
docker-compose ps

# 3. PHP container should be accessible
docker-compose exec php php -v

# 4. Knowledge base should be intact
docker-compose exec php bin/console app:verify-knowledge-base
```

## If Still Hanging

### Nuclear Option - Complete Docker Reset:

```bash
# 1. Quit Docker Desktop completely
killall Docker

# 2. Remove Docker socket files
rm -f ~/.docker/run/docker.sock
rm -f /var/run/docker.sock

# 3. Clean Docker CLI cache
rm -rf ~/.docker/cli-plugins/

# 4. Restart Docker Desktop
open -a Docker

# 5. Wait 30 seconds, then test
docker ps
```

## Prevention

To avoid this in the future:

1. **Give Docker more resources:**
   - Docker Desktop → Settings → Resources
   - Increase RAM to 4-8 GB
   - Increase CPUs to 4

2. **Cleanup regularly:**
   ```bash
   docker system prune -a
   ```

3. **Remove DOCKER_HOST conflicts:**
   ```bash
   # Add to ~/.zshrc
   unset DOCKER_HOST
   export DOCKER_HOST=unix:///var/run/docker.sock
   ```

## Quick Commands Reference

```bash
# Check Docker status
docker info

# See what's using resources
docker stats

# Clean up
docker system prune -a --volumes

# Restart everything
docker-compose down
docker-compose up -d
```

## What to Do Right Now

1. **Force quit Docker Desktop**
   ```bash
   killall Docker
   ```

2. **Remove Colima reference**
   ```bash
   unset DOCKER_HOST
   ```

3. **Start Docker Desktop** (from Applications)

4. **Test connection**
   ```bash
   docker ps
   ```

5. **Start your containers**
   ```bash
   cd /Users/michael.lynn/code/symfony/hipaa
   docker-compose up -d
   ```

6. **Verify knowledge base**
   ```bash
   docker-compose exec php bin/console app:verify-knowledge-base
   ```

Your 110 indexed documents are safe in MongoDB - they won't be lost!

