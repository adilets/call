<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            if (Schema::hasColumn('customers', 'email')) {
                DB::statement('ALTER TABLE customers MODIFY email VARCHAR(255) NULL');
                DB::statement('ALTER TABLE customers DROP INDEX IF EXISTS customers_email_unique');
                DB::statement('ALTER TABLE customers DROP INDEX IF EXISTS email_unique');
            }

            if (Schema::hasColumn('customers', 'gender')) {
                DB::statement("ALTER TABLE customers MODIFY gender ENUM('male','female') NULL");
            }

            if (Schema::hasColumn('customers', 'phone')) {
                DB::statement("UPDATE customers SET phone = '' WHERE phone IS NULL");
                DB::statement('ALTER TABLE customers MODIFY phone VARCHAR(255) NOT NULL');
            }
        }

        if ($driver === 'pgsql') {
            if (Schema::hasColumn('customers', 'email')) {
                DB::statement('ALTER TABLE customers ALTER COLUMN email DROP NOT NULL');
                DB::statement('ALTER TABLE customers ALTER COLUMN email TYPE VARCHAR(255)');
                DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_email_unique');
                DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS email_unique');
            }

            if (Schema::hasColumn('customers', 'gender')) {
                DB::statement('ALTER TABLE customers ALTER COLUMN gender DROP NOT NULL');
            }

            if (Schema::hasColumn('customers', 'phone')) {
                DB::statement("UPDATE customers SET phone = '' WHERE phone IS NULL");
                DB::statement('ALTER TABLE customers ALTER COLUMN phone TYPE VARCHAR(255)');
                DB::statement('ALTER TABLE customers ALTER COLUMN phone SET NOT NULL');
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE customers MODIFY phone VARCHAR(255) NULL');
            DB::statement("ALTER TABLE customers MODIFY gender ENUM('male','female') NOT NULL");
            DB::statement('ALTER TABLE customers MODIFY email VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE customers ADD UNIQUE customers_email_unique (email)');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE customers ALTER COLUMN phone DROP NOT NULL');
            DB::statement('ALTER TABLE customers ALTER COLUMN phone TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE customers ALTER COLUMN gender SET NOT NULL');
            DB::statement('ALTER TABLE customers ALTER COLUMN email SET NOT NULL');
            DB::statement('ALTER TABLE customers ALTER COLUMN email TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_email_unique UNIQUE (email)');
        }
    }
};


