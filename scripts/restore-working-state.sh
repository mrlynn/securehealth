#!/bin/bash

# Restore to Working State Script
# This script restores the application to the last known working state

echo "🔄 Restoring SecureHealth to Working State..."

# Method 1: Restore from git tag
echo "📋 Available restore methods:"
echo "1. Restore from git tag (v1.0-working-state)"
echo "2. Restore from backup branch (backup-working-state)"
echo "3. Reset to specific commit"
echo ""

# Get current branch and commit
CURRENT_BRANCH=$(git branch --show-current)
CURRENT_COMMIT=$(git rev-parse HEAD)

echo "📍 Current state:"
echo "   Branch: $CURRENT_BRANCH"
echo "   Commit: $CURRENT_COMMIT"
echo ""

# Function to restore from tag
restore_from_tag() {
    echo "🏷️  Restoring from git tag v1.0-working-state..."
    git fetch --tags
    git reset --hard v1.0-working-state
    echo "✅ Restored to tag v1.0-working-state"
}

# Function to restore from backup branch
restore_from_branch() {
    echo "🌿 Restoring from backup branch backup-working-state..."
    git fetch origin backup-working-state
    git reset --hard origin/backup-working-state
    echo "✅ Restored to backup branch"
}

# Function to show restore options
show_options() {
    echo "🔧 Restore Options:"
    echo "   ./restore-working-state.sh tag     - Restore from git tag"
    echo "   ./restore-working-state.sh branch  - Restore from backup branch"
    echo "   ./restore-working-state.sh help    - Show this help"
    echo ""
    echo "⚠️  WARNING: This will reset your current changes!"
    echo "💡 Tip: Commit or stash your changes before restoring"
}

# Main script logic
case "$1" in
    "tag")
        restore_from_tag
        ;;
    "branch")
        restore_from_branch
        ;;
    "help"|"")
        show_options
        ;;
    *)
        echo "❌ Unknown option: $1"
        show_options
        exit 1
        ;;
esac

echo ""
echo "🚀 Next steps:"
echo "   1. git push --force-with-lease origin $CURRENT_BRANCH"
echo "   2. Monitor Railway deployment"
echo "   3. Test application functionality"
echo ""
echo "📝 Working state includes:"
echo "   ✅ Railway deployment functional"
echo "   ✅ Login working properly"
echo "   ✅ Session persistence fixed"
echo "   ✅ API routes working"
echo "   ✅ Dashboard loading correctly"
