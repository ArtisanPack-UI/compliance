<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds a unique partial index to prevent concurrent
     * supersede race conditions where two grant calls for the same
     * user/purpose can both read the same existing record.
     *
     * For PostgreSQL: Uses a proper partial index
     * For MySQL/MariaDB: Uses a generated column approach
     * For SQLite: Uses a unique index with expression
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'pgsql' => $this->createPostgresIndex(),
            'mysql', 'mariadb' => $this->createMySqlIndex(),
            'sqlite' => $this->createSqliteIndex(),
            default => $this->createPostgresIndex(), // Fallback to PostgreSQL syntax
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'pgsql' => $this->dropPostgresIndex(),
            'mysql', 'mariadb' => $this->dropMySqlIndex(),
            'sqlite' => $this->dropSqliteIndex(),
            default => $this->dropPostgresIndex(),
        };
    }

    /**
     * Create PostgreSQL partial unique index.
     */
    protected function createPostgresIndex(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX consent_records_user_purpose_granted_unique
            ON consent_records (user_id, purpose)
            WHERE status = 'granted'
        ");
    }

    /**
     * Drop PostgreSQL partial unique index.
     */
    protected function dropPostgresIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS consent_records_user_purpose_granted_unique');
    }

    /**
     * Create MySQL/MariaDB unique index using a generated column.
     *
     * MySQL doesn't support partial indexes, so we use a generated column
     * that is NULL for non-granted records (NULL values don't violate unique constraints).
     */
    protected function createMySqlIndex(): void
    {
        // Add a generated column that's only populated for granted records
        DB::statement("
            ALTER TABLE consent_records
            ADD COLUMN granted_unique_key VARCHAR(255)
            GENERATED ALWAYS AS (
                CASE WHEN status = 'granted'
                    THEN CONCAT(user_id, ':', purpose)
                    ELSE NULL
                END
            ) STORED
        ");

        // Add unique index on the generated column
        DB::statement('
            CREATE UNIQUE INDEX consent_records_granted_unique_key
            ON consent_records (granted_unique_key)
        ');
    }

    /**
     * Drop MySQL/MariaDB unique index and generated column.
     */
    protected function dropMySqlIndex(): void
    {
        DB::statement('DROP INDEX consent_records_granted_unique_key ON consent_records');
        DB::statement('ALTER TABLE consent_records DROP COLUMN granted_unique_key');
    }

    /**
     * Create SQLite unique index using expression.
     */
    protected function createSqliteIndex(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX consent_records_user_purpose_granted_unique
            ON consent_records (user_id, purpose)
            WHERE status = 'granted'
        ");
    }

    /**
     * Drop SQLite unique index.
     */
    protected function dropSqliteIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS consent_records_user_purpose_granted_unique');
    }
};
