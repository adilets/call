<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Make email nullable, drop unique if exists
            if (Schema::hasColumn('customers', 'email')) {
                DB::statement('ALTER TABLE customers MODIFY email VARCHAR(255) NULL');
                // For MySQL/MariaDB drop unique index by name if known
                try { DB::statement('ALTER TABLE customers DROP INDEX customers_email_unique'); } catch (\Throwable $e) {}
                try { DB::statement('ALTER TABLE customers DROP INDEX email_unique'); } catch (\Throwable $e) {}
            }

            // Make gender nullable (convert enum to nullable enum)
            if (Schema::hasColumn('customers', 'gender')) {
                DB::statement("ALTER TABLE customers MODIFY gender ENUM('male','female') NULL");
            }

            // Make phone NOT NULL
            if (Schema::hasColumn('customers', 'phone')) {
                DB::statement('UPDATE customers SET phone = \'\' WHERE phone IS NULL');
                DB::statement('ALTER TABLE customers MODIFY phone VARCHAR(255) NOT NULL');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Revert phone to nullable
            DB::statement('ALTER TABLE customers MODIFY phone VARCHAR(255) NULL');
            // Revert gender to NOT NULL
            DB::statement("ALTER TABLE customers MODIFY gender ENUM('male','female') NOT NULL");
            // Revert email NOT NULL and add back unique
            DB::statement('ALTER TABLE customers MODIFY email VARCHAR(255) NOT NULL');
            try { DB::statement('ALTER TABLE customers ADD UNIQUE customers_email_unique (email)'); } catch (\Throwable $e) {}
        });
    }
};


