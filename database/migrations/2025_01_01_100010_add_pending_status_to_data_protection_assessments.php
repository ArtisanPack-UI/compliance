<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * This migration adds the 'pending' status to existing databases.
 *
 * Note: For new installations, the 'pending' status is already included in the
 * original table creation migration (2025_01_01_100002). This migration ensures
 * that databases created before the 'pending' status was added are updated.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL/MariaDB - check if 'pending' already exists before modifying
        if (DB::getDriverName() === 'mysql') {
            // Check if 'pending' is already in the enum
            $columnType = DB::selectOne(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'data_protection_assessments'
                 AND COLUMN_NAME = 'status'"
            );

            if ($columnType && ! str_contains($columnType->COLUMN_TYPE, "'pending'")) {
                DB::statement("ALTER TABLE data_protection_assessments MODIFY COLUMN status ENUM('draft', 'pending', 'in_review', 'approved', 'rejected', 'revision_required') DEFAULT 'draft'");
            }
        }

        // For PostgreSQL - IF NOT EXISTS handles idempotency
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TYPE data_protection_assessments_status_type ADD VALUE IF NOT EXISTS 'pending' AFTER 'draft'");
        }

        // For SQLite - no action needed (enums are stored as text)
    }

    /**
     * Reverse the migrations.
     *
     * WARNING: Removing enum values can cause data loss if records exist with
     * the 'pending' status. This rollback will fail if such records exist.
     *
     * PostgreSQL Note: PostgreSQL does not support removing enum values directly.
     * To rollback in PostgreSQL, you would need to:
     * 1. Create a new enum type without 'pending'
     * 2. Migrate all 'pending' records to another status (e.g., 'draft')
     * 3. Alter the column to use the new enum type
     * 4. Drop the old enum type
     * This is intentionally not automated due to the complexity and risk of data loss.
     */
    public function down(): void
    {
        // Check for existing 'pending' records before attempting rollback
        $pendingCount = DB::table('data_protection_assessments')
            ->where('status', 'pending')
            ->count();

        if ($pendingCount > 0) {
            throw new \RuntimeException(
                "Cannot rollback: {$pendingCount} records have 'pending' status. ".
                "Update these records to a different status before rolling back."
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE data_protection_assessments MODIFY COLUMN status ENUM('draft', 'in_review', 'approved', 'rejected', 'revision_required') DEFAULT 'draft'");
        }

        // PostgreSQL: Manual intervention required (see docblock above)
        // SQLite: No action needed
    }
};
